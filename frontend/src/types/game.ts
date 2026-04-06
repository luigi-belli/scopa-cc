import type { Card } from './card'

export interface GameState {
  state: string
  currentPlayer: number
  myIndex: number
  myName: string
  opponentName: string
  myHand: Card[]
  myCapturedCount: number
  myScope: number
  myTotalScore: number
  opponentHandCount: number
  opponentCapturedCount: number
  opponentScope: number
  opponentTotalScore: number
  table: Card[]
  deckCount: number
  isMyTurn: boolean
  pendingChoice: Card[][] | null
  roundHistory: RoundHistoryEntry[]
  deckStyle: string
}

export interface TurnResult {
  type: 'place' | 'capture' | 'choosing'
  card: Card
  playerIndex: number
  captured: Card[]
  scopa: boolean
  options?: Card[][]
}

export interface RoundScores {
  carte: number
  denari: number
  setteBello: number
  primiera: number
  scope: number
}

export interface RoundHistoryEntry {
  scores: [RoundScores, RoundScores]
  totals: [number, number]
}

export interface RoundEndData {
  scores: [RoundScores, RoundScores]
  gameState: GameState
}

export interface GameOverData {
  scores: [RoundScores, RoundScores]
  winner: number
  gameState: GameState
}

export interface CreateGameResponse {
  gameId: string
  playerToken: string
  state: string
  gameState: GameState | null
}

export interface JoinGameResponse {
  gameId: string
  playerToken: string
  gameState: GameState
}

export interface GameLookupResult {
  id: string
  name: string
  state: string
}
