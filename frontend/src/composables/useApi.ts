import { useGameStore } from '@/stores/gameStore'
import type {
  CreateGameResponse,
  JoinGameResponse,
  GameLookupResult,
  GameState,
} from '@/types/game'

const BASE = '/api'

async function request<T>(
  path: string,
  options: RequestInit = {}
): Promise<T> {
  const store = useGameStore()
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string> || {}),
  }

  if (store.playerToken) {
    headers['X-Player-Token'] = store.playerToken
  }

  const response = await fetch(`${BASE}${path}`, {
    ...options,
    headers,
  })

  if (response.status === 409) {
    throw new Error('Conflict: please retry')
  }

  if (response.status === 403) {
    throw new Error('Access denied: invalid token')
  }

  if (!response.ok) {
    const text = await response.text()
    throw new Error(`API error ${response.status}: ${text}`)
  }

  const contentType = response.headers.get('Content-Type') || ''
  if (response.status === 204 || !contentType.includes('json')) {
    return null as T
  }

  return response.json()
}

export function useApi() {
  async function createGame(
    playerName: string,
    gameName: string | null,
    singlePlayer: boolean,
    deckStyle: string
  ): Promise<CreateGameResponse> {
    return request<CreateGameResponse>('/games', {
      method: 'POST',
      body: JSON.stringify({ playerName, gameName, singlePlayer, deckStyle }),
    })
  }

  async function lookupGame(name: string): Promise<GameLookupResult[]> {
    return request<GameLookupResult[]>(`/games/lookup?name=${encodeURIComponent(name)}`)
  }

  async function joinGame(
    gameId: string,
    playerName: string
  ): Promise<JoinGameResponse> {
    return request<JoinGameResponse>(`/games/${gameId}/join`, {
      method: 'POST',
      body: JSON.stringify({ playerName }),
    })
  }

  async function getState(gameId: string): Promise<GameState> {
    return request<GameState>(`/games/${gameId}`)
  }

  async function playCard(gameId: string, cardIndex: number): Promise<GameState> {
    return request<GameState>(`/games/${gameId}/play-card`, {
      method: 'POST',
      body: JSON.stringify({ cardIndex }),
    })
  }

  async function selectCapture(
    gameId: string,
    optionIndex: number
  ): Promise<GameState> {
    return request<GameState>(`/games/${gameId}/select-capture`, {
      method: 'POST',
      body: JSON.stringify({ optionIndex }),
    })
  }

  async function nextRound(gameId: string): Promise<GameState> {
    return request<GameState>(`/games/${gameId}/next-round`, {
      method: 'POST',
      body: '{}',
    })
  }

  async function heartbeat(gameId: string): Promise<void> {
    return request<void>(`/games/${gameId}/heartbeat`, {
      method: 'POST',
      body: '{}',
    })
  }

  async function leaveGame(gameId: string): Promise<void> {
    return request<void>(`/games/${gameId}/leave`, {
      method: 'POST',
      body: '{}',
    })
  }

  return {
    createGame,
    lookupGame,
    joinGame,
    getState,
    playCard,
    selectCapture,
    nextRound,
    heartbeat,
    leaveGame,
  }
}
