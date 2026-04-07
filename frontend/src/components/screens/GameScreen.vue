<template>
  <div class="game-board" ref="boardEl">
    <button class="exit-btn" :title="t('game.exit')" @click="handleExit">&times;</button>

    <!-- Opponent Area -->
    <div class="player-area opponent">
      <div class="player-info">
        <span class="player-name">{{ gs?.opponentName }}</span>
        <span class="score-badge">{{ gs?.opponentTotalScore }} {{ t('game.pts') }}</span>
        <span class="scopa-badge" v-if="(gs?.opponentScope ?? 0) > 0">{{ gs?.opponentScope }} {{ t('game.scope') }}</span>
      </div>
      <TurnIndicator
        :isMyTurn="false"
        :style="{ visibility: gs && !gs.isMyTurn && gs.state === 'playing' ? 'visible' : 'hidden' }"
      />
      <div class="hand-strip">
        <CapturedDeck
          :deckStyle="currentDeckStyle"
          :count="gs?.opponentCapturedCount ?? 0"
          :mine="false"
          ref="opponentCapturedRef"
        />
        <div class="hand-row" ref="opponentHandRowEl">
          <CardBack
            v-for="i in (gs?.opponentHandCount ?? 0)"
            :key="'opp-' + i"
            :deckStyle="currentDeckStyle"
            :data-card-key="'opp-' + i"
            :style="store.dealHiding ? { opacity: '0' } : {}"
          />
        </div>
      </div>
    </div>

    <!-- Table Area -->
    <div class="table-area" ref="tableAreaEl">
      <DeckVisual
        :deckStyle="currentDeckStyle"
        :count="shownDeckCount"
        ref="deckVisualRef"
      />
      <div class="table-center" :class="{ 'no-deck': shownDeckCount === 0 }" ref="tableCenterEl">
        <CardComponent
          v-for="(card, idx) in (gs?.table ?? [])"
          :key="cardKey(card, idx)"
          :data-card-key="cardKey(card, idx)"
          :card="card"
          :deckStyle="currentDeckStyle"
          :style="store.dealHidingTable ? { opacity: '0' } : {}"
        />
      </div>
    </div>

    <!-- My Area -->
    <div class="player-area me">
      <div class="hand-strip">
        <div class="hand-row" ref="myHandRowEl">
          <CardComponent
            v-for="(card, idx) in (gs?.myHand ?? [])"
            :key="cardKey(card, idx)"
            :data-card-key="'my-' + cardKey(card, idx)"
            :card="card"
            :deckStyle="currentDeckStyle"
            :playable="canPlay"
            :style="store.dealHiding ? { opacity: '0' } : {}"
            @click="canPlay ? handlePlayCard(idx) : undefined"
          />
        </div>
        <CapturedDeck
          :deckStyle="currentDeckStyle"
          :count="gs?.myCapturedCount ?? 0"
          :mine="true"
          ref="myCapturedRef"
        />
      </div>
      <TurnIndicator
        :isMyTurn="true"
        :style="{ visibility: gs && gs.isMyTurn && gs.state === 'playing' ? 'visible' : 'hidden' }"
      />
      <div class="player-info">
        <span class="player-name">{{ gs?.myName }}</span>
        <span class="score-badge">{{ gs?.myTotalScore }} {{ t('game.pts') }}</span>
        <span class="scopa-badge" v-if="(gs?.myScope ?? 0) > 0">{{ gs?.myScope }} {{ t('game.scope') }}</span>
      </div>
    </div>

    <!-- Animation Layer (fixed, above everything) -->
    <div id="animation-layer" ref="animLayerEl"></div>

    <!-- Overlays -->
    <CaptureChoiceOverlay
      v-if="showCaptureChoice && gs?.state === 'choosing' && gs.pendingChoice"
      :options="gs.pendingChoice"
      :deckStyle="currentDeckStyle"
      @select="handleSelectCapture"
    />

    <RoundEndOverlay
      v-if="showRoundEnd && lastRoundScores"
      :scores="lastRoundScores"
      :myIndex="store.myIndex"
      :myName="gs?.myName ?? ''"
      :opponentName="gs?.opponentName ?? ''"
      :myTotalScore="gs?.myTotalScore ?? 0"
      :opponentTotalScore="gs?.opponentTotalScore ?? 0"
      @nextRound="handleNextRound"
    />

    <GameOverOverlay
      v-if="showGameOver && lastRoundScores"
      :scores="lastRoundScores"
      :winner="gameOverWinner"
      :myIndex="store.myIndex"
      :myName="gs?.myName ?? ''"
      :opponentName="gs?.opponentName ?? ''"
      :myTotalScore="gs?.myTotalScore ?? 0"
      :opponentTotalScore="gs?.opponentTotalScore ?? 0"
      @backToLobby="handleBackToLobby"
    />

    <!-- Effects -->
    <ScopaFlash ref="scopaFlashRef" />
    <ConfettiCanvas ref="confettiRef" />
    <DisconnectBanner :visible="disconnected" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useGameStore } from '@/stores/gameStore'
import { useApi } from '@/composables/useApi'
import { useMercure } from '@/composables/useMercure'
import { useI18n } from '@/i18n'
import type { Card, DeckStyle } from '@/types/card'
import { cardImagePath, cardBackPath } from '@/types/card'
import type { GameState, TurnResult, RoundScores, RoundEndData, GameOverData, SweepData } from '@/types/game'
import { cardKey, sleep, computeSlotRect } from '@/animations/flipUtils'
import type { SlotGridParams } from '@/animations/flipUtils'

