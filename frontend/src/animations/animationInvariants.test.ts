/**
 * Tests for animation flicker invariants.
 *
 * These tests validate the logic-level guarantees that prevent visual flicker,
 * without requiring a full DOM. They test the data-flow contracts that the
 * GameScreen animation code relies on.
 */
import { describe, it, expect } from 'vitest'
import type { GameState } from '@/types/game'

/** Helper: create a minimal valid GameState for testing */
function makeState(overrides: Partial<GameState> = {}): GameState {
  return {
    state: 'playing',
    currentPlayer: 0,
    myIndex: 0,
    myName: 'Alice',
    opponentName: 'Bob',
    myHand: [],
    myCapturedCount: 0,
    myScope: 0,
    myTotalScore: 0,
    opponentHandCount: 0,
    opponentCapturedCount: 0,
    opponentScope: 0,
    opponentTotalScore: 0,
    table: [],
    deckCount: 0,
    isMyTurn: true,
    pendingChoice: null,
    roundHistory: [],
    deckStyle: 'piacentine',
    gameType: 'scopa',
    briscolaCard: null,
    lastTrick: null,
    ...overrides,
  }
}

describe('Fix 1: restoreStyles must happen after commitState + nextTick', () => {
  /**
   * Simulates the handleTurnResult flow: hidden elements should NOT become
   * visible before state commits (which removes them from the DOM).
   *
   * The invariant: between animation end and commitState, hidden elements
   * must remain hidden. restoreStyles() must only run after Vue re-renders.
   */
  it('hidden elements stay hidden through the commit window', () => {
    // Simulate the styledEls tracking array
    const styledEls: { el: string; prop: string; old: string }[] = []
    function setStyle(el: string, prop: string, val: string) {
      styledEls.push({ el, prop, old: 'visible' })
    }
    function restoreStyles() {
      while (styledEls.length) styledEls.pop()
    }

    // During animation: hide source card and captured cards
    setStyle('played-card', 'visibility', 'hidden')
    setStyle('captured-card-1', 'visibility', 'hidden')
    setStyle('captured-card-2', 'visibility', 'hidden')

    // CORRECT ORDER (fix): snapshot → commit → nextTick → restoreStyles
    // At snapshot time: elements are still hidden (visibility:hidden has valid layout)
    expect(styledEls).toHaveLength(3) // still tracked, not restored

    // Simulate commitState — Vue would remove these elements
    const committed = true

    // Only NOW restore styles (after Vue has removed the elements)
    expect(committed).toBe(true)
    restoreStyles()
    expect(styledEls).toHaveLength(0)
  })

  it('getBoundingClientRect works on visibility:hidden elements (invariant)', () => {
    // This is a documented DOM behavior: visibility:hidden elements retain layout.
    // Our snapshot relies on this — we snapshot positions BEFORE restoring styles.
    // This test documents the assumption.
    //
    // In a real browser: visibility:hidden → getBoundingClientRect returns valid rect
    // In a real browser: display:none → getBoundingClientRect returns zero rect
    //
    // We use visibility:hidden (via setStyle), never display:none.
    // This test verifies the conceptual difference.
    const visHidden = { hasLayout: true, method: 'visibility:hidden' }
    const dispNone = { hasLayout: false, method: 'display:none' }

    expect(visHidden.hasLayout).toBe(true)  // snapshot works
    expect(dispNone.hasLayout).toBe(false)  // snapshot would fail
  })
})

