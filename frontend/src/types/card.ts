export type Suit = 'Denari' | 'Coppe' | 'Bastoni' | 'Spade'

export interface Card {
  suit: Suit
  value: number
}

export type DeckStyle = 'piacentine' | 'napoletane' | 'toscane' | 'siciliane'

export const SUIT_LETTER: Record<Suit, string> = {
  Denari: 'd',
  Coppe: 'c',
  Bastoni: 'b',
  Spade: 's',
}

export const DECK_EXT: Record<DeckStyle, string> = {
  piacentine: 'jpg',
  napoletane: 'jpg',
  toscane: 'png',
  siciliane: 'png',
}

export function cardImagePath(card: Card, deckStyle: DeckStyle): string {
  const ext = DECK_EXT[deckStyle]
  const letter = SUIT_LETTER[card.suit]
  return `/assets/cards/${deckStyle}/${card.value}${letter}.${ext}`
}

export function cardBackPath(deckStyle: DeckStyle): string {
  const ext = DECK_EXT[deckStyle]
  return `/assets/cards/${deckStyle}/bg.${ext}`
}

export const PRIMIERA_VALUES: Record<number, number> = {
  7: 21, 6: 18, 1: 16, 5: 15,
  4: 14, 3: 13, 2: 12,
  8: 10, 9: 10, 10: 10,
}

/** Briscola card point values (Ace=11, Three=10, King=4, Knight=3, Jack=2, rest=0). */
export const BRISCOLA_CARD_POINTS: Record<number, number> = {
  1: 11, 3: 10, 10: 4, 9: 3, 8: 2,
  2: 0, 4: 0, 5: 0, 6: 0, 7: 0,
}

/** Tressette card point values (×3 integer system: Ace=3, 2/3/figures=1, rest=0). */
export const TRESSETTE_CARD_POINTS: Record<number, number> = {
  1: 3, 2: 1, 3: 1, 8: 1, 9: 1, 10: 1,
  4: 0, 5: 0, 6: 0, 7: 0,
}

/** Format a Tressette ×3 integer score as a fraction (e.g. 29 → "9 2/3"). */
export function formatTressetteScore(n: number): string {
  const whole = Math.floor(n / 3)
  const remainder = n % 3
  if (remainder === 0) return `${whole}`
  if (whole === 0) return `${remainder}/3`
  return `${whole} ${remainder}/3`
}

/** Tressette card strength for trick resolution (3 strongest, 4 weakest). */
export const TRESSETTE_CARD_STRENGTH: Record<number, number> = {
  3: 10, 2: 9, 1: 8, 10: 7, 9: 6, 8: 5, 7: 4, 6: 3, 5: 2, 4: 1,
}
