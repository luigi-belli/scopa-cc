import { useGameStore } from '@/stores/gameStore'
import { useI18n } from '@/i18n'
import type {
  CreateGameResponse,
  JoinGameResponse,
  GameLookupResult,
  GameState,
} from '@/types/game'

export class ApiError extends Error {
  constructor(message: string, public readonly status: number) {
    super(message)
    this.name = 'ApiError'
  }
}

interface Api {
  createGame(playerName: string, gameName: string | null, singlePlayer: boolean, deckStyle: string): Promise<CreateGameResponse>
  lookupGame(name: string): Promise<GameLookupResult[]>
  joinGame(gameId: string, playerName: string): Promise<JoinGameResponse>
  getState(gameId: string): Promise<GameState>
  playCard(gameId: string, cardIndex: number): Promise<GameState>
  selectCapture(gameId: string, optionIndex: number): Promise<GameState>
  nextRound(gameId: string): Promise<GameState>
  heartbeat(gameId: string): Promise<void>
  leaveGame(gameId: string): Promise<void>
}

const BASE = '/api'

export function useApi(): Api {
  const store = useGameStore()
  const { t } = useI18n()

  async function request<T>(
    path: string,
    options: RequestInit = {}
  ): Promise<T> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
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
      let key = 'error.conflict'
      try {
        const body = await response.json()
        if (body?.detail) key = body.detail
      } catch { /* ignore parse errors */ }
      throw new ApiError(t(key), 409)
    }

    if (response.status === 403) {
      throw new ApiError(t('api.accessDenied'), 403)
    }

    if (!response.ok) {
      const text = await response.text()
      throw new ApiError(t('api.error', { status: response.status, text }), response.status)
    }

    const contentType = response.headers.get('Content-Type') || ''
    if (response.status === 204 || !contentType.includes('json')) {
      return null as T
    }

    return response.json()
  }

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
