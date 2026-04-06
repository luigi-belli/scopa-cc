<template>
  <div class="deck-selector">
    <label
      v-for="deck in decks"
      :key="deck.id"
      class="deck-option"
      :class="{ selected: selectedDeck === deck.id }"
      @click="selectDeck(deck.id)"
    >
      <img :src="`/assets/cards/${deck.id}/1d.${deck.ext}`" :alt="t('deck.' + deck.id)">
      <span>{{ t('deck.' + deck.id) }}</span>
    </label>
  </div>
</template>

<script setup lang="ts">
import { useDeckStyle } from '@/composables/useDeckStyle'
import { useI18n } from '@/i18n'
import type { DeckStyle } from '@/types/card'
import { DECK_EXT } from '@/types/card'

const { selectedDeck, setDeckStyle } = useDeckStyle()
const { t } = useI18n()

const DECK_IDS: readonly DeckStyle[] = ['piacentine', 'napoletane', 'toscane', 'siciliane'] as const

const decks = DECK_IDS.map(id => ({ id, ext: DECK_EXT[id] }))

function selectDeck(id: DeckStyle): void {
  setDeckStyle(id)
}
</script>
