import type { GameState } from '@/types/game'

/** Pick the authoritative state to commit after a successful `nextRound` API call.
 *
 *  The Mercure `game-state` event and the API response carry the same new state,
 *  but Mercure may arrive before, during, or after the API resolves. The stashed
 *  `serverState` may therefore be:
 *   - already progressed past round-end (Mercure arrived first, or an AI move
 *     was already published after the new round started) → prefer it
 *   - still on round-end (Mercure hasn't arrived yet) → fall back to the API
 *     response so the UI doesn't hang on the stale round-end state
 *
 *  Without this fallback, if Mercure is delayed even slightly the game appears
 *  to hang after dismissing the round-end dialog. */
export function pickNextRoundState(
  stashed: GameState | null,
  apiResponse: GameState,
): GameState {
  if (stashed && stashed.state !== 'round-end' && stashed.state !== 'game-over') {
    return stashed
  }
  return apiResponse
}
