<template>
  <div class="deck-selector">
    <label
      v-for="deck in decks"
      :key="deck.id"
      class="deck-option"
      :class="{ selected: selectedDeck === deck.id }"
      @click="selectDeck(deck.id)"
    >
      <img :src="`/assets/cards/${deck.id}/1d.${deck.ext}`" :alt="deck.label">
      <span>{{ deck.label }}</span>
    </label>
  </div>
</template>

<script setup lang="ts">
import { useDeckStyle } from '@/composables/useDeckStyle'
import type { DeckStyle } from '@/types/card'

const { selectedDeck, setDeckStyle } = useDeckStyle()

const decks = [
  { id: 'piacentine' as DeckStyle, label: 'Piacentine', ext: 'jpg' },
  { id: 'napoletane' as DeckStyle, label: 'Napoletane', ext: 'jpg' },
  { id: 'toscane' as DeckStyle, label: 'Toscane', ext: 'png' },
  { id: 'siciliane' as DeckStyle, label: 'Siciliane', ext: 'png' },
]

function selectDeck(id: DeckStyle) {
  setDeckStyle(id)
}
</script>
