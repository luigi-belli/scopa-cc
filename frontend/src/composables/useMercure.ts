import { ref, onUnmounted, type Ref } from 'vue'
import type { GameState, TurnResult, RoundEndData, GameOverData } from '@/types/game'

/** Wrap handler calls in try/catch to prevent exceptions from breaking
 *  the EventSource message loop. A single bad handler must never kill
 *  the entire SSE pipeline. */
function safeCall(fn: () => unknown): void {
  try {
    const result = fn()
    // Catch rejected promises from async handlers
    if (result instanceof Promise) {
      result.catch(e => console.error('Mercure handler async error:', e))
    }
  } catch (e) {
    console.error('Mercure handler error:', e)
  }
}

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
  onReconnect?: () => void | Promise<void>
}

export interface UseMercureReturn {
  connected: Ref<boolean>
  connect: (playerIndex: number) => void
  disconnect: () => void
}

/** Time in ms without any SSE activity before the connection is considered stale.
 *  EventSource normally receives periodic comments (:keepalive) from Mercure hub,
 *  but if the proxy or network silently drops the connection we won't get onerror. */
const STALE_TIMEOUT = 45_000

export function useMercure(
  gameId: string,
  handlers: MercureHandlers
): UseMercureReturn {
  const connected = ref(false)
  let eventSource: EventSource | null = null
  let staleTimer: ReturnType<typeof setTimeout> | null = null

  /** Reset the staleness timer — called on every incoming event or open. */
  function touchActivity() {
    if (staleTimer) clearTimeout(staleTimer)
    staleTimer = setTimeout(() => {
      // No activity for STALE_TIMEOUT — force reconnect
      if (eventSource) {
        eventSource.close()
        eventSource = null
        connected.value = false
        // Trigger reconnection after a short delay.
        // Capture the index now so the async callback doesn't need a non-null assertion.
        const idx = savedPlayerIndex
        if (idx !== null) {
          setTimeout(() => {
            if (!eventSource) connect(idx)
          }, 1000)
        }
      }
    }, STALE_TIMEOUT)
  }

  let savedPlayerIndex: number | null = null

  function connect(playerIndex: number) {
    // Clean up any existing connection before creating a new one
    if (eventSource) {
      eventSource.close()
      eventSource = null
    }

    savedPlayerIndex = playerIndex
    const topic = encodeURIComponent(`/games/${gameId}/player/${playerIndex}`)
    const url = `/.well-known/mercure?topic=${topic}`

    eventSource = new EventSource(url)
    let hasConnectedBefore = false

    eventSource.onopen = () => {
      const wasDisconnected = !connected.value && hasConnectedBefore
      connected.value = true
      hasConnectedBefore = true
      touchActivity()
      if (wasDisconnected) {
        safeCall(() => handlers.onReconnect?.())
      }
    }

    eventSource.onerror = () => {
      connected.value = false
      // Browser's native EventSource handles reconnection automatically.
      // We just mark disconnected so the UI can show a banner.
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
      touchActivity()
      const data = parseEvent(event)
      if (data) safeCall(() => handlers.onGameState?.(data as GameState))
    })

    eventSource.addEventListener('turn-result', (event: MessageEvent) => {
      touchActivity()
      const data = parseEvent(event)
      if (data) safeCall(() => handlers.onTurnResult?.(data as TurnResult))
    })

    eventSource.addEventListener('choose-capture', (event: MessageEvent) => {
      touchActivity()
      const data = parseEvent(event)
      if (data) safeCall(() => handlers.onChooseCapture?.(data))
    })

    eventSource.addEventListener('round-end', (event: MessageEvent) => {
      touchActivity()
      const data = parseEvent(event)
      if (data) safeCall(() => handlers.onRoundEnd?.(data as RoundEndData))
    })

    eventSource.addEventListener('game-over', (event: MessageEvent) => {
      touchActivity()
      const data = parseEvent(event)
      if (data) safeCall(() => handlers.onGameOver?.(data as GameOverData))
    })

    eventSource.addEventListener('opponent-disconnected', () => {
      touchActivity()
      safeCall(() => handlers.onOpponentDisconnected?.())
    })
  }

  function disconnect() {
    if (staleTimer) { clearTimeout(staleTimer); staleTimer = null }
    savedPlayerIndex = null
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
