import type { Card } from './card'

export type GameType = 'scopa' | 'briscola' | 'tressette'

export type GameStateValue = 'waiting' | 'playing' | 'choosing' | 'round-end' | 'game-over' | 'finished'

export interface GameState {
  state: GameStateValue
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
  mercureToken?: string | null
  gameType: GameType
  briscolaCard: Card | null
  lastTrick: TrickData | null
}

export interface TrickData {
  leaderCard: Card
  followerCard: Card
  winnerIndex: number
}

export interface TurnResult {
  type: 'place' | 'capture' | 'choosing' | 'trick'
  card: Card
  playerIndex: number
  captured: Card[]
  scopa: boolean
  options?: Card[][]
  trickWinner?: number
  leaderCard?: Card
}

export interface RoundScores {
  carte: number
  denari: number
  setteBello: number
  primiera: number
  scope: number
  carteCount: number
  denariCount: number
  primieraValue: number | null
  hasSetteBello: boolean
  carteCards: Card[]
  denariCards: Card[]
  primieraCards: Card[]
}

export interface RoundHistoryEntry {
  scores: [RoundScores, RoundScores]
  totals: [number, number]
}

export interface SweepData {
  remainingCards: Card[]
  lastCapturer: number
}

export interface RoundEndData {
  scores: [RoundScores, RoundScores]
  gameState: GameState
  sweep?: SweepData
}

export interface GameOverData {
  scores?: [RoundScores, RoundScores]
  winner: number
  gameState: GameState
  sweep?: SweepData
  capturedCards?: [Card[], Card[]]
}

export interface CreateGameResponse {
  gameId: string
  playerToken: string
  state: string
  gameType: GameType
  gameState: GameState | null
  mercureToken: string | null
}

export interface JoinGameResponse {
  gameId: string
  playerToken: string
  gameState: GameState
  mercureToken: string | null
}

export interface GameLookupResult {
  id: string
  name: string
  state: string
  gameType: GameType
}

export type ScoreCategory = 'carte' | 'denari' | 'primiera'
