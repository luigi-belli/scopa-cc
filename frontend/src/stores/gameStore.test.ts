import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useGameStore } from './gameStore'
import type { GameState } from '@/types/game'

/** Helper: create a minimal valid GameState for testing */
function makeState(overrides: Partial<GameState> = {}): GameState {
  return {
    state: 'playing',
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

describe('gameStore — two-layer state model', () => {
  let store: ReturnType<typeof useGameStore>

  beforeEach(() => {
    setActivePinia(createPinia())
    store = useGameStore()
  })

  describe('commitState', () => {
    it('updates both displayState and serverState simultaneously', () => {
      const s = makeState()
      store.commitState(s)
      expect(store.displayState).toStrictEqual(s)
      expect(store.serverState).toStrictEqual(s)
      expect(store.pendingState).toBeNull()
    })
  })

  describe('stashState', () => {
    it('updates serverState and pendingState but NOT displayState', () => {
      const initial = makeState({ myTotalScore: 0 })
      store.commitState(initial)

      const updated = makeState({ myTotalScore: 5 })
      store.stashState(updated)

      expect(store.displayState).toStrictEqual(initial)
      expect(store.serverState).toStrictEqual(updated)
      expect(store.pendingState).toStrictEqual(updated)
    })

    it('prevents score flicker — displayState scores unchanged during animation', () => {
      const initial = makeState({
        myTotalScore: 3,
        opponentTotalScore: 2,
        myCapturedCount: 10,
        opponentCapturedCount: 8,
      })
      store.commitState(initial)

      const updated = makeState({
        myTotalScore: 7,
        opponentTotalScore: 5,
        myCapturedCount: 18,
        opponentCapturedCount: 12,
      })
      store.stashState(updated)

      // Display still shows old scores — no flicker
      expect(store.displayState!.myTotalScore).toBe(3)
      expect(store.displayState!.opponentTotalScore).toBe(2)
      expect(store.displayState!.myCapturedCount).toBe(10)
      expect(store.displayState!.opponentCapturedCount).toBe(8)
    })
  })

  describe('finishAnimation', () => {
    it('commits pendingState to displayState and clears animating', () => {
      const initial = makeState({ myTotalScore: 0 })
      store.commitState(initial)
      store.animating = true

      const updated = makeState({ myTotalScore: 5 })
      store.stashState(updated)

      store.finishAnimation()

      expect(store.displayState).toStrictEqual(updated)
      expect(store.serverState).toStrictEqual(updated)
      expect(store.pendingState).toBeNull()
      expect(store.animating).toBe(false)
    })

    it('does nothing to displayState if pendingState is null', () => {
      const initial = makeState()
      store.commitState(initial)
      store.animating = true

      store.finishAnimation()

      expect(store.displayState).toStrictEqual(initial)
      expect(store.animating).toBe(false)
    })
  })

  describe('event queue', () => {
    it('queues events in order', () => {
      store.queueEvent({ type: 'turn-result', data: { type: 'place', card: { suit: 'Denari', value: 7 }, playerIndex: 0, captured: [], scopa: false } })
      store.queueEvent({ type: 'game-state', data: makeState() })

      expect(store.pendingEvents).toHaveLength(2)
      expect(store.pendingEvents[0].type).toBe('turn-result')
      expect(store.pendingEvents[1].type).toBe('game-state')
    })

    it('shiftEvent returns events in FIFO order', () => {
      store.queueEvent({ type: 'turn-result', data: { type: 'place', card: { suit: 'Denari', value: 7 }, playerIndex: 0, captured: [], scopa: false } })
      store.queueEvent({ type: 'game-state', data: makeState() })

      const first = store.shiftEvent()
      expect(first?.type).toBe('turn-result')

      const second = store.shiftEvent()
      expect(second?.type).toBe('game-state')

      expect(store.shiftEvent()).toBeUndefined()
    })
  })

  describe('dealHiding flags', () => {
    it('dealHiding set before commitState prevents hand card flash', () => {
      store.dealHiding = true
      const s = makeState({ myHand: [{ suit: 'Denari', value: 7 }] })
      store.commitState(s)

      // dealHiding is still true — template renders cards with opacity:0
      expect(store.dealHiding).toBe(true)
      expect(store.displayState!.myHand).toHaveLength(1)
    })

    it('dealHidingTable set before commitState prevents table card flash', () => {
      store.dealHidingTable = true
      const s = makeState({ table: [{ suit: 'Coppe', value: 3 }] })
      store.commitState(s)

      expect(store.dealHidingTable).toBe(true)
      expect(store.displayState!.table).toHaveLength(1)
    })
  })
})
