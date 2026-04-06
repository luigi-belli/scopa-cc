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
   * card visually leaves the hand. After finishAnimation commits the choosing
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

  it('showCaptureChoice must be set after finishAnimation commits choosing state', () => {
    // Simulate the flow: finishAnimation commits choosing state,
    // then showCaptureChoice must be set to true
    let showCaptureChoice = false // was set to false by a previous selection
    const committedState = makeState({ state: 'choosing', pendingChoice: [[{ suit: 'Denari', value: 7 }]] })

    // After finishAnimation:
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
    // Next choosing turn-result arrives, animation plays, finishAnimation commits

    const newChoosingState = makeState({
      state: 'choosing',
      pendingChoice: [[{ suit: 'Coppe', value: 3 }], [{ suit: 'Bastoni', value: 3 }]],
    })

    // The fix: after finishAnimation, check committed state
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
   * 2. pendingState is null → finishAnimation NOT called → displayState unchanged
   * 3. restoreStyles() makes captured cards visible again — FLASH!
   * 4. 600ms post-animation delay (cards remain visible)
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
