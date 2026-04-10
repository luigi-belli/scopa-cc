<template>
  <div class="deck-visual-wrap" :class="{ empty: count === 0 }" ref="wrapEl">
    <div class="deck-visual" ref="deckEl">
      <CardBack :deckStyle="deckStyle" />
    </div>
    <span class="deck-count" v-if="count > 0">{{ count }}</span>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { DeckStyle } from '@/types/card'
import CardBack from './CardBack.vue'

defineProps<{
  deckStyle: DeckStyle
  count: number
}>()

const wrapEl = ref<HTMLElement>()
const deckEl = ref<HTMLElement>()
defineExpose({ wrapEl, deckEl })
</script>