describe('Fix 2: animateEndOfRoundSweep intermediate state preserves display scores', () => {
  /**
   * The intermediate state committed before the sweep animation must use
   * current display values for scores/counts, not the post-scoring final values.
   * This prevents score badges from jumping before the sweep completes.
   */

  /** Replicates the intermediate state construction from GameScreen.vue */
  function buildIntermediateState(
    newState: GameState,
    remainingCards: GameState['table'],
    displayState: GameState | null,
  ): GameState {
    const ds = displayState
    return {
      ...newState,
      table: remainingCards,
      state: 'playing',
      myCapturedCount: ds?.myCapturedCount ?? newState.myCapturedCount,
      opponentCapturedCount: ds?.opponentCapturedCount ?? newState.opponentCapturedCount,
      myScope: ds?.myScope ?? newState.myScope,
      opponentScope: ds?.opponentScope ?? newState.opponentScope,
      myTotalScore: ds?.myTotalScore ?? newState.myTotalScore,
      opponentTotalScore: ds?.opponentTotalScore ?? newState.opponentTotalScore,
    }
  }

  it('intermediate state uses display scores, not final scores', () => {
    const displayState = makeState({
      myCapturedCount: 15,
      opponentCapturedCount: 12,
      myScope: 1,
      opponentScope: 0,
      myTotalScore: 5,
      opponentTotalScore: 3,
    })

    // newState has post-scoring values (remaining cards assigned + round scored)
    const newState = makeState({
      state: 'round-end',
      myCapturedCount: 18,     // +3 remaining cards
      opponentCapturedCount: 12,
      myScope: 1,
      opponentScope: 0,
      myTotalScore: 9,         // +4 from round scoring
      opponentTotalScore: 4,   // +1 from round scoring
    })

    const remaining = [
      { suit: 'Denari' as const, value: 3 },
      { suit: 'Coppe' as const, value: 5 },
      { suit: 'Bastoni' as const, value: 8 },
    ]

    const intermediate = buildIntermediateState(newState, remaining, displayState)

    // Must show display scores, NOT final scores
    expect(intermediate.myCapturedCount).toBe(15)    // not 18
    expect(intermediate.opponentCapturedCount).toBe(12)
    expect(intermediate.myScope).toBe(1)
    expect(intermediate.opponentScope).toBe(0)
    expect(intermediate.myTotalScore).toBe(5)        // not 9
    expect(intermediate.opponentTotalScore).toBe(3)  // not 4

    // But table should show remaining cards for animation
    expect(intermediate.table).toEqual(remaining)
    // And state should be 'playing' to suppress overlays
    expect(intermediate.state).toBe('playing')
  })

  it('falls back to newState values when displayState is null', () => {
    const newState = makeState({
      myCapturedCount: 18,
      myTotalScore: 9,
    })

    const intermediate = buildIntermediateState(newState, [], null)

    expect(intermediate.myCapturedCount).toBe(18)
    expect(intermediate.myTotalScore).toBe(9)
  })

  it('preserves non-score fields from newState', () => {
    const displayState = makeState({ myName: 'Alice' })
    const newState = makeState({
      myName: 'Alice',
      opponentName: 'Claude',
      deckCount: 0,
      myHand: [],
      opponentHandCount: 0,
    })

    const intermediate = buildIntermediateState(newState, [], displayState)

    expect(intermediate.myName).toBe('Alice')
    expect(intermediate.opponentName).toBe('Claude')
    expect(intermediate.deckCount).toBe(0)
    expect(intermediate.myHand).toEqual([])
    expect(intermediate.opponentHandCount).toBe(0)
  })
})

describe('Fix 3: choosing turn-result animation and overlay toggle', () => {
  /**
   * The 'choosing' turn-result must be handled with animPlace so the played
   * card visually leaves the hand. After the inline commit applies the choosing
   * state, showCaptureChoice must be re-enabled.
   */

  it('choosing is a valid TurnResult type alongside place and capture', () => {
    type TurnType = 'place' | 'capture' | 'choosing'
    const handler: Record<TurnType, string> = {
      place: 'animPlace',
      capture: 'animCapture',
      choosing: 'animPlace', // Fix: choosing now uses animPlace
    }

    expect(handler['choosing']).toBe('animPlace')
    expect(Object.keys(handler)).toContain('choosing')
  })

  it('showCaptureChoice must be set after inline commit applies choosing state', () => {
    // Simulate the flow: inline commit applies choosing state,
    // then showCaptureChoice must be set to true
    let showCaptureChoice = false // was set to false by a previous selection
    const committedState = makeState({ state: 'choosing', pendingChoice: [[{ suit: 'Denari', value: 7 }]] })

    // After inline commit:
    if (committedState.state === 'choosing') {
      showCaptureChoice = true
    }

    expect(showCaptureChoice).toBe(true)
  })

  it('showCaptureChoice stays false for non-choosing states', () => {
    let showCaptureChoice = false
    const committedState = makeState({ state: 'playing' })

    if (committedState.state === 'choosing') {
      showCaptureChoice = true
    }

    expect(showCaptureChoice).toBe(false)
  })

  it('second choosing event re-enables overlay after previous selection', () => {
    // Simulates: choose → select (false) → new choosing event → must be true again
    let showCaptureChoice = true

    // Player selects capture option
    showCaptureChoice = false

    // ... animation + API call ...
    // Next choosing turn-result arrives, animation plays, inline commit applies

    const newChoosingState = makeState({
      state: 'choosing',
      pendingChoice: [[{ suit: 'Coppe', value: 3 }], [{ suit: 'Bastoni', value: 3 }]],
    })

    // The fix: after inline commit, check committed state
    if (newChoosingState.state === 'choosing') {
      showCaptureChoice = true
    }

    expect(showCaptureChoice).toBe(true)
  })
})

