import { ref, onUnmounted } from 'vue'

export function useMercure(
  gameId: string,
  handlers: {
    onGameState?: (data: any) => void
    onTurnResult?: (data: any) => void
    onChooseCapture?: (data: any) => void
    onRoundEnd?: (data: any) => void
    onGameOver?: (data: any) => void
    onOpponentDisconnected?: () => void
  }
) {
  const connected = ref(false)
  let eventSource: EventSource | null = null

  function connect(playerIndex: number) {
    const topic = encodeURIComponent(`/games/${gameId}/player/${playerIndex}`)
    const url = `/.well-known/mercure?topic=${topic}`

    eventSource = new EventSource(url)

    eventSource.onopen = () => {
      connected.value = true
    }

    eventSource.onerror = () => {
      connected.value = false
    }

    // Listen for typed events
    eventSource.addEventListener('game-state', (event: MessageEvent) => {
      const payload = JSON.parse(event.data)
      const data = payload.data ?? payload
      handlers.onGameState?.(data)
    })

    eventSource.addEventListener('turn-result', (event: MessageEvent) => {
      const payload = JSON.parse(event.data)
      const data = payload.data ?? payload
      handlers.onTurnResult?.(data)
    })

    eventSource.addEventListener('choose-capture', (event: MessageEvent) => {
      const payload = JSON.parse(event.data)
      const data = payload.data ?? payload
      handlers.onChooseCapture?.(data)
    })

    eventSource.addEventListener('round-end', (event: MessageEvent) => {
      const payload = JSON.parse(event.data)
      const data = payload.data ?? payload
      handlers.onRoundEnd?.(data)
    })

    eventSource.addEventListener('game-over', (event: MessageEvent) => {
      const payload = JSON.parse(event.data)
      const data = payload.data ?? payload
      handlers.onGameOver?.(data)
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
