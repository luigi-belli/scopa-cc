<template>
  <div class="card" :class="{ playable, glow, 'captured-glow': capturedGlow }" :style="cardStyle">
    <img :src="imagePath" :alt="`${card.value} di ${card.suit}`" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Card, DeckStyle } from '@/types/card'
import { cardImagePath } from '@/types/card'

const props = defineProps<{
  card: Card
  deckStyle: DeckStyle
  playable?: boolean
  glow?: boolean
  capturedGlow?: boolean
  style?: Record<string, string>
}>()

const imagePath = computed(() => cardImagePath(props.card, props.deckStyle))

const cardStyle = computed(() => props.style || {})
</script>