import CardComponent from '@/components/game/CardComponent.vue'
import CardBack from '@/components/game/CardBack.vue'
import TurnIndicator from '@/components/game/TurnIndicator.vue'
import DeckVisual from '@/components/game/DeckVisual.vue'
import CapturedDeck from '@/components/game/CapturedDeck.vue'
import CaptureChoiceOverlay from '@/components/overlays/CaptureChoiceOverlay.vue'
import RoundEndOverlay from '@/components/overlays/RoundEndOverlay.vue'
import GameOverOverlay from '@/components/overlays/GameOverOverlay.vue'
import ScopaFlash from '@/components/effects/ScopaFlash.vue'
import ConfettiCanvas from '@/components/effects/ConfettiCanvas.vue'
import DisconnectBanner from '@/components/effects/DisconnectBanner.vue'

// ─── Animation timing constants (from CLAUDE.md) ───
const SLIDE_MS    = 500
const SLIDE_EASE  = 'cubic-bezier(0.22, 0.61, 0.36, 1)'
const FLIP_TBL_MS = 500
const FLIP_HND_MS = 400
const CAP_PAUSE   = 150
const GLOW_MS     = 500
const SWEEP_MS    = 450
const SWEEP_LAG   = 100
/** Compute sweep scale: shrink only if captured deck is smaller than card */
function sweepScale(cardW: number, capR: DOMRect): number | undefined {
  const ratio = capR.width / cardW
  return ratio < 1 ? ratio : undefined
}
const DEAL_MS     = 350
const DEAL_HND_LAG = 150
const DEAL_TBL_LAG = 75
const POST_ANIM   = 600
const SAFETY_MS   = 10000

const props = defineProps<{ gameId: string }>()
const router = useRouter()
const store  = useGameStore()
const api    = useApi()
const { t }  = useI18n()

// ─── DOM refs ───
const boardEl          = ref<HTMLElement>()
const tableAreaEl      = ref<HTMLElement>()
const tableCenterEl    = ref<HTMLElement>()
const animLayerEl      = ref<HTMLElement>()
const myHandRowEl      = ref<HTMLElement>()
const opponentHandRowEl = ref<HTMLElement>()
const deckVisualRef    = ref<InstanceType<typeof DeckVisual>>()
const myCapturedRef    = ref<InstanceType<typeof CapturedDeck>>()
const opponentCapturedRef = ref<InstanceType<typeof CapturedDeck>>()
const scopaFlashRef    = ref<InstanceType<typeof ScopaFlash>>()
const confettiRef      = ref<InstanceType<typeof ConfettiCanvas>>()

const disconnected     = ref(false)
const showCaptureChoice = ref(true)
const showRoundEnd     = ref(false)
const showGameOver     = ref(false)
const lastRoundScores  = ref<[RoundScores, RoundScores] | null>(null)
const gameOverWinner   = ref(0)
/** true while inside handleTurnResult's post-animation delay — prevents
 *  incoming events from being committed before processQueue runs */
const inPostAnimDelay  = ref(false)

const gs = computed(() => store.displayState)
const currentDeckStyle = computed<DeckStyle>(() => (gs.value?.deckStyle as DeckStyle) || 'piacentine')
/** Override deck count during deal animation so the deck visual stays visible
 *  until the last card-back clone flies out. null = use live gs value. */
const dealDeckCountOverride = ref<number | null>(null)
const shownDeckCount = computed(() => dealDeckCountOverride.value ?? gs.value?.deckCount ?? 0)
const canPlay = computed(() =>
  gs.value?.isMyTurn === true && gs.value?.state === 'playing' && !store.animating && !inPostAnimDelay.value
)

// ─── DOM Helpers (read-only, never mutate Vue DOM) ───

function q(sel: string): HTMLElement | null {
  return boardEl.value?.querySelector(sel) as HTMLElement | null
}
function qAll(sel: string): HTMLElement[] {
  return Array.from(boardEl.value?.querySelectorAll(sel) ?? [])
}
function rectsOf(sel: string): Map<string, DOMRect> {
  const m = new Map<string, DOMRect>()
  qAll(sel).forEach(el => {
    const k = el.dataset.cardKey; if (k) m.set(k, el.getBoundingClientRect())
  })
  return m
}
function deckR(): DOMRect | null {
  return deckVisualRef.value?.deckEl?.getBoundingClientRect() ?? null
}
function capturedR(pi: number): DOMRect | null {
  return (pi === store.myIndex ? myCapturedRef : opponentCapturedRef)
    .value?.capturedEl?.getBoundingClientRect() ?? null
}

// ─── Table slot position helper ───

/** Compute the bounding rect for slot `index` in the 2×5 table grid.
 *  Uses the grid container's position + grid geometry. */
function getSlotRect(index: number, cardW: number, cardH: number): DOMRect | null {
  const tc = tableCenterEl.value
  if (!tc) return null

  const tcR = tc.getBoundingClientRect()
  const style = getComputedStyle(tc)
  const cols = style.gridTemplateColumns.split(' ')
  const rows = style.gridTemplateRows.split(' ')
  const colW = parseFloat(cols[0]) || cardW
  const rowH = parseFloat(rows[0]) || cardH
  const gap = parseFloat(style.gap) || 6
  const padLeft = parseFloat(style.paddingLeft) || 0

  const grid: SlotGridParams = { colW, rowH, gap, padLeft, rowCount: rows.length }
  return computeSlotRect(index, cardW, cardH, tcR, grid)
}

// ─── Animation-layer helpers ───

function aLayer(): HTMLElement { return animLayerEl.value! }
function clearLayer() { const l = animLayerEl.value; if (l) l.innerHTML = '' }

