<template>
  <div class="overlay overlay-enter">
    <div class="overlay-content gameover-content" :class="iWon ? 'winner' : 'loser'">
      <h2>{{ iWon ? 'Hai Vinto! 🎉' : 'Hai Perso' }}</h2>
      <ScoreTable
        :scores="scores"
        :myIndex="myIndex"
        :myName="myName"
        :opponentName="opponentName"
        :globalScores="{ my: myTotalScore, opp: opponentTotalScore }"
      />
      <p class="gameover-final">
        Punteggio finale: {{ myTotalScore }} - {{ opponentTotalScore }}
      </p>
      <button class="btn btn-primary" @click="$emit('backToLobby')">
        Nuova partita
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { RoundScores } from '@/types/game'
import ScoreTable from './ScoreTable.vue'

const props = defineProps<{
  scores: [RoundScores, RoundScores]
  winner: number
  myIndex: number
  myName: string
  opponentName: string
  myTotalScore: number
  opponentTotalScore: number
}>()

defineEmits<{
  backToLobby: []
}>()

const iWon = computed(() => props.winner === props.myIndex)
</script>
