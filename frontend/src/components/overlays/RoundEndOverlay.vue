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
      />
      <button class="btn btn-primary" @click="$emit('nextRound')">
        {{ t('round.next') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { RoundScores } from '@/types/game'
import ScoreTable from './ScoreTable.vue'
import { useI18n } from '@/i18n'

defineProps<{
  scores: [RoundScores, RoundScores]
  myIndex: number
  myName: string
  opponentName: string
  myTotalScore: number
  opponentTotalScore: number
}>()

defineEmits<{
  nextRound: []
}>()

const { t } = useI18n()
</script>
