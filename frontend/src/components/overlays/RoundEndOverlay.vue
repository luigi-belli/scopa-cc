<template>
  <div class="overlay overlay-enter">
    <div class="overlay-content round-summary">
      <h2>{{ t('round.end') }}</h2>
      <ScoreTable
        :scores="scores"
        :myIndex="myIndex"
        :myName="myName"
        :opponentName="opponentName"
        :globalScores="{ my: myTotalScore, opp: opponentTotalScore }"
        @rowClick="selectedCategory = $event"
      />
      <button class="btn btn-primary" :disabled="loading" @click="$emit('nextRound')">
        <span class="btn-spinner" v-if="loading"></span>
        {{ t('round.next') }}
      </button>
    </div>
    <ScoreDetailDialog
      v-if="selectedCategory"
      :category="selectedCategory"
      :scores="scores"
      :myIndex="myIndex"
      :myName="myName"
      :opponentName="opponentName"
      :deckStyle="deckStyle"
      @close="selectedCategory = null"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { RoundScores, ScoreCategory } from '@/types/game'
import type { DeckStyle } from '@/types/card'
import ScoreTable from './ScoreTable.vue'
import ScoreDetailDialog from './ScoreDetailDialog.vue'
import { useI18n } from '@/i18n'

defineProps<{
  scores: [RoundScores, RoundScores]
  myIndex: number
  myName: string
  opponentName: string
  myTotalScore: number
  opponentTotalScore: number
  deckStyle: DeckStyle
  loading?: boolean
}>()

defineEmits<{
  nextRound: []
}>()

const { t } = useI18n()

const selectedCategory = ref<ScoreCategory | null>(null)
</script>