/** Track every element we imperatively style so we can undo it before commitState */
const styledEls: { el: HTMLElement; prop: string; old: string }[] = []
function setStyle(el: HTMLElement, prop: string, val: string) {
  styledEls.push({ el, prop, old: (el.style as any)[prop] })
  ;(el.style as any)[prop] = val
}
/** Undo all imperative style changes before commitState — ensures Vue's VDOM
 *  matches the actual DOM so no artifacts survive the re-render */
function restoreStyles() {
  while (styledEls.length) {
    const { el, prop, old } = styledEls.pop()!
    ;(el.style as any)[prop] = old
  }
}

// ─── Clone factories — ONLY in #animation-layer, NEVER in Vue DOM ───

function mkFace(card: Card, r: DOMRect): HTMLElement {
  const d = document.createElement('div')
  d.style.cssText = `position:fixed;z-index:51;border-radius:6px;overflow:hidden;pointer-events:none;will-change:transform;`
  applyRect(d, r)
  const i = document.createElement('img')
  i.src = cardImagePath(card, currentDeckStyle.value)
  i.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;'
  d.appendChild(i)
  return d
}
function mkBack(r: DOMRect): HTMLElement {
  const d = document.createElement('div')
  d.style.cssText = `position:fixed;z-index:52;border-radius:6px;overflow:hidden;pointer-events:none;will-change:transform;`
  applyRect(d, r)
  const i = document.createElement('img')
  i.src = cardBackPath(currentDeckStyle.value)
  i.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;'
  d.appendChild(i)
  return d
}
function applyRect(el: HTMLElement, r: DOMRect) {
  el.style.left   = `${r.left}px`
  el.style.top    = `${r.top}px`
  el.style.width  = `${r.width}px`
  el.style.height = `${r.height}px`
}

// ─── Low-level animation ───
// Uses transform for GPU-composited smooth motion instead of left/top

function flyTo(el: HTMLElement, to: DOMRect, dur: number, ease: string, scale?: number): Promise<void> {
  const fromL = parseFloat(el.style.left)
  const fromT = parseFloat(el.style.top)
  const fromW = parseFloat(el.style.width)
  const fromH = parseFloat(el.style.height)
  // scale: if provided, shrink/grow to scale × source size; if absent, keep original size
  const toW = scale != null ? scale * fromW : fromW
  const toH = scale != null ? scale * fromH : fromH
  // Centre the (possibly resized) clone on the target's centre
  const dx = to.left + (to.width - toW) / 2 - fromL
  const dy = to.top  + (to.height - toH) / 2 - fromT
  const sx = toW / fromW, sy = toH / fromH
  const a = el.animate([
    { transform: 'translate(0,0) scale(1)' },
    { transform: `translate(${dx}px,${dy}px) scale(${sx},${sy})` },
  ], { duration: dur, easing: ease, fill: 'forwards' })
  return new Promise(r => { a.onfinish = () => r() })
}

/** FLIP: given snapshot of old positions, animate elements from old→current */
function flip(sel: string, before: Map<string, DOMRect>, dur: number, ease: string): Promise<void> {
  const ps: Promise<void>[] = []
  qAll(sel).forEach(el => {
    const k = el.dataset.cardKey; if (!k) return
    const old = before.get(k); if (!old) return
    const cur = el.getBoundingClientRect()
    const dx = old.left - cur.left, dy = old.top - cur.top
    if (Math.abs(dx) < 1 && Math.abs(dy) < 1) return
    const a = el.animate(
      [{ transform: `translate(${dx}px,${dy}px)` }, { transform: 'translate(0,0)' }],
      { duration: dur, easing: ease }
    )
    ps.push(new Promise(r => { a.onfinish = () => r() }))
  })
  return Promise.all(ps).then(() => {})
}

// ════════════════════════════════════════════════════
// Mercure handlers
// ════════════════════════════════════════════════════

/** Whether the event pipeline is busy (animating, post-anim delay, or processing).
 *  All incoming Mercure events are queued while busy. */
function isBusy(): boolean {
  return store.animating || inPostAnimDelay.value
}

const { connect, disconnect: disconnectMercure } = useMercure(props.gameId, {
  onTurnResult(data: TurnResult) {
    if (isBusy()) {
      store.queueEvent({ type: 'turn-result', data }); return
    }
    handleTurnResult(data)
  },
  onGameState(data: GameState) {
    if (isBusy()) {
      // If there are already queued events, queue the game-state too so ordering
      // is preserved (prevents stash-overwrite when multiple turns are queued).
      // Otherwise, stash it for the currently-running animation's finishAnimation.
      if (store.pendingEvents.length > 0) {
        store.queueEvent({ type: 'game-state', data })
      } else {
        store.stashState(data)
      }
      return
    }
    // Re-enable capture choice overlay when entering choosing state
    if (data.state === 'choosing') showCaptureChoice.value = true
    maybeCommitOrDeal(data)
  },
  onChooseCapture() {},
  async onRoundEnd(data) {
    if (isBusy()) {
      store.queueEvent({ type: 'round-end', data }); return
    }
    await handleRoundEnd(data)
  },
  async onGameOver(data) {
    if (isBusy()) {
      store.queueEvent({ type: 'game-over', data }); return
    }
    await handleGameOver(data)
  },
  onOpponentDisconnected() { disconnected.value = true; store.clearSession() },
  async onReconnect() {
    try {
      const freshState = await api.getState(props.gameId)
      if (isBusy()) {
        store.queueEvent({ type: 'game-state', data: freshState })
      } else {
        if (freshState.state === 'choosing') showCaptureChoice.value = true
        maybeCommitOrDeal(freshState)
      }
    } catch (e) {
      console.error('Failed to re-fetch state on reconnect:', e)
    }
  },
})

