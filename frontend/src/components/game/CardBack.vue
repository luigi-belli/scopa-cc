<template>
  <div class="card-back">
    <img :src="imagePath" :alt="t('card.back')" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { DeckStyle } from '@/types/card'
import { cardBackPath } from '@/types/card'
import { useI18n } from '@/i18n'

const props = defineProps<{
  deckStyle: DeckStyle
}>()

const { t } = useI18n()

const imagePath = computed(() => cardBackPath(props.deckStyle))
</script>
