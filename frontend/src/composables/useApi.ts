import { useGameStore } from '@/stores/gameStore'
import { useI18n } from '@/i18n'
import type {
  CreateGameResponse,
  JoinGameResponse,
  GameLookupResult,
  GameState,
  GameType,
} from '@/types/game'

export class ApiError extends Error {
  constructor(message: string, public readonly status: number) {
    super(message)
    this.name = 'ApiError'
  }
}

interface RetryOptions {
  /** Max number of retry attempts (0 = no retries). Default 0. */
  maxRetries?: number
  /** Request timeout in ms. Default 10000. */
  timeout?: number
}

interface Api {
  createGame(playerName: string, gameName: string | null, singlePlayer: boolean, deckStyle: string, gameType: GameType): Promise<CreateGameResponse>
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

/** Initial retry delay in ms — doubles after each attempt (exponential backoff). */
export const RETRY_BASE_DELAY = 500

/** Whether an error is transient and worth retrying. */
export function isRetryable(error: unknown): boolean {
  if (error instanceof ApiError) {
    // 5xx = server error (transient), 429 = rate limited
    return error.status >= 500 || error.status === 429
  }
  // Network errors (fetch throws TypeError on network failure)
  if (error instanceof TypeError) return true
  // AbortError from timeout
  if (error instanceof DOMException && error.name === 'AbortError') return true
  return false
}

export function useApi(): Api {
  const store = useGameStore()
  const { t } = useI18n()

  async function request<T>(
    path: string,
    options: RequestInit = {},
    retryOpts: RetryOptions = {}
  ): Promise<T> {
    const { maxRetries = 0, timeout = 10_000 } = retryOpts
    let lastError: unknown

    for (let attempt = 0; attempt <= maxRetries; attempt++) {
      // Wait before retrying (exponential backoff with jitter)
      if (attempt > 0) {
        const delay = RETRY_BASE_DELAY * Math.pow(2, attempt - 1)
        const jitter = delay * 0.2 * Math.random()
        await new Promise(r => setTimeout(r, delay + jitter))
      }

      try {
        lastError = undefined
        const result = await doRequest<T>(path, options, timeout)
        return result
      } catch (e) {
        lastError = e
        if (!isRetryable(e) || attempt >= maxRetries) break
      }
    }

    throw lastError
  }

  async function doRequest<T>(
    path: string,
    options: RequestInit,
    timeout: number,
  ): Promise<T> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(options.headers as Record<string, string> || {}),
    }

    if (store.playerToken) {
      headers['X-Player-Token'] = store.playerToken
    }

    const controller = new AbortController()
    const timer = setTimeout(() => controller.abort(), timeout)

    let response: Response
    try {
      response = await fetch(`${BASE}${path}`, {
        ...options,
        headers,
        signal: controller.signal,
      })
    } finally {
      clearTimeout(timer)
    }

    if (response.status === 409) {
      let key = 'error.conflict'
      try {
        const body = await response.json()
        const KNOWN_KEYS: readonly string[] = ['error.conflict', 'error.gameNameTaken'] as const
        if (body?.detail && KNOWN_KEYS.includes(body.detail)) key = body.detail
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

    return response.json() as Promise<T>
  }

  async function createGame(
    playerName: string,
    gameName: string | null,
    singlePlayer: boolean,
    deckStyle: string,
    gameType: GameType = 'scopa'
  ): Promise<CreateGameResponse> {
    return request<CreateGameResponse>('/games', {
      method: 'POST',
      body: JSON.stringify({ playerName, gameName, singlePlayer, deckStyle, gameType }),
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
    return request<GameState>(`/games/${gameId}`, {}, { maxRetries: 2 })
  }

  async function playCard(gameId: string, cardIndex: number): Promise<GameState> {
    return request<GameState>(`/games/${gameId}/play-card`, {
      method: 'POST',
      body: JSON.stringify({ cardIndex }),
    }, { maxRetries: 1 })
  }

  async function selectCapture(
    gameId: string,
    optionIndex: number
  ): Promise<GameState> {
    return request<GameState>(`/games/${gameId}/select-capture`, {
      method: 'POST',
      body: JSON.stringify({ optionIndex }),
    }, { maxRetries: 1 })
  }

  async function nextRound(gameId: string): Promise<GameState> {
    return request<GameState>(`/games/${gameId}/next-round`, {
      method: 'POST',
      body: '{}',
    }, { maxRetries: 1 })
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
