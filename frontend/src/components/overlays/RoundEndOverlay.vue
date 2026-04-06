<template>
  <div class="overlay overlay-enter">
    <div class="overlay-content round-summary">
      <h2>Fine del turno</h2>
      <ScoreTable
        :scores="scores"
        :myIndex="myIndex"
        :myName="myName"
        :opponentName="opponentName"
        :globalScores="{ my: myTotalScore, opp: opponentTotalScore }"
      />
      <button class="btn btn-primary" @click="$emit('nextRound')">
        Prossimo turno
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { RoundScores } from '@/types/game'
import ScoreTable from './ScoreTable.vue'

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
</script>
