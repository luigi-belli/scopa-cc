import { ref, type Ref } from 'vue'
import type { DeckStyle } from '@/types/card'

interface UseDeckStyleReturn {
  selectedDeck: Ref<DeckStyle>
  setDeckStyle: (style: DeckStyle) => void
  deckStyle: Ref<DeckStyle>
}

const STORAGE_KEY = 'scopa-deck-style'
const VALID_STYLES: readonly DeckStyle[] = ['piacentine', 'napoletane', 'toscane', 'siciliane'] as const

const selectedDeck = ref<DeckStyle>(loadSaved())

function loadSaved(): DeckStyle {
  const saved = localStorage.getItem(STORAGE_KEY)
  if (saved && VALID_STYLES.includes(saved as DeckStyle)) {
    return saved as DeckStyle
  }
  return 'piacentine'
}

export function useDeckStyle(): UseDeckStyleReturn {
  function setDeckStyle(style: DeckStyle) {
    selectedDeck.value = style
    localStorage.setItem(STORAGE_KEY, style)
  }

  return {
    selectedDeck,
    setDeckStyle,
    // Backward-compatible alias
    deckStyle: selectedDeck,
  }
}
