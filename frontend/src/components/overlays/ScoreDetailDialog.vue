<template>
  <div class="score-detail-backdrop" @click.self="$emit('close')">
    <div class="score-detail-dialog" role="dialog" :aria-label="title">
      <button class="score-detail-close" :aria-label="t('dialog.close')" @click="$emit('close')">&times;</button>
      <h3>{{ title }}</h3>
      <div class="score-detail-columns">
        <div class="score-detail-col">
          <div class="score-detail-col-header">{{ myName }}</div>
          <div class="score-detail-cards">
            <div
              v-for="(card, i) in myCards"
              :key="`my-${card.suit}-${card.value}-${i}`"
              class="score-detail-card-wrapper"
            >
              <img
                :src="cardImagePath(card, deckStyle)"
                :alt="t('card.alt', { value: card.value, suit: t(`suit.${card.suit}`) })"
                class="score-detail-card"
              />
              <span v-if="category === 'primiera'" class="primiera-badge">
                {{ PRIMIERA_VALUES[card.value] }}
              </span>
            </div>
          </div>
          <div v-if="category === 'primiera' && myCards.length > 0" class="primiera-total">
            = {{ myPrimieraTotal }}
          </div>
        </div>
        <div class="score-detail-divider"></div>
        <div class="score-detail-col">
          <div class="score-detail-col-header">{{ opponentName }}</div>
          <div class="score-detail-cards">
            <div
              v-for="(card, i) in oppCards"
              :key="`opp-${card.suit}-${card.value}-${i}`"
              class="score-detail-card-wrapper"
            >
              <img
                :src="cardImagePath(card, deckStyle)"
                :alt="t('card.alt', { value: card.value, suit: t(`suit.${card.suit}`) })"
                class="score-detail-card"
              />
              <span v-if="category === 'primiera'" class="primiera-badge">
                {{ PRIMIERA_VALUES[card.value] }}
              </span>
            </div>
          </div>
          <div v-if="category === 'primiera' && oppCards.length > 0" class="primiera-total">
            = {{ oppPrimieraTotal }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import type { Card, DeckStyle } from '@/types/card'
import type { RoundScores, ScoreCategory } from '@/types/game'
import { cardImagePath, PRIMIERA_VALUES } from '@/types/card'
import { useI18n } from '@/i18n'

const props = defineProps<{
  category: ScoreCategory
  scores: [RoundScores, RoundScores]
  myIndex: number
  myName: string
  opponentName: string
  deckStyle: DeckStyle
}>()

const emit = defineEmits<{
  close: []
}>()

const { t } = useI18n()

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape') emit('close')
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))

const my = computed(() => props.scores[props.myIndex])
const opp = computed(() => props.scores[1 - props.myIndex])

const title = computed((): string => t(`score.${props.category}`))

function getCardsForCategory(score: RoundScores, category: ScoreCategory): Card[] {
  switch (category) {
    case 'carte': return score.carteCards
    case 'denari': return score.denariCards
    case 'primiera': return score.primieraCards
  }
}

const myCards = computed(() => getCardsForCategory(my.value, props.category))
const oppCards = computed(() => getCardsForCategory(opp.value, props.category))

const myPrimieraTotal = computed(() =>
  myCards.value.reduce((sum, c) => sum + (PRIMIERA_VALUES[c.value] ?? 0), 0),
)
const oppPrimieraTotal = computed(() =>
  oppCards.value.reduce((sum, c) => sum + (PRIMIERA_VALUES[c.value] ?? 0), 0),
)
</script>
