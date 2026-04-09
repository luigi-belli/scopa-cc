<template>
  <div class="overlay overlay-enter">
    <div class="overlay-content gameover-content" :class="iWon ? 'winner' : (isDraw ? '' : 'loser')">
      <h2 v-if="isDraw">{{ t('briscola.draw') }}</h2>
      <h2 v-else>{{ iWon ? t('gameover.won') : t('gameover.lost') }}</h2>

      <!-- Scopa: detailed score table -->
      <ScoreTable
        v-if="gameType === 'scopa' && scores"
        :scores="scores"
        :myIndex="myIndex"
        :myName="myName"
        :opponentName="opponentName"
        :globalScores="{ my: myTotalScore, opp: opponentTotalScore }"
        @rowClick="selectedCategory = $event"
      />

      <!-- Briscola: detailed point breakdown -->
      <BriscolaScoreTable
        v-if="gameType === 'briscola' && capturedCards"
        :capturedCards="capturedCards"
        :myIndex="myIndex"
        :myName="myName"
        :opponentName="opponentName"
        :myTotalScore="myTotalScore"
        :oppTotalScore="opponentTotalScore"
        :deckStyle="deckStyle"
      />

      <p v-if="gameType !== 'briscola'" class="gameover-final">
        {{ t('gameover.finalScore', { my: myTotalScore, opp: opponentTotalScore }) }}
      </p>
      <button class="btn btn-primary" @click="$emit('backToLobby')">
        {{ t('gameover.newGame') }}
      </button>
    </div>
    <ScoreDetailDialog
      v-if="gameType === 'scopa' && selectedCategory && scores"
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
import type { RoundScores, ScoreCategory, GameType } from '@/types/game'
import type { Card, DeckStyle } from '@/types/card'
import ScoreTable from './ScoreTable.vue'
import ScoreDetailDialog from './ScoreDetailDialog.vue'
import BriscolaScoreTable from './BriscolaScoreTable.vue'
import { useI18n } from '@/i18n'

const props = defineProps<{
  scores: [RoundScores, RoundScores] | null
  winner: number
  myIndex: number
  myName: string
  opponentName: string
  myTotalScore: number
  opponentTotalScore: number
  deckStyle: DeckStyle
  gameType: GameType
  capturedCards: [Card[], Card[]] | null
}>()

defineEmits<{
  backToLobby: []
}>()

const { t } = useI18n()

const iWon = computed(() => props.winner === props.myIndex)
const isDraw = computed(() => props.gameType === 'briscola' && props.myTotalScore === props.opponentTotalScore)
const selectedCategory = ref<ScoreCategory | null>(null)
</script>