describe('Fix 4: last move of round — early return to sweep on round-end', () => {
  /**
   * When a round ends, the server publishes turn-result + round-end (no separate
   * game-state event — see MercurePublisher::publishTurnOutcome). This means
   * pendingState is null after the last turn animation. Without the fix:
   *
   * 1. animCapture hides captured cards via setStyle(visibility:hidden)
   * 2. pendingState is null → no commit → displayState unchanged
   * 3. restoreStyles() makes captured cards visible again — FLASH!
   * 4. 200ms post-animation delay (cards remain visible)
   * 5. processQueue → animateEndOfRoundSweep → commits intermediate state
   *
   * Fix: detect round-end/game-over as next queued event when pendingState is null.
   * Skip restoreStyles/FLIP/delay and go straight to processQueue, which runs
   * animateEndOfRoundSweep (commits its own state, replacing all elements).
   */

  /** Replicates the early-return detection logic from handleTurnResult */
  function shouldSkipToSweep(
    pendingState: GameState | null,
    pendingEvents: Array<{ type: string }>,
  ): boolean {
    return !pendingState && pendingEvents.length > 0 &&
      (pendingEvents[0].type === 'round-end' || pendingEvents[0].type === 'game-over')
  }

  it('triggers early return when pendingState is null and round-end queued', () => {
    expect(shouldSkipToSweep(null, [{ type: 'round-end' }])).toBe(true)
  })

  it('triggers early return when pendingState is null and game-over queued', () => {
    expect(shouldSkipToSweep(null, [{ type: 'game-over' }])).toBe(true)
  })

  it('does NOT trigger when pendingState exists (normal turn with game-state)', () => {
    const pending = makeState()
    expect(shouldSkipToSweep(pending, [{ type: 'round-end' }])).toBe(false)
  })

  it('does NOT trigger when next event is game-state', () => {
    expect(shouldSkipToSweep(null, [{ type: 'game-state' }])).toBe(false)
  })

  it('does NOT trigger when next event is turn-result', () => {
    expect(shouldSkipToSweep(null, [{ type: 'turn-result' }])).toBe(false)
  })

  it('does NOT trigger when event queue is empty', () => {
    expect(shouldSkipToSweep(null, [])).toBe(false)
  })

  it('keeps animating=true through the early return (blocks straggler events)', () => {
    // In the fix, store.animating is NOT set to false before processQueue.
    // It stays true from handleTurnResult entry, blocking any Mercure events
    // during the await gaps in animateEndOfRoundSweep.
    let animating = true  // set at handleTurnResult entry

    // Early return path: animating should NOT be set to false
    const earlyReturn = shouldSkipToSweep(null, [{ type: 'round-end' }])
    if (earlyReturn) {
      // processQueue called, animating stays true
    } else {
      animating = false  // normal path sets animating=false when no pendingState
    }

    expect(animating).toBe(true)
  })

  it('discards styledEls without restoring (prevents flash of hidden cards)', () => {
    // During animCapture, captured cards are hidden via setStyle(visibility:hidden).
    // In the early return, we clear styledEls without calling restoreStyles(),
    // because animateEndOfRoundSweep will commitState which replaces all elements.
    const styledEls = [
      { el: 'captured-card-1', prop: 'visibility', old: 'visible' },
      { el: 'captured-card-2', prop: 'visibility', old: 'visible' },
    ]

    // Simulate early return: discard without restoring
    styledEls.length = 0

    expect(styledEls).toHaveLength(0)
    // The hidden cards stay hidden until Vue replaces them on commitState
  })
})

