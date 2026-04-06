<template>
  <div class="overlay">
    <div class="overlay-content">
      <h2>Scegli la cattura</h2>
      <div class="capture-options">
        <div
          v-for="(option, idx) in options"
          :key="idx"
          class="capture-option"
          @click="$emit('select', idx)"
        >
          <CardComponent
            v-for="(card, ci) in option"
            :key="ci"
            :card="card"
            :deckStyle="deckStyle"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Card, DeckStyle } from '@/types/card'
import CardComponent from '@/components/game/CardComponent.vue'

defineProps<{
  options: Card[][]
  deckStyle: DeckStyle
}>()

defineEmits<{
  select: [index: number]
}>()
</script>
