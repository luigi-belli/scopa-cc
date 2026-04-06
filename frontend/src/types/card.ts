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