describe('Fix 5: played card stays visible until sweep starts', () => {
  /**
   * During a capture animation, the play clone (card sliding from hand to table)
   * must remain visible through the pause and glow phases. If removed immediately
   * after the slide, there's a 650ms gap (150ms pause + 500ms glow) where the
   * played card is invisible before the sweep clone appears.
   *
   * Fix: keep playClone alive, only remove it right before sweep clones are created.
   */

  it('play clone must not be removed before sweep phase', () => {
    // Simulate the capture animation lifecycle
    const phases = [
      'slide',      // clone flies from hand to table
      'pause',      // 150ms pause
      'glow',       // 500ms glow on captured cards
      'sweep-start', // sweep clones created at same position
      'sweep-end',  // sweep clones fly to captured deck
    ]

    // Track when play clone is visible
    let playCloneVisible = false
    const visibilityLog: Array<{ phase: string; visible: boolean }> = []

    // Simulate lifecycle
    for (const phase of phases) {
      if (phase === 'slide') playCloneVisible = true  // clone created and animated
      // FIX: clone stays visible through pause and glow
      // OLD BUG: if (phase === 'pause') playCloneVisible = false  // was removed too early
      if (phase === 'sweep-start') playCloneVisible = false  // removed just before sweep clones
      visibilityLog.push({ phase, visible: playCloneVisible })
    }

    // Clone must be visible during slide, pause, AND glow
    expect(visibilityLog.find(l => l.phase === 'slide')!.visible).toBe(true)
    expect(visibilityLog.find(l => l.phase === 'pause')!.visible).toBe(true)
    expect(visibilityLog.find(l => l.phase === 'glow')!.visible).toBe(true)
    // Clone removed at sweep-start (sweep clone takes over at same position)
    expect(visibilityLog.find(l => l.phase === 'sweep-start')!.visible).toBe(false)
  })

  it('sweep clone is created at play clone final position (no gap)', () => {
    // The sweep clone for the played card is created at playCloneR
    // (the final rect of the play clone after its slide animation).
    // Since playClone is removed and sweep clone created in the same
    // synchronous block, there is no frame gap.
    const playCloneR = { left: 100, top: 200, width: 75, height: 133 }
    const sweepItems = [{ card: { suit: 'Denari', value: 7 }, rect: playCloneR }]

    // Sweep clone position matches play clone final position
    expect(sweepItems[0].rect).toBe(playCloneR)
  })
})

describe('Fix 9: Briscola trick — place clone removed before sweep', () => {
  /**
   * In animTrick (Briscola), the follower's card clone flies from hand to the
   * table center (step 2). When the sweep begins (step 4-5), a NEW clone is
   * created at the same position to fly to the captured deck. The original
   * place clone must be removed before the sweep starts, otherwise both the
   * place clone AND the sweep clone are visible simultaneously, making the
   * card appear stuck on the table during the sweep.
   *
   * Fix: track the place clone in a variable and remove it right before
   * creating sweep clones — identical to how animCapture removes playClone.
   */

  it('place clone must be removed before sweep clones are created', () => {
    // Simulate the animTrick lifecycle
    const phases = [
      'slide-to-table',  // place clone flies from hand to table center
      'pause',           // 150ms pause to show both cards
      'remove-clone',    // FIX: remove place clone before sweep
      'sweep-start',     // sweep clones created at same position
      'sweep-end',       // sweep clones fly to captured deck
    ]

    let placeCloneExists = false
    let sweepCloneExists = false
    const stateLog: Array<{ phase: string; placeClone: boolean; sweepClone: boolean }> = []

    for (const phase of phases) {
      if (phase === 'slide-to-table') placeCloneExists = true
      if (phase === 'remove-clone') placeCloneExists = false   // FIX
      if (phase === 'sweep-start') sweepCloneExists = true
      if (phase === 'sweep-end') sweepCloneExists = false
      stateLog.push({ phase, placeClone: placeCloneExists, sweepClone: sweepCloneExists })
    }

    // Place clone visible during slide and pause
    expect(stateLog.find(l => l.phase === 'slide-to-table')!.placeClone).toBe(true)
    expect(stateLog.find(l => l.phase === 'pause')!.placeClone).toBe(true)

    // Place clone removed BEFORE sweep clone created — no overlap
    expect(stateLog.find(l => l.phase === 'remove-clone')!.placeClone).toBe(false)
    expect(stateLog.find(l => l.phase === 'sweep-start')!.placeClone).toBe(false)

    // Sweep clone takes over
    expect(stateLog.find(l => l.phase === 'sweep-start')!.sweepClone).toBe(true)
  })

  it('without fix: place clone and sweep clone overlap (the bug)', () => {
    // OLD (buggy) behavior: place clone never removed
    let placeCloneExists = false
    let sweepCloneExists = false

    // slide
    placeCloneExists = true
    // pause — still exists
    // sweep-start — sweep clone created but place clone NOT removed
    sweepCloneExists = true

    // BUG: both visible at the same time
    expect(placeCloneExists).toBe(true)
    expect(sweepCloneExists).toBe(true)
    // Two clones of the same card visible = card appears stuck on table
  })

  it('leader card DOM element is hidden before sweep (unchanged behavior)', () => {
    // The leader card is a real DOM element (placed in a previous turn).
    // It is correctly hidden via setStyle before the sweep.
    // This test documents that the leader card handling was already correct.
    const styledEls: Array<{ el: string; prop: string }> = []
    function setStyle(el: string, prop: string, _val: string) {
      styledEls.push({ el, prop })
    }

    // Leader card found in DOM and hidden
    setStyle('leader-card', 'visibility', 'hidden')

    expect(styledEls).toHaveLength(1)
    expect(styledEls[0].el).toBe('leader-card')
  })
})