function isDealState(prev: GameState | null, next: GameState): boolean {
  if (!prev) return true
  if (prev.myHand.length === 0 && next.myHand.length > 0) return true
  // Deck count only decreases when dealHands() runs — never during normal play
  if (next.deckCount < prev.deckCount) return true
  return false
}

function maybeCommitOrDeal(data: GameState) {
  if (isDealState(store.displayState, data)) runDealAnimation(data)
  else store.commitState(data)
}

/** Animate remaining table cards sweeping to the last capturer's deck,
 *  then commit the new state. Used at round-end and game-over.
 *  The sweep data (remainingCards + lastCapturer) comes from the backend event,
 *  not from displayState, because the turn animation may not have committed state. */
async function animateEndOfRoundSweep(newState: GameState, sweep?: SweepData): Promise<void> {
  if (!sweep || sweep.remainingCards.length === 0) {
    store.commitState(newState)
    return
  }

  const { remainingCards, lastCapturer } = sweep

  // First, commit an intermediate state that shows the post-play table
  // (with remaining cards visible) so Vue renders them for us to animate.
  // Preserve current display scores/counts — newState has post-scoring values
  // that would flash before the sweep completes.
  const ds = store.displayState
  const intermediateState: GameState = {
    ...newState,
    table: remainingCards,
    state: 'playing', // keep playing state so no overlay appears
    myCapturedCount: ds?.myCapturedCount ?? newState.myCapturedCount,
    opponentCapturedCount: ds?.opponentCapturedCount ?? newState.opponentCapturedCount,
    myScope: ds?.myScope ?? newState.myScope,
    opponentScope: ds?.opponentScope ?? newState.opponentScope,
    myTotalScore: ds?.myTotalScore ?? newState.myTotalScore,
    opponentTotalScore: ds?.opponentTotalScore ?? newState.opponentTotalScore,
  }
  store.commitState(intermediateState)
  await nextTick()

  store.animating = true

  try {
    // Pause before sweep so there's a visual gap after the last turn animation
    await sleep(CAP_PAUSE)

    // Glow all remaining table cards
    const glowEls: HTMLElement[] = []
    remainingCards.forEach((card, idx) => {
      const el = q(`[data-card-key="${cardKey(card, idx)}"]`)
      if (el) { el.classList.add('captured-glow'); glowEls.push(el) }
    })
    await sleep(GLOW_MS)
    glowEls.forEach(el => el.classList.remove('captured-glow'))

    // Snapshot positions then hide originals
    const sweepItems: { card: Card; rect: DOMRect }[] = []
    remainingCards.forEach((card, idx) => {
      const el = q(`[data-card-key="${cardKey(card, idx)}"]`)
      if (el) {
        sweepItems.push({ card, rect: el.getBoundingClientRect() })
        setStyle(el, 'visibility', 'hidden')
      }
    })

    // Sweep to captured deck
    const capR = capturedR(lastCapturer)
    if (capR && sweepItems.length > 0) {
      const scale = sweepScale(sweepItems[0].rect.width, capR)
      const ps: Promise<void>[] = []
      sweepItems.forEach((si, i) => {
        const cl = mkFace(si.card, si.rect)
        aLayer().appendChild(cl)
        ps.push(
          sleep(i * SWEEP_LAG).then(() =>
            flyTo(cl, capR, SWEEP_MS, 'ease-in-out', scale).then(() => {
              if (cl.parentNode) cl.remove()
            })
          )
        )
      })
      await Promise.all(ps)
    }
  } catch (e) {
    console.error('End-of-round sweep animation error:', e)
  }

  clearLayer()
  store.commitState(newState)
  await nextTick()
  restoreStyles()
  store.animating = false
}

async function handleRoundEnd(data: RoundEndData): Promise<void> {
  lastRoundScores.value = data.scores
  if (data.gameState) await animateEndOfRoundSweep(data.gameState, data.sweep)
  showRoundEnd.value = true
}

async function handleGameOver(data: GameOverData): Promise<void> {
  lastRoundScores.value = data.scores
  gameOverWinner.value = data.winner
  if (data.gameState) await animateEndOfRoundSweep(data.gameState, data.sweep)
  showGameOver.value = true
  store.clearSession()
  if (data.winner === store.myIndex) nextTick(() => confettiRef.value?.start())
}

// ─── FLIP rearrangement helpers ───

/** Snapshot positions of all cards by identity key.
 *  Table/hand cards keyed by value-suit; opponent backs keyed by opp-N. */
function snapshotByIdentity(): Map<string, DOMRect> {
  const map = new Map<string, DOMRect>()
  // Table cards — keyed by card identity (survives index shifts)
  const table = gs.value?.table ?? []
  table.forEach((card, idx) => {
    const el = q(`[data-card-key="${cardKey(card, idx)}"]`)
    if (el) map.set(`t:${card.value}-${card.suit}`, el.getBoundingClientRect())
  })
  // My hand cards — keyed by card identity
  const hand = gs.value?.myHand ?? []
  hand.forEach((card, idx) => {
    const el = q(`[data-card-key="my-${cardKey(card, idx)}"]`)
    if (el) map.set(`h:${card.value}-${card.suit}`, el.getBoundingClientRect())
  })
  // Opponent hand backs — keyed by sequential index (all face-down, no identity)
  const oppCount = gs.value?.opponentHandCount ?? 0
  for (let n = 1; n <= oppCount; n++) {
    const el = q(`[data-card-key="opp-${n}"]`)
    if (el) map.set(`o:${n}`, el.getBoundingClientRect())
  }
  return map
}

