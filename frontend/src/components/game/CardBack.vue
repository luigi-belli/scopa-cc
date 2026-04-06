<template>
  <div class="card-back">
    <img :src="imagePath" alt="Card back" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { DeckStyle } from '@/types/card'
import { cardBackPath } from '@/types/card'

const props = defineProps<{
  deckStyle: DeckStyle
}>()

const imagePath = computed(() => cardBackPath(props.deckStyle))
</script>
