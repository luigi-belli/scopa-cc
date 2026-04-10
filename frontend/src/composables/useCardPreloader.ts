import { watch, type Ref } from 'vue'
import { type DeckStyle, SUIT_LETTER, cardImagePath, cardBackPath } from '@/types/card'

const preloaded = new Set<DeckStyle>()

const SUITS = Object.keys(SUIT_LETTER)
const VALUES = Array.from({ length: 10 }, (_, i) => i + 1)

function preloadDeck(style: DeckStyle): void {
  if (preloaded.has(style)) return
  preloaded.add(style)

  const urls: string[] = [cardBackPath(style)]

  for (const suit of SUITS) {
    for (const value of VALUES) {
      urls.push(cardImagePath({ suit, value }, style))
    }
  }

  for (const url of urls) {
    const img = new Image()
    img.src = url
  }
}

export function useCardPreloader(deckStyle: Ref<DeckStyle>): void {
  preloadDeck(deckStyle.value)
  watch(deckStyle, (style) => preloadDeck(style))
}