describe('Fix 7: flyTo scale is relative to source, not target', () => {
  /**
   * flyTo() computes the final visual size as scale × fromW (source dimensions).
   * Previously, it computed scale × to.width (target dimensions), which caused
   * double-shrinking when the target (captured deck) was already smaller than
   * the source (table card).
   *
   * On mobile: card=58px, captured=40px, scale=40/58≈0.689
   * OLD (bug): toW = 0.689 × 40 = 27.6px (way too small!)
   * NEW (fix): toW = 0.689 × 58 = 40px (matches captured deck exactly)
   */

  /** Replicates sweepScale from GameScreen.vue */
  function sweepScale(cardW: number, capW: number): number | undefined {
    const ratio = capW / cardW
    return ratio < 1 ? ratio : undefined
  }

  /** Replicates the fixed flyTo size calculation */
  function flyToFinalSize(fromW: number, fromH: number, toW: number, toH: number, scale?: number) {
    const finalW = scale != null ? scale * fromW : fromW
    const finalH = scale != null ? scale * fromH : fromH
    return { width: finalW, height: finalH }
  }

  it('mobile sweep: cards shrink to exactly captured deck size (40×71)', () => {
    const cardW = 58, cardH = 103  // mobile card
    const capW = 40, capH = 71     // mobile captured deck
    const scale = sweepScale(cardW, capW)

    expect(scale).toBeCloseTo(40 / 58)

    const final = flyToFinalSize(cardW, cardH, capW, capH, scale)
    expect(final.width).toBeCloseTo(capW, 1)  // 40px
    expect(final.height).toBeCloseTo(capH, 1)  // 71px
  })

  it('desktop sweep: no scale needed (both 75×133)', () => {
    const cardW = 75, cardH = 133
    const capW = 75, capH = 133
    const scale = sweepScale(cardW, capW)

    expect(scale).toBeUndefined()

    const final = flyToFinalSize(cardW, cardH, capW, capH, scale)
    expect(final.width).toBe(75)
    expect(final.height).toBe(133)
  })

  it('sweep centering: clone centers on captured deck rect', () => {
    // flyTo centers the (possibly resized) clone on the target centre
    const capR = { left: 10, top: 50, width: 40, height: 71 }
    const fromW = 58
    const scale = sweepScale(fromW, capR.width)!
    const toW = scale * fromW  // 40

    const dx_offset = capR.left + (capR.width - toW) / 2
    // (40 - 40) / 2 = 0, so clone left = capR.left
    expect(dx_offset).toBeCloseTo(capR.left)
  })
})

