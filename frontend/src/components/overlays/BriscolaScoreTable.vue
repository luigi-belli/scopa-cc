<template>
  <div class="score-table">
    <table>
      <tr>
        <th></th>
        <th>{{ myName }}</th>
        <th>{{ opponentName }}</th>
      </tr>
      <tr class="score-row-clickable" @click="showDetail = true">
        <td>{{ t('briscola.score.points') }}</td>
        <td :class="{ 'winner-cell': myTotalScore > oppTotalScore }">{{ myTotalScore }}</td>
        <td :class="{ 'winner-cell': oppTotalScore > myTotalScore }">{{ oppTotalScore }}</td>
      </tr>
    </table>
  </div>

  <div v-if="showDetail" class="score-detail-backdrop" @click.self="showDetail = false">
    <div class="score-detail-dialog" role="dialog" :aria-label="t('briscola.score.cards')">
      <button class="score-detail-close" :aria-label="t('dialog.close')" @click="showDetail = false">&times;</button>
      <h3>{{ t('briscola.score.cards') }}</h3>
      <div class="score-detail-columns">
        <div class="score-detail-col">
          <div class="score-detail-col-header">{{ myName }}</div>
          <div class="score-detail-cards">
            <div
              v-for="(card, i) in mySorted"
              :key="`my-${card.suit}-${card.value}-${i}`"
              class="score-detail-card-wrapper"
            >
              <img
                :src="cardImagePath(card, deckStyle)"
                :alt="t('card.alt', { value: card.value, suit: t(`suit.${card.suit}`) })"
                class="score-detail-card"
              />
              <span class="primiera-badge">{{ BRISCOLA_CARD_POINTS[card.value] ?? 0 }}</span>
            </div>
          </div>
        </div>
        <div class="score-detail-divider"></div>
        <div class="score-detail-col">
          <div class="score-detail-col-header">{{ opponentName }}</div>
          <div class="score-detail-cards">
            <div
              v-for="(card, i) in oppSorted"
              :key="`opp-${card.suit}-${card.value}-${i}`"
              class="score-detail-card-wrapper"
            >
              <img
                :src="cardImagePath(card, deckStyle)"
                :alt="t('card.alt', { value: card.value, suit: t(`suit.${card.suit}`) })"
                class="score-detail-card"
              />
              <span class="primiera-badge">{{ BRISCOLA_CARD_POINTS[card.value] ?? 0 }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import type { Card, DeckStyle } from '@/types/card'
import { cardImagePath, BRISCOLA_CARD_POINTS } from '@/types/card'
import { useI18n } from '@/i18n'

const props = defineProps<{
  capturedCards: [Card[], Card[]]
  myIndex: number
  myName: string
  opponentName: string
  myTotalScore: number
  oppTotalScore: number
  deckStyle: DeckStyle
}>()

const { t } = useI18n()

const showDetail = ref(false)

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape' && showDetail.value) showDetail.value = false
}
onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))

const myCards = computed(() => props.capturedCards[props.myIndex])
const oppCards = computed(() => props.capturedCards[1 - props.myIndex])

/** Sort cards by Briscola point value descending, then by card value descending. Exclude 0-point cards. */
function sortByPoints(cards: Card[]): Card[] {
  return [...cards]
    .filter((c) => (BRISCOLA_CARD_POINTS[c.value] ?? 0) > 0)
    .sort((a, b) => {
      const pa = BRISCOLA_CARD_POINTS[a.value] ?? 0
      const pb = BRISCOLA_CARD_POINTS[b.value] ?? 0
      if (pa !== pb) return pb - pa
      return b.value - a.value
    })
}

const mySorted = computed(() => sortByPoints(myCards.value))
const oppSorted = computed(() => sortByPoints(oppCards.value))
</script>
