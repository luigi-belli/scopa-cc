import { ref, onUnmounted, type Ref } from 'vue'
import type { GameState, TurnResult, RoundEndData, GameOverData } from '@/types/game'

export function setMercureCookie(token: string): void {
  document.cookie = `mercureAuthorization=${token}; path=/.well-known/mercure; SameSite=Strict; Secure`
}

export interface MercureHandlers {
  onGameState?: (data: GameState) => void | Promise<void>
  onTurnResult?: (data: TurnResult) => void | Promise<void>
  onChooseCapture?: (data: unknown) => void | Promise<void>
  onRoundEnd?: (data: RoundEndData) => void | Promise<void>
  onGameOver?: (data: GameOverData) => void | Promise<void>
  onOpponentDisconnected?: () => void
  onReconnect?: () => void
}

export interface UseMercureReturn {
  connected: Ref<boolean>
  connect: (playerIndex: number) => void
  disconnect: () => void
}

export function useMercure(
  gameId: string,
  handlers: MercureHandlers
): UseMercureReturn {
  const connected = ref(false)
  let eventSource: EventSource | null = null

  function connect(playerIndex: number) {
    const topic = encodeURIComponent(`/games/${gameId}/player/${playerIndex}`)
    const url = `/.well-known/mercure?topic=${topic}`

    eventSource = new EventSource(url)
    let hasConnectedBefore = false

    eventSource.onopen = () => {
      const wasDisconnected = !connected.value && hasConnectedBefore
      connected.value = true
      hasConnectedBefore = true
      if (wasDisconnected) {
        handlers.onReconnect?.()
      }
    }

    eventSource.onerror = () => {
      connected.value = false
    }

    function parseEvent(event: MessageEvent): unknown {
      try {
        const payload = JSON.parse(event.data)
        return payload.data ?? payload
      } catch {
        console.error('Failed to parse SSE event data')
        return null
      }
    }

    // Listen for typed events
    eventSource.addEventListener('game-state', (event: MessageEvent) => {
      const data = parseEvent(event)
      if (data) handlers.onGameState?.(data as GameState)
    })

    eventSource.addEventListener('turn-result', (event: MessageEvent) => {
      const data = parseEvent(event)
      if (data) handlers.onTurnResult?.(data as TurnResult)
    })

    eventSource.addEventListener('choose-capture', (event: MessageEvent) => {
      const data = parseEvent(event)
      if (data) handlers.onChooseCapture?.(data)
    })

    eventSource.addEventListener('round-end', (event: MessageEvent) => {
      const data = parseEvent(event)
      if (data) handlers.onRoundEnd?.(data as RoundEndData)
    })

    eventSource.addEventListener('game-over', (event: MessageEvent) => {
      const data = parseEvent(event)
      if (data) handlers.onGameOver?.(data as GameOverData)
    })

    eventSource.addEventListener('opponent-disconnected', () => {
      handlers.onOpponentDisconnected?.()
    })
  }

  function disconnect() {
    if (eventSource) {
      eventSource.close()
      eventSource = null
      connected.value = false
    }
  }

  onUnmounted(() => {
    disconnect()
  })

  return { connected, connect, disconnect }
}