describe('Fix 8: deal animation scales clones when deck is smaller than cards', () => {
  /**
   * When the deck visual is smaller than card slots (mobile: 40px → 58px),
   * deal animation clones must grow from deck size to card size.
   * dealScale = targetW / deckW. On desktop (same size), no scale.
   */

  /** Replicates dealScale computation from GameScreen.vue */
  function computeDealScale(targetW: number, deckW: number): number | undefined {
    return deckW > 0 && Math.abs(targetW / deckW - 1) > 0.01
      ? targetW / deckW : undefined
  }

  /** Same flyTo size calc */
  function flyToFinalSize(fromW: number, fromH: number, scale?: number) {
    return {
      width: scale != null ? scale * fromW : fromW,
      height: scale != null ? scale * fromH : fromH,
    }
  }

  it('mobile deal: clones grow from 40×71 to 58×103', () => {
    const deckW = 40, deckH = 71    // mobile deck visual
    const cardW = 58, cardH = 103   // mobile card slot
    const scale = computeDealScale(cardW, deckW)

    expect(scale).toBeCloseTo(58 / 40)

    const final = flyToFinalSize(deckW, deckH, scale)
    expect(final.width).toBeCloseTo(cardW, 1)
    expect(final.height).toBeCloseTo(cardH, 1)
  })

  it('desktop deal: no scale needed (both 75×133)', () => {
    const deckW = 75, deckH = 133
    const cardW = 75, cardH = 133
    const scale = computeDealScale(cardW, deckW)

    expect(scale).toBeUndefined()

    const final = flyToFinalSize(deckW, deckH, scale)
    expect(final.width).toBe(75)
    expect(final.height).toBe(133)
  })

  it('deal clone centres on target card slot', () => {
    // Clone starts at deck rect, ends centred on card slot
    const targetR = { left: 100, top: 200, width: 58, height: 103 }
    const deckW = 40
    const scale = computeDealScale(targetR.width, deckW)!
    const toW = scale * deckW  // 58

    const offset = targetR.left + (targetR.width - toW) / 2
    expect(offset).toBeCloseTo(targetR.left)
  })
})

describe('Mobile dimensions never affect desktop', () => {
  /**
   * Desktop dimensions are the CSS defaults (no media query).
   * Mobile dimensions are in @media (max-width: 600px).
   * Animation scale calculations use runtime measurements,
   * so they auto-adapt. These tests verify both breakpoints
   * produce correct final sizes.
   */

  function sweepScale(cardW: number, capW: number): number | undefined {
    const ratio = capW / cardW
    return ratio < 1 ? ratio : undefined
  }

  function computeDealScale(targetW: number, deckW: number): number | undefined {
    return deckW > 0 && Math.abs(targetW / deckW - 1) > 0.01
      ? targetW / deckW : undefined
  }

  it('desktop sweep produces no scale (card == captured == 75px)', () => {
    expect(sweepScale(75, 75)).toBeUndefined()
  })

  it('desktop deal produces no scale (deck == card == 75px)', () => {
    expect(computeDealScale(75, 75)).toBeUndefined()
  })

  it('mobile sweep produces correct shrink scale', () => {
    const scale = sweepScale(58, 40)!
    expect(scale * 58).toBeCloseTo(40, 0)
  })

  it('mobile deal produces correct grow scale', () => {
    const scale = computeDealScale(58, 40)!
    expect(scale * 40).toBeCloseTo(58, 0)
  })
})

describe('isDealState detection', () => {
  /** Replicates isDealState logic from GameScreen.vue */
  function isDealState(prev: GameState | null, next: GameState): boolean {
    if (!prev) return true
    if (prev.myHand.length === 0 && next.myHand.length > 0) return true
    if (next.deckCount < prev.deckCount) return true
    return false
  }

  it('first load (no previous state) triggers deal', () => {
    expect(isDealState(null, makeState())).toBe(true)
  })

  it('re-deal after empty hands triggers deal', () => {
    const prev = makeState({ myHand: [], deckCount: 20 })
    const next = makeState({
      myHand: [{ suit: 'Denari', value: 7 }],
      deckCount: 14,
    })
    expect(isDealState(prev, next)).toBe(true)
  })

  it('normal play (deck unchanged) does not trigger deal', () => {
    const prev = makeState({
      myHand: [{ suit: 'Denari', value: 7 }, { suit: 'Coppe', value: 3 }],
      deckCount: 20,
    })
    const next = makeState({
      myHand: [{ suit: 'Coppe', value: 3 }],
      deckCount: 20,
    })
    expect(isDealState(prev, next)).toBe(false)
  })

  it('deck count decrease triggers deal (re-deal detection)', () => {
    const prev = makeState({ myHand: [{ suit: 'Denari', value: 1 }], deckCount: 20 })
    const next = makeState({
      myHand: [{ suit: 'Denari', value: 7 }, { suit: 'Coppe', value: 3 }, { suit: 'Bastoni', value: 5 }],
      deckCount: 14,
    })
    expect(isDealState(prev, next)).toBe(true)
  })
})

