<template>
  <div class="game-board" ref="boardEl">
    <button class="exit-btn" :title="t('game.exit')" @click="handleExit">&times;</button>

    <!-- Opponent Area -->
    <div class="player-area opponent">
      <div class="player-info">
        <span class="player-name">{{ gs?.opponentName }}</span>
        <span class="score-badge">{{ opponentScoreDisplay }} {{ t('game.pts') }}</span>
        <span class="scopa-badge" v-if="isScopa && (gs?.opponentScope ?? 0) > 0">{{ gs?.opponentScope }} {{ gs?.opponentScope === 1 ? t('game.scopa') : t('game.scope') }}</span>
      </div>
      <TurnIndicator
        :isMyTurn="false"
        :style="{ visibility: gs && !gs.isMyTurn && gs.state === 'playing' ? 'visible' : 'hidden' }"
      />
      <div class="hand-strip" :class="{ 'tressette-hand': isTressette }">
        <CapturedDeck
          :deckStyle="currentDeckStyle"
          :count="gs?.opponentCapturedCount ?? 0"
          :mine="false"
          :scopaCards="gs?.opponentScopaCards ?? []"
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
    <div class="table-area" :class="{ 'tressette-table-area': isTressette }" ref="tableAreaEl">
      <CardComponent
        v-if="isBriscola && gs?.briscolaCard && shownDeckCount > 0"
        :card="gs.briscolaCard"
        :deckStyle="currentDeckStyle"
        class="briscola-trump-card"
        :style="store.dealHidingBriscola ? { opacity: '0' } : {}"
      />
      <DeckVisual
        :deckStyle="currentDeckStyle"
        :count="shownDeckCount"
        ref="deckVisualRef"
      />
      <div class="table-center" :class="{ 'briscola-table': isBriscola, 'tressette-table': isTressette }" ref="tableCenterEl">
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
      <div class="hand-strip" :class="{ 'tressette-hand': isTressette }">
        <div class="hand-row" ref="myHandRowEl">
          <CardComponent
            v-for="(card, idx) in (gs?.myHand ?? [])"
            :key="cardKey(card, idx)"
            :data-card-key="'my-' + cardKey(card, idx)"
            :card="card"
            :deckStyle="currentDeckStyle"
            :playable="canPlay && (!tressettePlayableIndices || tressettePlayableIndices.has(idx))"
            :class="{ lifted: playedCardIdx === idx, nudge: nudging }"
            :style="store.dealHiding ? { opacity: '0' } : {}"
            @click="canPlay && (!tressettePlayableIndices || tressettePlayableIndices.has(idx)) ? handlePlayCard(idx) : undefined"
          />
        </div>
        <CapturedDeck
          :deckStyle="currentDeckStyle"
          :count="gs?.myCapturedCount ?? 0"
          :mine="true"
          :scopaCards="gs?.myScopaCards ?? []"
          ref="myCapturedRef"
        />
      </div>
      <TurnIndicator
        :isMyTurn="true"
        :style="{ visibility: gs && gs.isMyTurn && gs.state === 'playing' ? 'visible' : 'hidden' }"
      />
      <div class="player-info">
        <span class="player-name">{{ gs?.myName }}</span>
        <span class="score-badge">{{ myScoreDisplay }} {{ t('game.pts') }}</span>
        <span class="scopa-badge" v-if="isScopa && (gs?.myScope ?? 0) > 0">{{ gs?.myScope }} {{ gs?.myScope === 1 ? t('game.scopa') : t('game.scope') }}</span>
      </div>
    </div>

    <!-- Animation Layer (fixed, above everything) -->
    <div id="animation-layer" ref="animLayerEl"></div>

    <!-- Overlays -->
    <CaptureChoiceOverlay
      v-if="isScopa && showCaptureChoice && captureChoiceOptions"
      :options="captureChoiceOptions"
      :deckStyle="currentDeckStyle"
      @select="handleSelectCapture"
    />

    <RoundEndOverlay
      v-if="isScopa && showRoundEnd && lastRoundScores"
      :scores="lastRoundScores"
      :myIndex="store.myIndex"
      :myName="gs?.myName ?? ''"
      :opponentName="gs?.opponentName ?? ''"
      :myTotalScore="gs?.myTotalScore ?? 0"
      :opponentTotalScore="gs?.opponentTotalScore ?? 0"
      :deckStyle="currentDeckStyle"
      @nextRound="handleNextRound"
    />

    <GameOverOverlay
      v-if="showGameOver && (lastRoundScores || isTrickGame)"
      :scores="lastRoundScores"
      :gameType="gs?.gameType ?? 'scopa'"
      :winner="gameOverWinner"
      :myIndex="store.myIndex"
      :myName="gs?.myName ?? ''"
      :opponentName="gs?.opponentName ?? ''"
      :myTotalScore="gs?.myTotalScore ?? 0"
      :opponentTotalScore="gs?.opponentTotalScore ?? 0"
      :deckStyle="currentDeckStyle"
      :capturedCards="gameOverCapturedCards"
      @backToLobby="handleBackToLobby"
    />

    <!-- Effects -->
    <ScopaFlash ref="scopaFlashRef" />
    <ConfettiCanvas ref="confettiRef" />
    <DisconnectBanner :visible="disconnected" />
    <ReconnectBanner :visible="reconnecting && !disconnected" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useGameStore } from '@/stores/gameStore'
import { useApi } from '@/composables/useApi'
import { useMercure, setMercureCookie } from '@/composables/useMercure'
import { useI18n } from '@/i18n'
import type { Card, DeckStyle } from '@/types/card'
import { cardImagePath, cardBackPath, formatTressetteScore } from '@/types/card'
import type { GameState, TurnResult, RoundScores, RoundEndData, GameOverData, SweepData } from '@/types/game'
import { cardKey, sleep, computeSlotRect, computeFlyToDelta } from '@/animations/flipUtils'
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
import ReconnectBanner from '@/components/effects/ReconnectBanner.vue'

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
const DEAL_FLIP_MS = 300
const DRAW_REVEAL_MS = 800  // Tressette: pause to show drawn card face before proceeding
const POST_ANIM   = 200
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
/** true while the SSE connection is down or an API action failed — shows reconnect banner */
const reconnecting     = ref(false)
const showCaptureChoice = ref(true)
const showRoundEnd     = ref(false)
const showGameOver     = ref(false)
const lastRoundScores  = ref<[RoundScores, RoundScores] | null>(null)
/** Backup of lastRoundScores for retry on nextRound API failure */
let lastRoundScoresBackup: [RoundScores, RoundScores] | null = null
const gameOverWinner   = ref(0)
const gameOverCapturedCards = ref<[Card[], Card[]] | null>(null)
/** true while inside handleTurnResult's post-animation delay — prevents
 *  incoming events from being committed before processQueue runs */