/** After commitState + nextTick, animate surviving cards from old positions to new. */
function flipRearrange(before: Map<string, DOMRect>): Promise<void> {
  const ps: Promise<void>[] = []

  // Helper: animate one element from old→new position
  function flipEl(el: HTMLElement, key: string, dur: number) {
    const oldR = before.get(key)
    if (!oldR) return
    const newR = el.getBoundingClientRect()
    const dx = oldR.left - newR.left, dy = oldR.top - newR.top
    if (Math.abs(dx) < 1 && Math.abs(dy) < 1) return
    const a = el.animate(
      [{ transform: `translate(${dx}px,${dy}px)` }, { transform: 'translate(0,0)' }],
      { duration: dur, easing: 'ease-out' }
    )
    ps.push(new Promise(r => { a.onfinish = () => r() }))
  }

  // Table cards
  const newTable = gs.value?.table ?? []
  newTable.forEach((card, idx) => {
    const el = q(`[data-card-key="${cardKey(card, idx)}"]`)
    if (el) flipEl(el, `t:${card.value}-${card.suit}`, FLIP_TBL_MS)
  })

  // My hand cards
  const newHand = gs.value?.myHand ?? []
  newHand.forEach((card, idx) => {
    const el = q(`[data-card-key="my-${cardKey(card, idx)}"]`)
    if (el) flipEl(el, `h:${card.value}-${card.suit}`, FLIP_HND_MS)
  })

  // Opponent hand backs
  const newOppCount = gs.value?.opponentHandCount ?? 0
  for (let n = 1; n <= newOppCount; n++) {
    const el = q(`[data-card-key="opp-${n}"]`)
    if (el) flipEl(el, `o:${n}`, FLIP_HND_MS)
  }

  return Promise.all(ps).then(() => {})
}

// ════════════════════════════════════════════════════
// Turn result dispatcher
// ════════════════════════════════════════════════════

async function handleTurnResult(result: TurnResult) {
  store.animating = true
  const safety = setTimeout(() => {
    restoreStyles(); clearLayer()
    if (store.pendingState) store.finishAnimation()
    else store.animating = false
    inPostAnimDelay.value = false
    processQueue()
  }, SAFETY_MS)

  try {
    if (result.type === 'place')   await animPlace(result)
    else if (result.type === 'capture') await animCapture(result)
    else if (result.type === 'choosing') await animPlace(result)
  } catch (e) { console.error('Animation error:', e) }

  clearTimeout(safety)

  // 1. Clear animation layer (clones no longer needed)
  clearLayer()

  // 2. When the round ends, the server publishes turn-result + round-end (no
  //    separate game-state event — see publishTurnOutcome). So pendingState is null.
  //    If we follow the normal path, restoreStyles() would flash the captured cards
  //    back to visible for 600ms+ before the sweep starts.
  //    Fix: skip restore/FLIP/delay and go straight to processQueue, which runs
  //    animateEndOfRoundSweep (it commits its own intermediate state via commitState).
  //    Keep animating=true so any straggler events stay queued through the sweep.
  if (!store.pendingState && store.pendingEvents.length > 0 &&
      (store.pendingEvents[0].type === 'round-end' || store.pendingEvents[0].type === 'game-over')) {
    styledEls.length = 0  // discard tracked styles — Vue will replace all elements
    processQueue()
    return
  }

  // 3. Snapshot card positions NOW — elements still in old state.
  //    visibility:hidden elements still have valid layout for getBoundingClientRect().
  //    Do NOT restoreStyles() here — that would flash hidden cards visible for one
  //    frame before Vue removes them at commitState.
  const beforeRects = snapshotByIdentity()

  // 4. Check if pending state is a re-deal.
  //    Can't use isDealState here because displayState hasn't committed the
  //    just-played card yet (hand still shows 1 when it should be 0).
  //    Instead compare deckCount: normal plays never change it, only dealHands does.
  const pending = store.pendingState
  const isRedeal = !!(pending && store.displayState && pending.deckCount < store.displayState.deckCount)

  // Pre-set dealHiding so new hand cards render with opacity:0 (no flash).
  // Freeze deck visual at pre-deal count so it doesn't vanish when finishAnimation
  // commits the post-deal state (where deckCount may be 0).
  if (isRedeal) {
    store.dealHiding = true
    dealDeckCountOverride.value = store.displayState!.deckCount
  }

  // 5. *** HARD INVARIANT: commitState ONLY here, after ALL animation ***
  if (store.pendingState) store.finishAnimation()
  else store.animating = false

  // Re-enable capture choice overlay if committed state is choosing
  // (needed because showCaptureChoice is set to false on selection,
  // and the game-state was stashed — not routed through the Mercure handler)
  if (gs.value?.state === 'choosing') showCaptureChoice.value = true

  // Keep animating flag set through FLIP + deal so incoming events stay queued
  if (isRedeal) store.animating = true

  // 5. FLIP: animate surviving cards from old positions to new
  await nextTick()
  // Now safe to restore styles — Vue has already removed animated-away elements,
  // so restoring visibility on them is a no-op. Surviving elements get cleaned up.
  restoreStyles()
  await flipRearrange(beforeRects)

  // 6. If re-deal detected, run deal animation instead of normal post-anim flow
  if (isRedeal && pending) {
    await runDealAnimation(pending)
    return  // deal animation calls processQueue when done
  }

  // Post-animation delay: keep queueing events so nothing commits during gap
  inPostAnimDelay.value = true
  await sleep(POST_ANIM)
  inPostAnimDelay.value = false

  // Commit any game-state that was stashed during the post-anim delay
  if (store.pendingState) {
    const ps = store.pendingState
    store.pendingState = null
    if ((ps as GameState).state === 'choosing') showCaptureChoice.value = true
    if (isDealState(store.displayState, ps)) {
      runDealAnimation(ps)
      return  // deal animation calls processQueue when done
    }
    store.commitState(ps)
  }

  processQueue()
}

