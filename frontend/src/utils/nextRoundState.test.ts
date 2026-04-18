import { describe, it, expect } from 'vitest'
import { pickNextRoundState } from './nextRoundState'
import type { GameState, GameStateValue } from '@/types/game'

function makeState(state: GameStateValue, overrides: Partial<GameState> = {}): GameState {
  return {
    state,
    currentPlayer: 0,
    myIndex: 0,
    myName: 'Alice',
    opponentName: 'Bob',
    myHand: [],
    myCapturedCount: 0,
    myScope: 0,
    myTotalScore: 0,
    opponentHandCount: 0,
    opponentCapturedCount: 0,
    opponentScope: 0,
    opponentTotalScore: 0,
    table: [],
    deckCount: 30,
    isMyTurn: true,
    pendingChoice: null,
    roundHistory: [],
    deckStyle: 'piacentine',
    gameType: 'scopa',
    briscolaCard: null,
    lastTrick: null,
    myScopaCards: [],
    opponentScopaCards: [],
    ...overrides,
  }
}

describe('pickNextRoundState', () => {
  it('returns the API response when no state has been stashed yet', () => {
    const apiResponse = makeState('playing', { deckCount: 30, myHand: [{ suit: 'Denari', value: 7 }] })
    expect(pickNextRoundState(null, apiResponse)).toBe(apiResponse)
  })

  it('returns the API response when serverState is still round-end (Mercure not yet arrived) — prevents post-dialog hang', () => {
    const stashed = makeState('round-end')
    const apiResponse = makeState('playing', { deckCount: 30, myHand: [{ suit: 'Denari', value: 7 }] })
    expect(pickNextRoundState(stashed, apiResponse)).toBe(apiResponse)
  })

  it('returns the API response when serverState is game-over', () => {
    const stashed = makeState('game-over')
    const apiResponse = makeState('playing')
    expect(pickNextRoundState(stashed, apiResponse)).toBe(apiResponse)
  })

  it('prefers a progressed stashed serverState when Mercure already delivered the new state', () => {
    const stashed = makeState('playing', { deckCount: 30, myHand: [{ suit: 'Coppe', value: 3 }] })
    const apiResponse = makeState('playing', { deckCount: 30, myHand: [{ suit: 'Denari', value: 7 }] })
    expect(pickNextRoundState(stashed, apiResponse)).toBe(stashed)
  })

  it('prefers a stashed choosing state (AI/opponent already triggered a capture choice)', () => {
    const stashed = makeState('choosing')
    const apiResponse = makeState('playing')
    expect(pickNextRoundState(stashed, apiResponse)).toBe(stashed)
  })
})