const inPostAnimDelay  = ref(false)
/** true while a playCard API call is in flight — prevents duplicate requests */
const playInFlight     = ref(false)
/** index of the card just clicked — keeps it visually lifted until animation starts */
const playedCardIdx    = ref<number | null>(null)
/** True when a choosing turn-result saved the card's hand origin.
 *  animCapture reads the hand row rect fresh to avoid stale coordinates
 *  after viewport changes (rotation, resize) during the overlay. */
const choosingFromHand = ref(false)
/** Capture options from the choosing turn-result, used to show the overlay
 *  without committing the choosing state (so the card stays in the hand). */
const choosingOptions  = ref<Card[][] | null>(null)
/** Safety timer: releases the animating lock if the player doesn't select
 *  within 30s (e.g. tab backgrounded, network issues). */
let choosingSafety: ReturnType<typeof setTimeout> | null = null

const gs = computed(() => store.displayState)
const currentDeckStyle = computed<DeckStyle>(() => (gs.value?.deckStyle as DeckStyle) || 'piacentine')
const isBriscola = computed(() => gs.value?.gameType === 'briscola')
const isTressette = computed(() => gs.value?.gameType === 'tressette')
const isTrickGame = computed(() => isBriscola.value || isTressette.value)
const isScopa = computed(() => !isTrickGame.value)
/** Resolved capture options for the overlay: from the choosing turn-result (primary)
 *  or from committed game state (reconnection fallback). null when not choosing. */
const captureChoiceOptions = computed<Card[][] | null>(() =>
  choosingOptions.value ?? (gs.value?.state === 'choosing' ? gs.value.pendingChoice : null)
)
const myScoreDisplay = computed(() =>
  isTressette.value ? formatTressetteScore(gs.value?.myTotalScore ?? 0) : String(gs.value?.myTotalScore ?? 0),
)
const opponentScoreDisplay = computed(() =>
  isTressette.value ? formatTressetteScore(gs.value?.opponentTotalScore ?? 0) : String(gs.value?.opponentTotalScore ?? 0),
)
/** Override deck count during deal animation so the deck visual stays visible
 *  until the last card-back clone flies out. null = use live gs value. */
const dealDeckCountOverride = ref<number | null>(null)
const shownDeckCount = computed(() => dealDeckCountOverride.value ?? gs.value?.deckCount ?? 0)
const canPlay = computed(() =>
  gs.value?.isMyTurn === true && gs.value?.state === 'playing' && !store.animating && !playInFlight.value
)

/** In Tressette, follower must always follow suit when possible.
 *  Returns null if all cards are playable, or a Set of playable hand indices. */
const tressettePlayableIndices = computed<Set<number> | null>(() => {
  if (!isTressette.value || !gs.value) return null
  const { table, myHand } = gs.value
  // Only applies when leader has played (we are the follower)
  if (table.length === 0) return null
  const ledSuit = table[0].suit
  const matching = myHand.reduce<number[]>((acc, c, i) => { if (c.suit === ledSuit) acc.push(i); return acc }, [])
  // If we have matching cards, only those are playable; otherwise all are playable
  return matching.length > 0 ? new Set(matching) : null
})

// ─── Turn nudge: pulse hand cards after 5s of inactivity, repeat every 5s ───
const nudging = ref(false)
let nudgeTimer: ReturnType<typeof setTimeout> | null = null
function clearNudge() {
  if (nudgeTimer) { clearTimeout(nudgeTimer); nudgeTimer = null }
  nudging.value = false
}
function scheduleNudge() {
  nudgeTimer = setTimeout(() => {
    nudgeTimer = null
    // Remove class, wait for a real paint, then re-add to guarantee animation restart
    nudging.value = false
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        if (!canPlay.value) return       // guard: turn may have ended
        nudging.value = true
        scheduleNudge()                  // chain next nudge
      })
    })
  }, 5000)
}
watch(canPlay, (can) => {
  clearNudge()
  if (can) {
    scheduleNudge()
  }
}, { immediate: true })

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

/** Compute the bounding rect for slot `index` in the table grid.
 *  Reads grid geometry from computed style (works for both Scopa 2×5 and Briscola 1×1). */
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

  const grid: SlotGridParams = { colW, rowH, gap, padLeft, rowCount: rows.length, colCount: cols.length }
  return computeSlotRect(index, cardW, cardH, tcR, grid)
}

/** Return the table grid cell size (slot dimensions, independent of source card size) */
function getTableSlotSize(): { w: number; h: number } | null {
  const tc = tableCenterEl.value
  if (!tc) return null
  const style = getComputedStyle(tc)
  const cols = style.gridTemplateColumns.split(' ')
  const rows = style.gridTemplateRows.split(' ')
  return { w: parseFloat(cols[0]) || 75, h: parseFloat(rows[0]) || 133 }
}

// ─── Animation-layer helpers ───

function aLayer(): HTMLElement { return animLayerEl.value! }
function clearLayer() { const l = animLayerEl.value; if (l) l.replaceChildren() }

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

/** Pick the opponent card-back element at the correct hand slot.
 *  Falls back to the last element when cardIndex is missing (old server). */