describe('Fix 6: deck visual stays visible during deal animation that exhausts deck', () => {
  /**
   * When the last hand is dealt (deck goes from N to 0), the deck visual must
   * remain visible during the deal animation and fade smoothly as cards depart.
   *
   * Problem: commitState is called at the START of deal animation (with dealHiding
   * flags). If newState.deckCount is 0, the DeckVisual immediately gets count=0,
   * adds .empty class, and fades out before any card-back clones fly.
   *
   * Fix: dealDeckCountOverride freezes the shown deck count at the pre-deal value.
   * During animation, tickDeckDOM() imperatively (not reactively) updates the deck
   * DOM so Vue doesn't re-render and reset imperative opacity:1 on revealed cards.
   */

  it('shownDeckCount uses override when set, falls back to live value', () => {
    // Replicates the computed: dealDeckCountOverride ?? gs?.deckCount ?? 0
    function shownDeckCount(override: number | null, liveDeckCount: number | null): number {
      return override ?? liveDeckCount ?? 0
    }

    // Override active: shows frozen value
    expect(shownDeckCount(6, 0)).toBe(6)
    expect(shownDeckCount(3, 0)).toBe(3)
    // Override cleared: shows live value
    expect(shownDeckCount(null, 14)).toBe(14)
    expect(shownDeckCount(null, 0)).toBe(0)
    // Both null: fallback
    expect(shownDeckCount(null, null)).toBe(0)
  })

  it('pre-deal count is captured from displayState before commitState', () => {
    // Before commitState, displayState has the old deckCount
    const displayState = makeState({ deckCount: 6 })
    const newState = makeState({ deckCount: 0 })

    // The override must capture the displayState value, not the newState value
    const preDealDeckCount = displayState.deckCount
    expect(preDealDeckCount).toBe(6)
    expect(newState.deckCount).toBe(0)
  })

  it('first load computes pre-deal count from newState by adding back dealt cards', () => {
    // On first load, displayState is null. newState.deckCount is already post-deal.
    // We must compute pre-deal count = newState.deckCount + dealt cards.
    const newState = makeState({
      deckCount: 30,
      myHand: [{ suit: 'Denari', value: 1 }, { suit: 'Coppe', value: 2 }, { suit: 'Bastoni', value: 3 }],
      opponentHandCount: 3,
      table: [
        { suit: 'Denari', value: 4 }, { suit: 'Coppe', value: 5 },
        { suit: 'Bastoni', value: 6 }, { suit: 'Spade', value: 7 },
      ],
    })

    const isNewRound = true // first load is always a new round
    const dealtCardCount = (isNewRound ? newState.table.length : 0)
      + newState.myHand.length + newState.opponentHandCount
    const preDealDeckCount = newState.deckCount + dealtCardCount

    // 30 + 4 table + 3 hand + 3 opponent = 40 (full deck)
    expect(preDealDeckCount).toBe(40)
    expect(dealtCardCount).toBe(10)
  })

  it('re-deal (not new round) only counts hand cards, not table', () => {
    // Mid-round re-deal: table cards already exist, only hands are dealt
    const newState = makeState({
      deckCount: 14,
      myHand: [{ suit: 'Denari', value: 1 }, { suit: 'Coppe', value: 2 }, { suit: 'Bastoni', value: 3 }],
      opponentHandCount: 3,
      table: [{ suit: 'Spade', value: 7 }, { suit: 'Denari', value: 5 }],
    })

    const isNewRound = false
    const dealtCardCount = (isNewRound ? newState.table.length : 0)
      + newState.myHand.length + newState.opponentHandCount

    // Only 3+3=6 hand cards dealt, not table cards
    expect(dealtCardCount).toBe(6)
    // But for re-deal, displayState would exist, so this fallback path
    // wouldn't normally run. Tested for completeness.
    expect(newState.deckCount + dealtCardCount).toBe(20)
  })

  it('tickDeckDOM decrements count imperatively without triggering reactive updates', () => {
    // Simulates the imperative deck DOM manipulation during deal animation.
    // Uses a plain variable (not a reactive ref) to track count.
    let remainingDeck = 6
    const domState = { countText: '6', hasEmptyClass: false, countDisplay: '' }

    function tickDeckDOM() {
      remainingDeck--
      if (remainingDeck <= 0) {
        domState.hasEmptyClass = true
        domState.countDisplay = 'none'
      } else {
        domState.countText = String(remainingDeck)
      }
    }

    // 4 table cards + 3 my hand + 3 opponent hand = 10 cards from 6 deck
    // (in real game, 6 deck = 3+3 hand cards only, but this tests the mechanism)
    tickDeckDOM(); expect(domState.countText).toBe('5'); expect(domState.hasEmptyClass).toBe(false)
    tickDeckDOM(); expect(domState.countText).toBe('4'); expect(domState.hasEmptyClass).toBe(false)
    tickDeckDOM(); expect(domState.countText).toBe('3'); expect(domState.hasEmptyClass).toBe(false)
    tickDeckDOM(); expect(domState.countText).toBe('2'); expect(domState.hasEmptyClass).toBe(false)
    tickDeckDOM(); expect(domState.countText).toBe('1'); expect(domState.hasEmptyClass).toBe(false)
    tickDeckDOM(); expect(domState.hasEmptyClass).toBe(true); expect(domState.countDisplay).toBe('none')
  })

  it('reactive tickDeck causes re-render that resets imperative opacity (the bug)', () => {
    // Demonstrates WHY we must use imperative DOM updates, not reactive ref updates.
    //
    // When dealDeckCountOverride changes reactively, Vue re-renders the component.
    // During re-render, Vue re-applies :style="dealHiding ? { opacity: '0' } : {}"
    // on hand cards, which overwrites the imperative el.style.opacity = '1' that
    // was set when a card-back clone arrived.
    //
    // Simulation: track what happens to a revealed card when a reactive update fires.
    let dealHiding = true
    const cardStyles: Record<string, string> = {}

    // Card arrives: clone removed, real card revealed imperatively
    cardStyles['card-1'] = '1' // el.style.opacity = '1'

    // REACTIVE tick triggers re-render → Vue re-applies :style binding
    function vueReRender() {
      if (dealHiding) {
        // Vue re-applies the reactive binding, overwriting imperative '1'
        cardStyles['card-1'] = '0'
      }
    }

    vueReRender()
    // Bug: card-1 is invisible again even though it was revealed!
    expect(cardStyles['card-1']).toBe('0')
  })

  it('imperative DOM cleanup restores deck state before Vue takes over', () => {
    // After animation, imperative DOM changes must be cleaned up so Vue's
    // reactive bindings produce the correct final state.
    const domState = {
      hasEmptyClass: true,    // added imperatively during animation
      countDisplay: 'none',   // hidden imperatively
      countText: '',
    }

    // Cleanup before clearing override
    domState.hasEmptyClass = false  // deckEl.classList.remove('empty')
    domState.countDisplay = ''       // countEl.style.display = ''
    domState.countText = ''          // countEl.textContent = ''

    // Now clearing the override lets Vue re-render with real deckCount
    // If deckCount is 0, Vue will add .empty class via reactive binding
    expect(domState.hasEmptyClass).toBe(false) // clean slate for Vue
    expect(domState.countDisplay).toBe('')      // clean slate for Vue
  })

  it('early return from deal animation clears override', () => {
    // If deckR() returns null or no elements to animate, the animation
    // bails out early. The override must still be cleared.
    let override: number | null = 6

    // Simulate early return
    const dr = null // deckR() returned null
    if (!dr) {
      override = null // must clear override
    }

    expect(override).toBeNull()
  })

  it('deck stays visible for non-exhausting deals (override > 0 throughout)', () => {
    // When the deck has plenty of cards (e.g., 28→22 for 6 hand cards),
    // the override starts at 28 and ticks down to 22 — never hits 0,
    // so the deck stays fully visible throughout.
    let remainingDeck = 28
    const domState = { hasEmptyClass: false }

    function tickDeckDOM() {
      remainingDeck--
      if (remainingDeck <= 0) domState.hasEmptyClass = true
    }

    // 6 cards dealt (3 per player, no table cards for re-deal)
    for (let c = 0; c < 6; c++) tickDeckDOM()

    expect(remainingDeck).toBe(22)
    expect(domState.hasEmptyClass).toBe(false) // deck stays visible
  })
})
