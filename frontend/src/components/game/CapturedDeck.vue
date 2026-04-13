<template>
  <div class="captured-deck" :class="mine ? 'mine' : 'opponent'" ref="capturedEl">
    <div class="captured-stack">
      <!-- Scopa marker cards: face-up, rotated 90°, behind the deck -->
      <div
        v-for="sc in scopaCards"
        :key="`sc-${sc.suit}-${sc.value}`"
        class="scopa-marker"
      >
        <img :src="cardImagePath(sc, deckStyle)" alt="Scopa" />
      </div>
      <!-- Main card back stack (on top of scopa markers) -->
      <CardBack v-if="count > 0" :deckStyle="deckStyle" class="captured-back" />
      <span v-if="count > 0" class="captured-count">{{ count }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { Card, DeckStyle } from '@/types/card'
import { cardImagePath } from '@/types/card'
import CardBack from './CardBack.vue'

const props = defineProps<{
  deckStyle: DeckStyle
  count: number
  mine: boolean
  scopaCards: Card[]
}>()

const capturedEl = ref<HTMLElement>()
defineExpose({ capturedEl })
</script>
