import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { GameState, TurnResult, RoundEndData, GameOverData } from '@/types/game'

export type QueuedEvent =
  | { type: 'turn-result'; data: TurnResult }
  | { type: 'game-state'; data: GameState }
  | { type: 'round-end'; data: RoundEndData }
  | { type: 'game-over'; data: GameOverData }

export const useGameStore = defineStore('game', () => {
  const gameId = ref<string | null>(null)
  const playerToken = ref<string | null>(null)
  const myIndex = ref<number>(0)

  // Two-layer state model
  const displayState = ref<GameState | null>(null)
  const serverState = ref<GameState | null>(null)
  const pendingState = ref<GameState | null>(null)

  const animating = ref(false)
  const pendingTurnResult = ref<TurnResult | null>(null)
  const pendingEvents = ref<QueuedEvent[]>([])

  // Deal animation flags
  const dealHiding = ref(false)
  const dealHidingTable = ref(false)

  function commitState(state: GameState) {
    displayState.value = state
    serverState.value = state
    pendingState.value = null
  }

  function stashState(state: GameState) {
    serverState.value = state
    pendingState.value = state
  }

  function finishAnimation() {
    if (pendingState.value) {
      displayState.value = pendingState.value
      serverState.value = pendingState.value
      pendingState.value = null
    }
    animating.value = false
  }

  function setGame(id: string, token: string, index: number) {
    gameId.value = id
    playerToken.value = token
    myIndex.value = index
    localStorage.setItem(`scopa-player-token-${id}`, token)
    localStorage.setItem('scopa-active-game', JSON.stringify({ gameId: id, playerToken: token, myIndex: index }))
  }

  function loadToken(id: string): string | null {
    return localStorage.getItem(`scopa-player-token-${id}`)
  }

  /** Restore session from localStorage (e.g. after page reload). Returns true if a session was found. */
  function restoreSession(): boolean {
    const raw = localStorage.getItem('scopa-active-game')
    if (!raw) return false
    try {
      const session = JSON.parse(raw)
      if (session.gameId && session.playerToken) {
        gameId.value = session.gameId
        playerToken.value = session.playerToken
        myIndex.value = session.myIndex ?? 0
        return true
      }
    } catch { /* invalid JSON, ignore */ }
    return false
  }

  function clearSession() {
    localStorage.removeItem('scopa-active-game')
  }

  function queueEvent(event: QueuedEvent): void {
    pendingEvents.value.push(event)
  }

  function shiftEvent(): QueuedEvent | undefined {
    return pendingEvents.value.shift()
  }

  function $reset() {
    clearSession()
    gameId.value = null
    playerToken.value = null
    myIndex.value = 0
    displayState.value = null
    serverState.value = null
    pendingState.value = null
    animating.value = false
    pendingTurnResult.value = null
    pendingEvents.value = []
    dealHiding.value = false
    dealHidingTable.value = false
  }

  return {
    gameId,
    playerToken,
    myIndex,
    displayState,
    serverState,
    pendingState,
    animating,
    pendingTurnResult,
    pendingEvents,
    dealHiding,
    dealHidingTable,
    commitState,
    stashState,
    finishAnimation,
    setGame,
    loadToken,
    restoreSession,
    clearSession,
    queueEvent,
    shiftEvent,
    $reset,
  }
})
