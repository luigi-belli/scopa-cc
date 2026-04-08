export interface Card {
  suit: string
  value: number
}

export type DeckStyle = 'piacentine' | 'napoletane' | 'toscane' | 'siciliane'

export const SUIT_LETTER: Record<string, string> = {
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