function getOpponentCardEl(result: TurnResult): HTMLElement | null {
  const backs = qAll('.player-area.opponent .hand-row .card-back')
  if (!backs.length) return null
  const idx = result.cardIndex != null && result.cardIndex < backs.length
    ? result.cardIndex
    : backs.length - 1
  return backs[idx]
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
/** Two-sided card clone. Default: starts showing back, add 'flipped' to reveal face.
 *  With startFaceUp=true: starts showing face, call flipToBack() to flip to back. */
function mkFlippable(card: Card, r: DOMRect, startFaceUp = false): HTMLElement {
  const d = document.createElement('div')
  d.className = startFaceUp ? 'card-flip flipped' : 'card-flip'
  d.style.cssText = `position:fixed;z-index:52;pointer-events:none;will-change:transform;`
  applyRect(d, r)
  const inner = document.createElement('div')
  inner.className = 'card-flip-inner'
  // Front face (hidden until flipped)
  const front = document.createElement('div')
  front.className = 'card-flip-front'
  const fi = document.createElement('img')
  fi.src = cardImagePath(card, currentDeckStyle.value)
  front.appendChild(fi)
  // Back face (visible initially, unless startFaceUp)
  const back = document.createElement('div')
  back.className = 'card-flip-back'
  const bi = document.createElement('img')
  bi.src = cardBackPath(currentDeckStyle.value)
  back.appendChild(bi)
  inner.appendChild(front)
  inner.appendChild(back)
  d.appendChild(inner)
  return d
}
/** Trigger back→face flip */
function flipCard(el: HTMLElement) { el.classList.add('flipped') }
/** Trigger face→back flip */
function flipToBack(el: HTMLElement) { el.classList.remove('flipped') }
function applyRect(el: HTMLElement, r: DOMRect) {
  el.style.left   = `${r.left}px`
  el.style.top    = `${r.top}px`
  el.style.width  = `${r.width}px`
  el.style.height = `${r.height}px`
}

/** Sweep face-up cards to a captured deck with staggered face-to-back flip.
 *  Each card clone starts face-up, flips to back at 40% of the sweep, then flies
 *  to the captured-deck rect and is removed on arrival. */
function sweepToCaptured(
  items: { card: Card; rect: DOMRect }[],
  capR: DOMRect,
  scale: number | undefined,
  topIndex?: number,
): Promise<void> {
  const ps: Promise<void>[] = []
  items.forEach((si, i) => {
    const cl = mkFlippable(si.card, si.rect, true)
    if (i === topIndex) cl.style.zIndex = '53'
    aLayer().appendChild(cl)
    ps.push(
      sleep(i * SWEEP_LAG).then(() => {
        setTimeout(() => flipToBack(cl), SWEEP_MS * 0.4)
        return flyTo(cl, capR, SWEEP_MS, 'ease-in-out', scale).then(() => {
          if (cl.parentNode) cl.remove()
        })
      })
    )
  })
  return Promise.all(ps).then(() => {})
}

// ─── Low-level animation ───
// Uses transform for GPU-composited smooth motion instead of left/top

function flyTo(el: HTMLElement, to: DOMRect, dur: number, ease: string, scale?: number): Promise<void> {
  const fromL = parseFloat(el.style.left)
  const fromT = parseFloat(el.style.top)
  const fromW = parseFloat(el.style.width)
  const fromH = parseFloat(el.style.height)
  const { dx, dy, sx, sy } = computeFlyToDelta(fromL, fromT, fromW, fromH, to, scale)
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

const { connected: mercureConnected, connect, disconnect: disconnectMercure } = useMercure(props.gameId, {
  onTurnResult(data: TurnResult) {
    reconnecting.value = false
    if (isBusy()) {
      store.queueEvent({ type: 'turn-result', data }); return
    }
    handleTurnResult(data)
  },
  onGameState(data: GameState) {
    reconnecting.value = false
    if (isBusy()) {
      // If there are already queued events, queue the game-state too so ordering
      // is preserved (prevents stash-overwrite when multiple turns are queued).
      // Otherwise, stash it for the currently-running animation's commit.
      if (store.pendingEvents.length > 0) {
        store.queueEvent({ type: 'game-state', data })
      } else {
        store.stashState(data)
      }
      return
    }
    // When the round-end overlay is showing and the game has moved past it
    // (opponent clicked "Next Round" first), stash the new state but keep the
    // overlay visible so this player can review scores at their own pace.
    // handleNextRound will commit this state when the player clicks the button.
    if (showRoundEnd.value && data.state !== 'round-end' && data.state !== 'game-over') {
      store.serverState = data
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
    // getState already has maxRetries: 2 built in (see useApi).
    // If it still fails, schedule a deferred retry so we don't silently hang.
    try {
      const freshState = await api.getState(props.gameId)
      reconcileState(freshState) // clears reconnecting banner
    } catch (e) {
      console.error('Failed to re-fetch state on reconnect:', e)
      reconnecting.value = true
      scheduleReconcile()
    }
  },
})

// Show reconnect banner when Mercure SSE disconnects.
// When it reconnects, the banner stays visible until reconcileState confirms
// the client state is in sync (onReconnect handler calls reconcileState which
// clears the banner).
watch(mercureConnected, (isConnected) => {
  if (!isConnected && !disconnected.value) {
    reconnecting.value = true
  }
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

  // Snapshot remaining cards' current positions BEFORE committing the
  // intermediate state. The previous capture animation hid captured cards
  // with visibility:hidden — remaining cards are still visible and in their
  // original grid slots. We need these old positions to FLIP-animate after
  // Vue re-renders the table with only the remaining cards.
  const oldRects = new Map<string, DOMRect>()
  const currentTable = store.displayState?.table ?? []
  remainingCards.forEach(card => {
    const idx = currentTable.findIndex(t => t.suit === card.suit && t.value === card.value)
    if (idx >= 0) {
      const el = q(`[data-card-key="${cardKey(card, idx)}"]`)
      if (el) oldRects.set(`${card.value}-${card.suit}`, el.getBoundingClientRect())
    }
  })

  // Commit an intermediate state that shows the post-play table
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
  store.animating = true
  await nextTick()

  // FLIP: animate remaining cards from their old grid positions to the new
  // positions (after reflow with fewer cards). This prevents a visible jump.
  const flipPs: Promise<void>[] = []
  remainingCards.forEach((card, idx) => {
    const oldR = oldRects.get(`${card.value}-${card.suit}`)
    if (!oldR) return
    const el = q(`[data-card-key="${cardKey(card, idx)}"]`)
    if (!el) return
    const newR = el.getBoundingClientRect()
    const dx = oldR.left - newR.left, dy = oldR.top - newR.top
    if (Math.abs(dx) < 1 && Math.abs(dy) < 1) return
    const a = el.animate(
      [{ transform: `translate(${dx}px,${dy}px)` }, { transform: 'translate(0,0)' }],
      { duration: FLIP_TBL_MS, easing: 'ease-out' }
    )
    flipPs.push(new Promise(r => { a.onfinish = () => r() }))
  })
  if (flipPs.length > 0) await Promise.all(flipPs)

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
      await sweepToCaptured(sweepItems, capR, scale)
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
  lastRoundScoresBackup = data.scores
  if (data.gameState) await animateEndOfRoundSweep(data.gameState, data.sweep)
  showRoundEnd.value = true
}

async function handleGameOver(data: GameOverData): Promise<void> {
  lastRoundScores.value = data.scores || null
  gameOverWinner.value = data.winner
  gameOverCapturedCards.value = data.capturedCards || null
  if (data.gameState) {
    // For trick-taking games, no sweep animation — just commit the final state
    if (data.gameState.gameType === 'briscola' || data.gameState.gameType === 'tressette') {
      store.commitState(data.gameState)
    } else {
      await animateEndOfRoundSweep(data.gameState, data.sweep)
    }
  }
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

/** After commitState + nextTick, animate surviving cards from old positions to new.
 *  @param oppPlayedIdx 0-based index of the opponent card that was played (or -1 if none).
 *         Cards after this index slide left to fill the gap left by the removed card. */
function flipRearrange(before: Map<string, DOMRect>, oppPlayedIdx = -1): Promise<void> {
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

  // Opponent hand backs — when a card was played at oppPlayedIdx (0-based),
  // Vue removes the last opp-N key. Cards after the played position must FLIP
  // from their old (shifted-right) positions to fill the gap.
  const newOppCount = gs.value?.opponentHandCount ?? 0
  const playedPos1 = oppPlayedIdx >= 0 ? oppPlayedIdx + 1 : 0  // 1-based, 0 = no shift
  for (let n = 1; n <= newOppCount; n++) {
    const el = q(`[data-card-key="opp-${n}"]`)
    if (!el) continue
    const oldN = (playedPos1 > 0 && n >= playedPos1) ? n + 1 : n
    flipEl(el, `o:${oldN}`, FLIP_HND_MS)
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
    playedCardIdx.value = null
    if (store.pendingState) store.finishAnimation()
    else store.animating = false
    inPostAnimDelay.value = false
    processQueue()
  }, SAFETY_MS)

  try {
    if (result.type === 'place')   await animPlace(result)
    else if (result.type === 'capture') await animCapture(result)
    else if (result.type === 'choosing') {
      // Show the overlay immediately WITHOUT committing the choosing state.
      // This keeps the card visible in the hand. The choosing game-state
      // (arriving next via SSE) is stashed because animating=true, and stays
      // stashed until the player selects. handleSelectCapture releases
      // the lock so the capture turn-result flows through the normal pipeline
      // where the card is still in the hand.
      clearTimeout(safety)
      playedCardIdx.value = null
      choosingFromHand.value = result.playerIndex === store.myIndex
      choosingOptions.value = result.options ?? null
      showCaptureChoice.value = true
      // Safety: release lock after 30s if the player hasn't selected
      choosingSafety = setTimeout(() => {
        choosingSafety = null
        choosingOptions.value = null
        store.finishAnimation()
        processQueue()
      }, 30_000)
      // Brief yield so the choosing game-state arrives and is stashed
      await sleep(10)
      return  // Don't commit — card stays in hand
    }
    else if (result.type === 'trick') await animTrick(result)
  } catch (e) { console.error('Animation error:', e) }

  clearTimeout(safety)
  playedCardIdx.value = null

  // 1. Clear animation layer (clones no longer needed)
  clearLayer()

  // 2. When the round ends, the server publishes turn-result + round-end (no
  //    separate game-state event — see publishTurnOutcome). So pendingState is null.
  //    If we follow the normal path, restoreStyles() would flash the captured cards
  //    back to visible for 200ms+ before the sweep starts.
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

  // Pre-set dealHiding so ALL hand cards render with opacity:0 (no flash).
  // This MUST be set for ALL re-deal types including Briscola partial deals —
  // otherwise the newly drawn card flashes visible before the deal animation hides it.
  // For Briscola partial deals, runDealAnimation immediately reveals old cards after layout.
  // Capture pre-commit hand info for trick-game partial deal detection
  // (the inline commit will overwrite displayState, so we must save this now).
  let redealCtx: DealContext | undefined
  if (isRedeal) {
    store.dealHiding = true
    dealDeckCountOverride.value = store.displayState!.deckCount
    const isTrickPartial = (pending!.gameType === 'briscola' || pending!.gameType === 'tressette')
      && store.displayState!.myHand.length > 0
    if (isTrickPartial) {
      // Determine opponent's drawn card for tressette (drawn cards are visible)
      let opponentDrawnCard: Card | undefined
      let trickWinner: number | undefined
      if (pending!.gameType === 'tressette' && result.type === 'trick') {
        trickWinner = result.trickWinner
        const myIndex = store.myIndex
        const winnerIsMe = result.trickWinner === myIndex
        // Opponent's drawn card: if I won, opponent is loser; if opponent won, opponent is winner
        opponentDrawnCard = winnerIsMe ? result.loserDrawnCard : result.winnerDrawnCard
      }
      redealCtx = {
        prevMyHand: [...store.displayState!.myHand],
        prevDeckCount: store.displayState!.deckCount,
        opponentDrawnCard,
        trickWinner,
      }
    }
  }

  // 5. *** HARD INVARIANT: commitState ONLY here, after ALL animation ***
  //    Commit pending state but keep animating=true — the FLIP rearrangement
  //    (500ms) still needs the guard so incoming Mercure events stay queued.
  if (store.pendingState) {
    store.displayState = store.pendingState
    store.serverState = store.pendingState
    store.pendingState = null
  }
  // store.animating stays true through FLIP + post-anim delay

  // Re-enable capture choice overlay if committed state is choosing
  // (needed because showCaptureChoice is set to false on selection,
  // and the game-state was stashed — not routed through the Mercure handler)
  if (gs.value?.state === 'choosing') showCaptureChoice.value = true

  // 5. FLIP: animate surviving cards from old positions to new
  await nextTick()
  // Now safe to restore styles — Vue has already removed animated-away elements,
  // so restoring visibility on them is a no-op. Surviving elements get cleaned up.
  restoreStyles()

  // For Briscola partial deals: dealHiding hid ALL cards (including existing ones).
  // Reveal old cards NOW — before FLIP — so they stay visible during the FLIP animation.
  // The new card stays hidden (opacity 0) and will be animated from deck in runDealAnimation.
  if (redealCtx) {
    const prevSet = new Set(redealCtx.prevMyHand.map(c => `${c.suit}-${c.value}`))
    const myHand = gs.value?.myHand ?? []
    myHand.forEach((card, idx) => {
      if (prevSet.has(`${card.suit}-${card.value}`)) {
        const el = q(`[data-card-key="my-${cardKey(card, idx)}"]`)
        if (el) el.style.opacity = '1'
      }
    })
    // Reveal all but the last opponent card (the drawn one)
    const oppCount = gs.value?.opponentHandCount ?? 0
    for (let n = 1; n < oppCount; n++) {
      const el = q(`[data-card-key="opp-${n}"]`)
      if (el) el.style.opacity = '1'
    }
  }

  const isMe = result.playerIndex === store.myIndex
  const oppIdx = !isMe && result.cardIndex != null ? result.cardIndex : -1
  await flipRearrange(beforeRects, oppIdx)

  // 6. If re-deal detected, run deal animation instead of normal post-anim flow
  if (isRedeal && pending) {
    await runDealAnimation(pending, redealCtx)
    return  // deal animation calls processQueue when done
  }

  // Post-animation delay: release animating, use inPostAnimDelay as the guard
  // so isBusy() still returns true but canPlay is responsive (per d330813).
  store.animating = false
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
    playedCardIdx.value = null
  } else {
    const el = getOpponentCardEl(result)
    if (el) { srcR = el.getBoundingClientRect(); setStyle(el, 'visibility', 'hidden') }
  }
  if (!srcR) return

  // 2. Destination: the specific slot where the card will land.
  //    Briscola: always slot 0 (single centered slot, overlay existing card).
  //    Scopa: next empty slot (index = current table length).
  const tableSlotIdx = isTrickGame.value ? 0 : (gs.value?.table.length ?? 0)
  const slot = getTableSlotSize()
  const destW = slot?.w ?? srcR.width
  const destH = slot?.h ?? srcR.height
  const dest = getSlotRect(tableSlotIdx, destW, destH)
  if (!dest) return

  // 3. Clone flies from hand → target slot (scale up if hand cards smaller than table slot)
  const clone = isMe ? mkFace(card, srcR) : mkFlippable(card, srcR)
  aLayer().appendChild(clone)
  if (!isMe) setTimeout(() => flipCard(clone), SLIDE_MS * 0.1)
  const placeScale = Math.abs(destW / srcR.width - 1) > 0.01 ? destW / srcR.width : undefined
  await flyTo(clone, dest, SLIDE_MS, SLIDE_EASE, placeScale)
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
  //    After capture-choice: card already left hand, so query the hand row
  //    position fresh (safe against viewport changes during the overlay).
  let srcR: DOMRect | null = null
  const fromChoosing = gs.value?.state === 'choosing'
  if (fromChoosing && choosingFromHand.value) {
    // Choosing player: build a source rect from the last card in the hand row
    // (the played card is gone, but adjacent cards give a good origin).
    choosingFromHand.value = false
    playedCardIdx.value = null
    const lastHandCard = myHandRowEl.value?.lastElementChild as HTMLElement | null
    if (lastHandCard) {
      srcR = lastHandCard.getBoundingClientRect()
    } else if (myHandRowEl.value) {
      // Hand is now empty — use the hand row's center with actual card slot dimensions
      const hr = myHandRowEl.value.getBoundingClientRect()
      const slot = getTableSlotSize()
      const cardW = slot?.w ?? 75
      const cardH = slot?.h ?? 133
      srcR = new DOMRect(hr.left + hr.width / 2 - cardW / 2, hr.top, cardW, cardH)
    }
  } else if (!fromChoosing) {
    if (isMe) {
      const hand = gs.value?.myHand ?? []
      const idx = hand.findIndex(c => c.suit === card.suit && c.value === card.value)
      if (idx >= 0) {
        const el = q(`[data-card-key="my-${cardKey(card, idx)}"]`)
        if (el) { srcR = el.getBoundingClientRect(); setStyle(el, 'visibility', 'hidden') }
      }
      playedCardIdx.value = null
    } else {
      const el = getOpponentCardEl(result)
      if (el) { srcR = el.getBoundingClientRect(); setStyle(el, 'visibility', 'hidden') }
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
    playClone = isMe ? mkFace(card, srcR) : mkFlippable(card, srcR)
    playClone.style.zIndex = '53'
    aLayer().appendChild(playClone)
    if (!isMe) setTimeout(() => flipCard(playClone!), SLIDE_MS * 0.1)
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
    await sweepToCaptured(sweepItems, capR, scale, 0)
  }

  // 7. Scopa flash
  if (result.scopa) {
    scopaFlashRef.value?.show()
  }
}

// ════════════════════════════════════════════════════
// TRICK ANIMATION (Briscola & Tressette)
//
// When the follower plays, the trick resolves:
// 1. Follower's card flies from hand → table center (500ms)
// 2. Brief pause to show both cards (150ms)
// 3. Both cards sweep to winner's captured deck (450ms)
// ════════════════════════════════════════════════════

async function animTrick(result: TurnResult) {
  const isMe = result.playerIndex === store.myIndex
  const card = result.card     // follower's card
  const leaderCard = result.leaderCard
  const trickWinner = result.trickWinner ?? 0

  // 1. Find & hide follower's card in hand
  let srcR: DOMRect | null = null
  if (isMe) {
    const hand = gs.value?.myHand ?? []
    const idx = hand.findIndex(c => c.suit === card.suit && c.value === card.value)
    if (idx >= 0) {
      const el = q(`[data-card-key="my-${cardKey(card, idx)}"]`)
      if (el) { srcR = el.getBoundingClientRect(); setStyle(el, 'visibility', 'hidden') }
    }
    playedCardIdx.value = null
  } else {
    const el = getOpponentCardEl(result)
    if (el) { srcR = el.getBoundingClientRect(); setStyle(el, 'visibility', 'hidden') }
  }

  // 2. Fly follower's card to table center (same slot — overlays leader's card)
  const slot = getTableSlotSize()
  const destW = slot?.w ?? srcR?.width ?? 75
  const destH = slot?.h ?? srcR?.height ?? 133
  const dest = getSlotRect(0, destW, destH)
  const trickScale = srcR && Math.abs(destW / srcR.width - 1) > 0.01 ? destW / srcR.width : undefined
  let placeClone: HTMLElement | null = null
  if (srcR && dest) {
    placeClone = isMe ? mkFace(card, srcR) : mkFlippable(card, srcR)
    aLayer().appendChild(placeClone)
    if (!isMe) setTimeout(() => flipCard(placeClone!), SLIDE_MS * 0.1)
    await flyTo(placeClone, dest, SLIDE_MS, SLIDE_EASE, trickScale)
  }

  // 3. Brief pause to show both cards
  await sleep(CAP_PAUSE)
  const table = gs.value?.table ?? []

  // 4. Snapshot positions, hide table cards
  const sweepItems: { card: Card; rect: DOMRect }[] = []
  // Leader card (already on table from a previous place animation)
  if (leaderCard) {
    const lIdx = table.findIndex(t => t.suit === leaderCard.suit && t.value === leaderCard.value)
    if (lIdx >= 0) {
      const el = q(`[data-card-key="${cardKey(leaderCard, lIdx)}"]`)
      if (el) {
        sweepItems.push({ card: leaderCard, rect: el.getBoundingClientRect() })
        setStyle(el, 'visibility', 'hidden')
      }
    }
  }
  // Follower card — remove the place clone (sweep clone replaces it at same position)
  if (placeClone?.parentNode) placeClone.remove()
  if (dest) {
    sweepItems.push({ card, rect: dest })
  }

  // 5. Sweep to winner's captured deck
  const capR = capturedR(trickWinner)
  if (capR && sweepItems.length > 0) {
    const scale = sweepScale(sweepItems[0].rect.width, capR)
    await sweepToCaptured(sweepItems, capR, scale)
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
// - Briscola partial deal (after trick: 1 card per player)
//
// For Briscola partial deals, the caller (handleTurnResult) passes a
// DealContext with the pre-commit hand info, since the inline commit has
// already overwritten displayState by the time we get here.
// ════════════════════════════════════════════════════

/** Pre-commit state info for trick-game partial deals */
interface DealContext {
  prevMyHand: Card[]
  prevDeckCount: number
  /** For Tressette: the card the opponent drew (shown face-up during animation) */
  opponentDrawnCard?: Card
  /** For Tressette: which player won the trick (draws first) */
  trickWinner?: number
}

async function runDealAnimation(newState: GameState, ctx?: DealContext) {
  store.animating = true

  const prevTblLen = store.displayState?.table.length ?? 0
  // New round = table has more cards than before (or first load when displayState is null)
  const isNewRound = !store.displayState || newState.table.length > prevTblLen

  // Trick-game partial deal: after a trick, each player draws 1 card (not a full re-deal).
  // Applies to Briscola (always) and Tressette (while stock remains).
  // Detection uses DealContext (pre-commit state) when available, falling back to
  // displayState comparison for non-handleTurnResult paths (e.g. maybeCommitOrDeal).
  const isTrickPartialDeal = !isNewRound && (newState.gameType === 'briscola' || newState.gameType === 'tressette') && (
    ctx
      ? ctx.prevDeckCount > newState.deckCount  // deck decreased = cards were drawn
      : (store.displayState?.deckCount ?? 0) > newState.deckCount
  )

  // Build set of old card identities for finding new cards
  const prevMyHandSet = isTrickPartialDeal
    ? new Set((ctx?.prevMyHand ?? store.displayState?.myHand ?? []).map(c => `${c.suit}-${c.value}`))
    : null

  // Scopa markers are now driven by server state (myScopaCards/opponentScopaCards)
  // and reset automatically when the new round state is committed.

  // 1. Set hiding flags so Vue renders ALL hand cards with opacity:0.
  // For Briscola partial deals, old cards are revealed immediately after layout (step 2).
  // This MUST hide everything first to prevent new cards flashing visible.
  store.dealHiding = true
  store.dealHidingTable = isNewRound
  // Hide briscola card during initial deal (revealed after animation)
  if (isNewRound && newState.gameType === 'briscola') {
    store.dealHidingBriscola = true
  }
  // Freeze deck visual at pre-deal count so it doesn't vanish before animation.
  // Compute pre-deal count by adding back the cards that were dealt
  // (server's deckCount is already post-deal). If the override was already set
  // (re-deal path sets it before the inline commit to prevent flicker), keep it.
  const prevDeckCount = ctx?.prevDeckCount ?? store.displayState?.deckCount ?? 40
  const newCardsDealt = isTrickPartialDeal
    ? prevDeckCount - newState.deckCount
    : (isNewRound ? newState.table.length : 0) + newState.myHand.length + newState.opponentHandCount
  const preDealDeckCount = dealDeckCountOverride.value
    ?? (newState.deckCount + newCardsDealt)
  dealDeckCountOverride.value = preDealDeckCount
  // Commit state — new cards render with opacity:0 via reactive :style bindings
  // (for partial deal, existing cards remain visible since dealHiding is false)
  store.commitState(newState)
  await nextTick()
  // Wait for browser to lay out the elements (needed for getBoundingClientRect)
  await new Promise<void>(r => requestAnimationFrame(() => r()))

  // 2. Collect elements to animate
  const tblEls = isNewRound ? qAll('.table-center [data-card-key]') : []
  let myEls: HTMLElement[]
  let oppEls: HTMLElement[]

  if (isTrickPartialDeal && prevMyHandSet) {
    // All cards are hidden via dealHiding. Identify old vs new cards, then
    // immediately reveal old cards so only new ones stay hidden for animation.
    const allMyEls = qAll('.player-area.me .hand-row [data-card-key^="my-"]')
    const allOppEls = qAll('.player-area.opponent .hand-row [data-card-key^="opp-"]')
    myEls = []
    allMyEls.forEach((el, idx) => {
      const card = newState.myHand[idx]
      if (card && !prevMyHandSet.has(`${card.suit}-${card.value}`)) {
        myEls.push(el)  // new card — stays hidden, will animate from deck
      } else {
        el.style.opacity = '1'  // old card — reveal immediately
      }
    })
    // Opponent cards are all backs — can't identify by card identity.
    // In Briscola, each player draws exactly 1 card after a trick.
    // Reveal all but the last opponent card (the newly drawn one).
    oppEls = allOppEls.length > 0 ? [allOppEls[allOppEls.length - 1]] : []
    allOppEls.forEach((el, idx) => {
      if (idx < allOppEls.length - 1) el.style.opacity = '1'  // old — reveal
    })
  } else {
    myEls  = qAll('.player-area.me .hand-row [data-card-key^="my-"]')
    oppEls = qAll('.player-area.opponent .hand-row [data-card-key^="opp-"]')
  }
  const allEls = [...tblEls, ...myEls, ...oppEls]

  // 3. Deck position
  const dr = deckR()
  if (!dr || dr.width === 0 || allEls.length === 0) {
    store.dealHiding = false; store.dealHidingTable = false; store.dealHidingBriscola = false
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
    const wrapEl = deckVisualRef.value?.wrapEl
    if (!wrapEl) return
    if (remainingDeck <= 0) {
      wrapEl.classList.add('empty')
      const countEl = wrapEl.querySelector('.deck-count')
      if (countEl) (countEl as HTMLElement).style.display = 'none'
      // Hide briscola trump card together with the deck
      const briscolaEl = q('.briscola-trump-card')
      if (briscolaEl) (briscolaEl as HTMLElement).style.opacity = '0'
    } else {
      const countEl = wrapEl.querySelector('.deck-count')
      if (countEl) countEl.textContent = String(remainingDeck)
    }
  }

  // Build a set of "my" element refs for quick lookup (to distinguish from opponent)
  const myElSet = new Set(myEls)

  // Map my hand elements to their card data for flippable clones.
  // For partial deals (Briscola), myEls only contains new cards — those are at the
  // end of newState.myHand. For full deals, myEls maps 1:1 to newState.myHand.
  const myElCards = new Map<HTMLElement, Card>()
  if (isTrickPartialDeal) {
    // New cards are appended at the end of the hand
    const handLen = newState.myHand.length
    myEls.forEach((el, idx) => {
      const card = newState.myHand[handLen - myEls.length + idx]
      if (card) myElCards.set(el, card)
    })
  } else {
    myEls.forEach((el, idx) => {
      const card = newState.myHand[idx]
      if (card) myElCards.set(el, card)
    })
  }

  // Map opponent elements to drawn card for tressette (drawn cards are visible)
  const oppElCards = new Map<HTMLElement, Card>()
  const oppDrawnCard = ctx?.opponentDrawnCard
  const isTressetteReveal = isTrickPartialDeal && !!oppDrawnCard
  if (isTressetteReveal && oppDrawnCard && oppEls.length > 0) {
    oppElCards.set(oppEls[oppEls.length - 1], oppDrawnCard)
  }

  /** Get the card data for an element (my hand card or tressette opponent drawn card) */
  function getElCard(el: HTMLElement): Card | undefined {
    return myElCards.get(el) ?? oppElCards.get(el)
  }

  /** Create a deal clone: flippable for cards with known face, plain back for unknown */
  function mkDealClone(el: HTMLElement, card: Card | undefined): HTMLElement {
    if (card) return mkFlippable(card, dr!)
    return mkBack(dr!)
  }

  /** On arrival: flip if flippable, then reveal real element and remove clone.
   *  For tressette opponent drawn cards: flip to face, pause, flip back to back, then reveal. */
  function onDealArrive(clone: HTMLElement, el: HTMLElement, card: Card | undefined): Promise<void> {
    if (card) {
      flipCard(clone)
      if (isTressetteReveal && oppElCards.has(el)) {
        // Show face, pause, animate flip back to card-back, then reveal
        return sleep(DRAW_REVEAL_MS)
          .then(() => { flipToBack(clone) ; return sleep(DEAL_FLIP_MS) })
          .then(() => {
            el.style.opacity = '1'
            if (clone.parentNode) clone.remove()
          })
      }
      return sleep(DEAL_FLIP_MS).then(() => {
        el.style.opacity = '1'
        if (clone.parentNode) clone.remove()
      })
    }
    el.style.opacity = '1'
    if (clone.parentNode) clone.remove()
    return Promise.resolve()
  }

  // Table cards (new round only)
  tblEls.forEach((el, idx) => {
    const target = el.getBoundingClientRect()
    const card = newState.table[idx]
    const clone = mkDealClone(el, card)
    aLayer().appendChild(clone)
    const d = i++ * DEAL_TBL_LAG
    deals.push(sleep(d).then(() => {
      tickDeckDOM()
      return flyTo(clone, target, DEAL_MS, SLIDE_EASE, dealScale).then(() =>
        onDealArrive(clone, el, card)
      )
    }))
  })

  // Hand cards: interleave opponent and player cards (one at a time, alternating).
  // For tressette, winner draws first — reorder based on trick winner.
  const winnerIsMe = isTressetteReveal && ctx?.trickWinner === store.myIndex
  const maxHand = Math.max(myEls.length, oppEls.length)
  for (let h = 0; h < maxHand; h++) {
    // Default: opponent first. Tressette: winner first.
    const pair = winnerIsMe ? [myEls[h], oppEls[h]] : [oppEls[h], myEls[h]]
    for (const el of pair) {
      if (!el) continue
      const target = el.getBoundingClientRect()
      const card = getElCard(el)
      const clone = mkDealClone(el, card)
      aLayer().appendChild(clone)
      const d = i++ * DEAL_HND_LAG
      deals.push(sleep(d).then(() => {
        tickDeckDOM()
        return flyTo(clone, target, DEAL_MS, SLIDE_EASE, dealScale).then(() =>
          onDealArrive(clone, el, card)
        )
      }))
    }
  }

  await Promise.all(deals)
  clearLayer()

  // Restore imperative deck DOM changes before Vue takes over
  const wrapEl = deckVisualRef.value?.wrapEl
  if (wrapEl) {
    wrapEl.classList.remove('empty')
    const countEl = wrapEl.querySelector('.deck-count')
    if (countEl) { (countEl as HTMLElement).style.display = ''; countEl.textContent = '' }
  }
  // Clear deck count override — let reactive value take over
  dealDeckCountOverride.value = null
  // Clear hiding flags and wait for Vue to apply them BEFORE clearing imperative
  // opacity. Without this nextTick, clearing imperative opacity would expose Vue's
  // stale reactive opacity:0 (from dealHiding=true) for one frame, causing a flash.
  // IMPORTANT: This must happen BEFORE the briscola reveal below, because that
  // reveal changes reactive state (dealHidingBriscola) which triggers a Vue re-render.
  // That re-render would re-apply the reactive opacity:0 from dealHiding, overriding
  // the imperative opacity:1 set during the deal animation — causing a flash.
  store.dealHiding = false
  store.dealHidingTable = false
  await nextTick()
  // Now Vue has removed the reactive opacity:0 bindings — safe to clear imperative overrides
  allEls.forEach(el => { el.style.opacity = '' })

  // Reveal briscola card after hand cards are visible (smooth fade-in)
  if (store.dealHidingBriscola) {
    const briscolaEl = q('.briscola-trump-card')
    if (briscolaEl) {
      briscolaEl.style.opacity = '0'
      store.dealHidingBriscola = false
      await nextTick()
      briscolaEl.style.transition = 'opacity 300ms ease-in'
      briscolaEl.style.opacity = '1'
      await sleep(300)
      briscolaEl.style.transition = ''
      briscolaEl.style.opacity = ''
    } else {
      store.dealHidingBriscola = false
    }
  }

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
    // inline commit can use it (preserves correct per-turn state ordering).
    if (store.pendingEvents.length > 0 && store.pendingEvents[0].type === 'game-state') {
      const gsEv = store.shiftEvent()!
      if (gsEv.type === 'game-state') store.stashState(gsEv.data)
    }
    // Await handleTurnResult so exceptions are caught and the queue keeps draining.
    // handleTurnResult calls processQueue() at the end of its normal path, so we
    // must NOT chain another processQueue here — only on error fallback.
    try {
      await handleTurnResult(ev.data)
    } catch (e) {
      console.error('processQueue: turn-result handler failed:', e)
      // Ensure pipeline doesn't stall: clear animation lock and drain remaining events
      store.animating = false
      inPostAnimDelay.value = false
      processQueue()
    }
  }
  else if (ev.type === 'game-state') {
    // Stash but don't close overlay — see onGameState for explanation.
    if (showRoundEnd.value && ev.data.state !== 'round-end' && ev.data.state !== 'game-over') {
      store.serverState = ev.data
      return
    }
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
  playedCardIdx.value = cardIndex
  playInFlight.value = true
  try { await api.playCard(props.gameId, cardIndex) }
  catch (e: unknown) {
    playedCardIdx.value = null
    console.error('Play card error:', e)
    // The server may have processed the move despite the network error.
    // Show reconnecting banner and schedule a reconcile to catch up.
    reconnecting.value = true
    scheduleReconcile()
  }
  finally { playInFlight.value = false }
}
async function handleSelectCapture(optionIndex: number) {
  // Clear the choosing safety timer and release the animation pipeline lock
  // so the capture turn-result flows through the normal animation path.
  if (choosingSafety) { clearTimeout(choosingSafety); choosingSafety = null }
  showCaptureChoice.value = false
  store.animating = false
  try {
    await api.selectCapture(props.gameId, optionIndex)
    choosingOptions.value = null
  } catch (e: unknown) {
    console.error('Select capture error:', e)
    // Restore the overlay so the player can retry — without this the game hangs
    showCaptureChoice.value = true
    reconnecting.value = true
    scheduleReconcile()
  }
}
async function handleNextRound() {
  showRoundEnd.value = false; lastRoundScores.value = null
  // If the opponent already clicked "Next Round" and the game-state for the new
  // round was stashed in serverState, skip the API call and commit that state.
  const ss = store.serverState
  if (ss && ss.state !== 'round-end' && ss.state !== 'game-over') {
    maybeCommitOrDeal(ss)
    return
  }
  try { await api.nextRound(props.gameId) }
  catch (e: unknown) {
    console.error('Next round error:', e)
    // Re-check: the game-state event may have arrived while the API call was in flight.
    const currentState = store.serverState?.state
    if (currentState && currentState !== 'round-end' && currentState !== 'game-over') {
      maybeCommitOrDeal(store.serverState!)
      return
    }
    // Genuine error while still in round-end — restore overlay so player can retry.
    showRoundEnd.value = true
    lastRoundScores.value = lastRoundScoresBackup
    reconnecting.value = true
    scheduleReconcile()
  }
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
// State reconciliation — periodic polling safety net
// ════════════════════════════════════════════════════

/** Apply a fresh server state to the pipeline, respecting busy/queue semantics. */
function reconcileState(freshState: GameState) {
  reconnecting.value = false
  // If the server has moved past the choosing state, clear the overlay data
  // so it doesn't persist after a successful-but-lost select-capture response.
  if (freshState.state !== 'choosing') choosingOptions.value = null
  // Stash but don't close overlay — see onGameState for explanation.
  if (showRoundEnd.value && freshState.state !== 'round-end' && freshState.state !== 'game-over') {
    store.serverState = freshState
    return
  }
  if (isBusy()) {
    store.queueEvent({ type: 'game-state', data: freshState })
  } else {
    if (freshState.state === 'choosing') showCaptureChoice.value = true
    maybeCommitOrDeal(freshState)
  }
}

let reconcileTimer: ReturnType<typeof setTimeout> | null = null

function scheduleReconcile(delayMs = 3000) {
  if (reconcileTimer) return // already scheduled
  reconcileTimer = setTimeout(async () => {
    reconcileTimer = null
    try {
      const freshState = await api.getState(props.gameId)
      reconcileState(freshState)
    } catch (e) {
      console.error('Deferred reconcile failed:', e)
    }
  }, delayMs)
}

/** Periodic poll interval — catches any state the client missed via SSE.
 *  Runs every 5s. If the server state diverges from what we have, reconcile. */
const POLL_INTERVAL = 5000
let pollInterval: ReturnType<typeof setInterval> | undefined

async function pollForMissedState() {
  // Don't poll during animations — we'd just queue it anyway and the
  // animation pipeline will finish and process the queue on its own.
  if (store.animating || disconnected.value) return
  // Don't poll if game is over (terminal state)
  if (showGameOver.value) return

  try {
    const fresh = await api.getState(props.gameId)
    const current = store.serverState
    if (!current) return

    // Detect divergence: the server has moved to a different state or turn
    // that we haven't seen. Common cases:
    //   - It's now our turn but displayState says opponent's turn (missed SSE)
    //   - Server state changed (e.g. choosing → playing) but we're stuck
    const diverged =
      fresh.state !== current.state ||
      fresh.isMyTurn !== current.isMyTurn ||
      fresh.deckCount !== current.deckCount ||
      fresh.myHand.length !== current.myHand.length ||
      fresh.myCapturedCount !== current.myCapturedCount

    if (diverged) {
      console.warn('State poll: divergence detected, reconciling')
      reconcileState(fresh)
    }
  } catch {
    // Polling is best-effort — silently ignore transient failures
  }
}

// ════════════════════════════════════════════════════
// Lifecycle
// ════════════════════════════════════════════════════

let heartbeatInterval: ReturnType<typeof setInterval> | undefined

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
      if (state.mercureToken) setMercureCookie(state.mercureToken)
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
  pollInterval = setInterval(pollForMissedState, POLL_INTERVAL)
})

onUnmounted(() => {
  disconnectMercure()
  clearInterval(heartbeatInterval)
  clearInterval(pollInterval)
  if (reconcileTimer) { clearTimeout(reconcileTimer); reconcileTimer = null }
  if (choosingSafety) { clearTimeout(choosingSafety); choosingSafety = null }
  clearNudge()
})
</script>
