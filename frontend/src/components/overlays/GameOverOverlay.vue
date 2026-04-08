<template>
  <div class="overlay overlay-enter">
    <div class="overlay-content gameover-content" :class="iWon ? 'winner' : 'loser'">
      <h2>{{ iWon ? t('gameover.won') : t('gameover.lost') }}</h2>
      <ScoreTable
        :scores="scores"
        :myIndex="myIndex"
        :myName="myName"
        :opponentName="opponentName"
        :globalScores="{ my: myTotalScore, opp: opponentTotalScore }"
        @rowClick="selectedCategory = $event"
      />
      <p class="gameover-final">
        {{ t('gameover.finalScore', { my: myTotalScore, opp: opponentTotalScore }) }}
      </p>
      <button class="btn btn-primary" @click="$emit('backToLobby')">
        {{ t('gameover.newGame') }}
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
import { computed, ref } from 'vue'
import type { RoundScores, ScoreCategory } from '@/types/game'
import type { DeckStyle } from '@/types/card'
import ScoreTable from './ScoreTable.vue'
import ScoreDetailDialog from './ScoreDetailDialog.vue'
import { useI18n } from '@/i18n'

const props = defineProps<{
  scores: [RoundScores, RoundScores]
  winner: number
  myIndex: number
  myName: string
  opponentName: string
  myTotalScore: number
  opponentTotalScore: number
  deckStyle: DeckStyle
}>()

defineEmits<{
  backToLobby: []
}>()

const { t } = useI18n()

const iWon = computed(() => props.winner === props.myIndex)
const selectedCategory = ref<ScoreCategory | null>(null)
</script>