// ════════════════════════════════════════════════════
// PLACE ANIMATION
//
// Hard rules enforced:
//  - Vue-managed DOM is NEVER modified (no appendChild/remove on .table-center)
//  - Only visibility:hidden via tracked setStyle (undone before commitState)
//  - All moving elements are clones in #animation-layer
//  - commitState is NOT called here (caller does it)
//
// 1. Hide source card in hand (visibility:hidden, tracked)
// 2. Clone in animation layer at source position
// 3. Fly clone → centre of table area (500ms)
// 4. Return. Caller restores styles, clears layer, commits state.
// ════════════════════════════════════════════════════

async function animPlace(result: TurnResult) {
  const isMe = result.playerIndex === store.myIndex
  const card = result.card

  // 1. Find & hide source
  let srcR: DOMRect | null = null
  if (isMe) {
    const hand = gs.value?.myHand ?? []
    const idx = hand.findIndex(c => c.suit === card.suit && c.value === card.value)
    if (idx >= 0) {
      const el = q(`[data-card-key="my-${cardKey(card, idx)}"]`)
      if (el) { srcR = el.getBoundingClientRect(); setStyle(el, 'visibility', 'hidden') }
    }
  } else {
    const backs = qAll('.player-area.opponent .hand-row .card-back')
    if (backs.length) {
      const el = backs[backs.length - 1]
      srcR = el.getBoundingClientRect()
      setStyle(el, 'visibility', 'hidden')
    }
  }
  if (!srcR) return

  // 2. Destination: the specific empty slot where the card will land.
  //    The new card will be appended at index = current table length.
  //    Compute the grid cell position for that slot.
  const dest = getSlotRect(gs.value?.table.length ?? 0, srcR.width, srcR.height)
  if (!dest) return

  // 3. Clone flies from hand → target slot
  const clone = isMe ? mkFace(card, srcR) : mkBack(srcR)
  aLayer().appendChild(clone)
  if (!isMe) {
    setTimeout(() => {
      const img = clone.querySelector('img')
      if (img) img.src = cardImagePath(card, currentDeckStyle.value)
    }, SLIDE_MS * 0.4)
  }
  await flyTo(clone, dest, SLIDE_MS, SLIDE_EASE)
}

// ════════════════════════════════════════════════════
// CAPTURE ANIMATION
//
// Hard rules: same as place — no Vue DOM mutation, tracked styles only.
//
// 1. Hide source card (visibility:hidden, tracked)
// 2. Clone slides from hand → first captured card on table (500ms)
// 3. Pause 150ms
// 4. Glow captured table cards via CSS class (500ms), then remove class
// 5. Hide captured table cards (visibility:hidden, tracked)
// 6. Sweep clones from captured positions → captured deck (450ms, stagger)
// 7. Scopa flash if applicable
// 8. Return. Caller restores styles, clears layer, commits state.
// ════════════════════════════════════════════════════

async function animCapture(result: TurnResult) {
  const isMe = result.playerIndex === store.myIndex
  const card = result.card
  const captured = result.captured
  const table = gs.value?.table ?? []

  // 1. Find & hide source card in hand.
  //    If not found (e.g. after capture-choice dialog — card already left hand),
  //    skip the hand→table slide and go straight to glow+sweep.
  let srcR: DOMRect | null = null
  const fromChoosing = gs.value?.state === 'choosing'
  if (!fromChoosing) {
    if (isMe) {
      const hand = gs.value?.myHand ?? []
      const idx = hand.findIndex(c => c.suit === card.suit && c.value === card.value)
      if (idx >= 0) {
        const el = q(`[data-card-key="my-${cardKey(card, idx)}"]`)
        if (el) { srcR = el.getBoundingClientRect(); setStyle(el, 'visibility', 'hidden') }
      }
    } else {
      const backs = qAll('.player-area.opponent .hand-row .card-back')
      if (backs.length) {
        const el = backs[backs.length - 1]
        srcR = el.getBoundingClientRect()
        setStyle(el, 'visibility', 'hidden')
      }
    }
  }

  // Landing target (first captured card on table)
  let landR: DOMRect | null = null
  for (const cc of captured) {
    const idx = table.findIndex(t => t.suit === cc.suit && t.value === cc.value)
    if (idx >= 0) {
      const el = q(`[data-card-key="${cardKey(cc, idx)}"]`)
      if (el) { landR = el.getBoundingClientRect(); break }
    }
  }

  // 2. If we have a source (card was in hand), clone flies hand → landing.
  //    Keep the clone alive through pause+glow so the played card stays visible
  //    on top of the captured card until the sweep starts.
  let playClone: HTMLElement | null = null
  let playCloneR: DOMRect | null = null
  if (srcR) {
    playClone = isMe ? mkFace(card, srcR) : mkBack(srcR)
    aLayer().appendChild(playClone)
    if (!isMe) {
      setTimeout(() => {
        const img = playClone!.querySelector('img')
        if (img) img.src = cardImagePath(card, currentDeckStyle.value)
      }, SLIDE_MS * 0.4)
    }
    if (landR) await flyTo(playClone, landR, SLIDE_MS, SLIDE_EASE)
    else await sleep(SLIDE_MS)
    playCloneR = playClone.getBoundingClientRect()
    // Do NOT remove playClone here — it stays visible during pause+glow
  }

  // 3. Pause
  await sleep(CAP_PAUSE)

  // 4. Glow
  const glowEls: HTMLElement[] = []
  for (const cc of captured) {
    const idx = table.findIndex(t => t.suit === cc.suit && t.value === cc.value)
    if (idx >= 0) {
      const el = q(`[data-card-key="${cardKey(cc, idx)}"]`)
      if (el) { el.classList.add('captured-glow'); glowEls.push(el) }
    }
  }
  await sleep(GLOW_MS)
  glowEls.forEach(el => el.classList.remove('captured-glow'))

  // 5. Remove play clone now — sweep clones are about to be created at the same
  //    position, so there's no visible gap.
  if (playClone?.parentNode) playClone.remove()

  // Snapshot positions of cards to sweep, then hide originals
  const sweepItems: { card: Card; rect: DOMRect }[] = []
  // Include played card — use its landing position (or first captured card position as fallback)
  if (playCloneR) {
    sweepItems.push({ card, rect: playCloneR })
  } else if (landR) {
    sweepItems.push({ card, rect: landR })
  }
  for (const cc of captured) {
    const idx = table.findIndex(t => t.suit === cc.suit && t.value === cc.value)
    if (idx >= 0) {
      const el = q(`[data-card-key="${cardKey(cc, idx)}"]`)
      if (el) {
        sweepItems.push({ card: cc, rect: el.getBoundingClientRect() })
        setStyle(el, 'visibility', 'hidden')
      }
    }
  }

  // 6. Sweep
  const capR = capturedR(result.playerIndex)
  if (capR && sweepItems.length > 0) {
    const scale = sweepScale(sweepItems[0].rect.width, capR)
    const ps: Promise<void>[] = []
    sweepItems.forEach((si, i) => {
      const cl = mkFace(si.card, si.rect)
      aLayer().appendChild(cl)
      ps.push(
        sleep(i * SWEEP_LAG).then(() =>
          flyTo(cl, capR, SWEEP_MS, 'ease-in-out', scale).then(() => {
            if (cl.parentNode) cl.remove()
          })
        )
      )
    })
    await Promise.all(ps)
  }

  // 7. Scopa: flash + mark the capturing card in the captured deck
  if (result.scopa) {
    scopaFlashRef.value?.show()
    const capRef = result.playerIndex === store.myIndex ? myCapturedRef : opponentCapturedRef
    capRef.value?.addScopa(card)
  }
}

// ════════════════════════════════════════════════════
// DEAL ANIMATION
//
// This is the ONE controlled exception to "commitState after animation":
// commitState is called at the START, but with dealHiding flags that render
// all new cards with opacity:0. Card-back clones then animate from the deck
// to each card position. On arrival, the real card is revealed (opacity:1).
//
// Triggers:
// - First game load (no previous state)
// - Re-deal when both hands empty mid-round
// - New round after nextRound
// ════════════════════════════════════════════════════

async function runDealAnimation(newState: GameState) {
  store.animating = true

  const prevTblLen = store.displayState?.table.length ?? 0
  // New round = table has more cards than before (or first load when displayState is null)
  const isNewRound = !store.displayState || newState.table.length > prevTblLen

  // Clear scopa markers on new round
  if (isNewRound) {
    myCapturedRef.value?.clearScopa()
    opponentCapturedRef.value?.clearScopa()
  }

  // 1. Set hiding flags so Vue renders cards with opacity:0
  store.dealHiding = true
  store.dealHidingTable = isNewRound
  // Freeze deck visual at pre-deal count so it doesn't vanish before animation.
  // Compute pre-deal count by adding back the cards that were dealt
  // (server's deckCount is already post-deal). If the override was already set
  // (re-deal path sets it before finishAnimation to prevent flicker), keep it.
  const dealtCardCount = (isNewRound ? newState.table.length : 0)
    + newState.myHand.length + newState.opponentHandCount
  const preDealDeckCount = dealDeckCountOverride.value
    ?? (newState.deckCount + dealtCardCount)
  dealDeckCountOverride.value = preDealDeckCount
  // Commit state — cards render with opacity:0 via reactive :style bindings
  store.commitState(newState)
  await nextTick()
  // Wait for browser to lay out the elements (needed for getBoundingClientRect)
  await new Promise<void>(r => requestAnimationFrame(() => r()))

  // 2. Collect all elements to animate
  const tblEls = isNewRound ? qAll('.table-center [data-card-key]') : []
  const myEls  = qAll('.player-area.me .hand-row [data-card-key^="my-"]')
  const oppEls = qAll('.player-area.opponent .hand-row [data-card-key^="opp-"]')
  const allEls = [...tblEls, ...myEls, ...oppEls]

  // 3. Deck position
  const dr = deckR()
  if (!dr || dr.width === 0 || allEls.length === 0) {
    store.dealHiding = false; store.dealHidingTable = false
    dealDeckCountOverride.value = null
    store.animating = false
    processQueue()
    return
  }

  const deals: Promise<void>[] = []
  let i = 0
  // Deal grow scale: when deck visual is smaller than card slots (mobile),
  // clones must grow during flight. ratio > 1 means grow, ~1 means no scale needed.
  const firstTarget = allEls[0]?.getBoundingClientRect()
  const dealScale = firstTarget && dr.width > 0 && Math.abs(firstTarget.width / dr.width - 1) > 0.01
    ? firstTarget.width / dr.width : undefined

  /** Imperatively update deck count DOM as each card-back departs.
   *  Uses direct DOM manipulation instead of reactive updates to avoid
   *  Vue re-renders that would reset imperative opacity:1 on revealed cards. */
  let remainingDeck = preDealDeckCount
  function tickDeckDOM() {
    remainingDeck--
    const deckEl = deckVisualRef.value?.deckEl
    if (!deckEl) return
    if (remainingDeck <= 0) {
      deckEl.classList.add('empty')
      const countEl = deckEl.querySelector('.deck-count')
      if (countEl) (countEl as HTMLElement).style.display = 'none'
    } else {
      const countEl = deckEl.querySelector('.deck-count')
      if (countEl) countEl.textContent = String(remainingDeck)
    }
  }

  // Table cards (new round only)
  tblEls.forEach(el => {
    const target = el.getBoundingClientRect()
    const clone = mkBack(dr)
    aLayer().appendChild(clone)
    const d = i++ * DEAL_TBL_LAG
    deals.push(sleep(d).then(() => {
      tickDeckDOM()
      return flyTo(clone, target, DEAL_MS, SLIDE_EASE, dealScale).then(() => {
        el.style.opacity = '1'   // override reactive opacity:0
        if (clone.parentNode) clone.remove()
      })
    }))
  })

  // Hand cards: interleave opponent and player cards (one at a time, alternating)
  const maxHand = Math.max(myEls.length, oppEls.length)
  for (let h = 0; h < maxHand; h++) {
    // Opponent card first, then my card (traditional dealing order: non-self first)
    for (const el of [oppEls[h], myEls[h]]) {
      if (!el) continue
      const target = el.getBoundingClientRect()
      const clone = mkBack(dr)
      aLayer().appendChild(clone)
      const d = i++ * DEAL_HND_LAG
      deals.push(sleep(d).then(() => {
        tickDeckDOM()
        return flyTo(clone, target, DEAL_MS, SLIDE_EASE, dealScale).then(() => {
          el.style.opacity = '1'
          if (clone.parentNode) clone.remove()
        })
      }))
    }
  }

  await Promise.all(deals)
  clearLayer()
  // Restore imperative deck DOM changes before Vue takes over
  const deckEl = deckVisualRef.value?.deckEl
  if (deckEl) {
    deckEl.classList.remove('empty')
    const countEl = deckEl.querySelector('.deck-count')
    if (countEl) { (countEl as HTMLElement).style.display = ''; countEl.textContent = '' }
  }
  // Clear deck count override — let reactive value take over
  dealDeckCountOverride.value = null
  // Clear hiding flags — Vue will remove :style opacity bindings
  store.dealHiding = false
  store.dealHidingTable = false
  // Remove imperative opacity overrides now that reactive bindings are clear
  allEls.forEach(el => { el.style.opacity = '' })
  store.animating = false
  processQueue()
}

// ════════════════════════════════════════════════════
// Event queue
// ════════════════════════════════════════════════════

async function processQueue() {
  const ev = store.shiftEvent()
  if (!ev) return

  if (ev.type === 'turn-result') {
    // If the next queued event is a game-state, pre-stash it so the animation's
    // finishAnimation() can commit it (preserves correct per-turn state ordering).
    if (store.pendingEvents.length > 0 && store.pendingEvents[0].type === 'game-state') {
      const gsEv = store.shiftEvent()!
      if (gsEv.type === 'game-state') store.stashState(gsEv.data)
    }
    handleTurnResult(ev.data)
  }
  else if (ev.type === 'game-state') {
    if (ev.data.state === 'choosing') showCaptureChoice.value = true
    maybeCommitOrDeal(ev.data)
    // Chain: process next event unless a deal animation started
    if (!store.animating) processQueue()
  }
  else if (ev.type === 'round-end') {
    await handleRoundEnd(ev.data)
    // Round-end shows overlay; drain remaining events (unlikely, but safe)
    processQueue()
  }
  else if (ev.type === 'game-over') {
    await handleGameOver(ev.data)
  }
}

// ════════════════════════════════════════════════════
// API actions
// ════════════════════════════════════════════════════

async function handlePlayCard(cardIndex: number) {
  if (!canPlay.value) return
  try { await api.playCard(props.gameId, cardIndex) }
  catch (e: unknown) { console.error('Play card error:', e) }
}
async function handleSelectCapture(optionIndex: number) {
  // Dismiss the overlay BEFORE the API call so the animation is visible
  showCaptureChoice.value = false
  try { await api.selectCapture(props.gameId, optionIndex) }
  catch (e: unknown) { console.error('Select capture error:', e) }
}
async function handleNextRound() {
  showRoundEnd.value = false; lastRoundScores.value = null
  try { await api.nextRound(props.gameId) }
  catch (e: unknown) { console.error('Next round error:', e) }
}
function handleBackToLobby() {
  api.leaveGame(props.gameId).catch(() => {})
  store.$reset()
  router.push({ name: 'lobby' })
}

function handleExit() {
  if (!confirm(t('game.exitConfirm'))) return
  api.leaveGame(props.gameId).catch(() => {})
  store.$reset()
  router.push({ name: 'lobby' })
}

// ════════════════════════════════════════════════════
// Lifecycle
// ════════════════════════════════════════════════════

let heartbeatInterval: ReturnType<typeof setInterval>

onMounted(async () => {
  // Restore session from localStorage if store is empty (e.g. page reload)
  if (!store.playerToken) {
    store.restoreSession()
  }

  // Get game state: pendingState (from LobbyScreen), displayState (page reload), or API fetch
  let state: GameState
  if (store.pendingState) {
    state = store.pendingState
    store.pendingState = null
  } else if (store.displayState) {
    state = store.displayState
  } else {
    try {
      state = await api.getState(props.gameId)
      // Sync myIndex from server state (in case localStorage was stale)
      store.myIndex = state.myIndex
    } catch (e) {
      console.error('Failed to load game state:', e)
      store.$reset()
      router.push({ name: 'lobby' }); return
    }
  }
  // Always run deal animation on mount (displayState is null → no flash)
  runDealAnimation(state)
  connect(store.myIndex)
  heartbeatInterval = setInterval(() => { api.heartbeat(props.gameId).catch(() => {}) }, 10000)
})

onUnmounted(() => {
  disconnectMercure()
  clearInterval(heartbeatInterval)
})
</script>
