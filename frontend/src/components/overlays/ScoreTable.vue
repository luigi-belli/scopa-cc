<template>
  <div class="score-table">
    <table>
      <tr>
        <th></th>
        <th>{{ myName }}</th>
        <th>{{ opponentName }}</th>
      </tr>
      <tr class="score-row-clickable" @click="$emit('rowClick', 'carte')">
        <td>{{ t('score.carte') }}</td>
        <td :class="{ 'winner-cell': my.carte > opp.carte }">
          {{ my.carteCount }}{{ my.carte > 0 ? ' \u2022' : '' }}
        </td>
        <td :class="{ 'winner-cell': opp.carte > my.carte }">
          {{ opp.carteCount }}{{ opp.carte > 0 ? ' \u2022' : '' }}
        </td>
      </tr>
      <tr class="score-row-clickable" @click="$emit('rowClick', 'denari')">
        <td>{{ t('score.denari') }}</td>
        <td :class="{ 'winner-cell': my.denari > opp.denari }">
          {{ my.denariCount }}{{ my.denari > 0 ? ' \u2022' : '' }}
        </td>
        <td :class="{ 'winner-cell': opp.denari > my.denari }">
          {{ opp.denariCount }}{{ opp.denari > 0 ? ' \u2022' : '' }}
        </td>
      </tr>
      <tr>
        <td>{{ t('score.setteBello') }}</td>
        <td :class="{ 'winner-cell': my.hasSetteBello }">
          {{ my.hasSetteBello ? '\u2713' : '\u2717' }}
        </td>
        <td :class="{ 'winner-cell': opp.hasSetteBello }">
          {{ opp.hasSetteBello ? '\u2713' : '\u2717' }}
        </td>
      </tr>
      <tr class="score-row-clickable" @click="$emit('rowClick', 'primiera')">
        <td>{{ t('score.primiera') }}</td>
        <td :class="{ 'winner-cell': my.primiera > opp.primiera }">
          {{ my.primieraValue != null ? my.primieraValue : '\u2014' }}{{ my.primiera > 0 ? ' \u2022' : '' }}
        </td>
        <td :class="{ 'winner-cell': opp.primiera > my.primiera }">
          {{ opp.primieraValue != null ? opp.primieraValue : '\u2014' }}{{ opp.primiera > 0 ? ' \u2022' : '' }}
        </td>
      </tr>
      <tr>
        <td>{{ t('score.scope') }}</td>
        <td :class="{ 'winner-cell': my.scope > opp.scope }">
          {{ my.scope }}{{ my.scope > 0 ? ' \u2022' : '' }}
        </td>
        <td :class="{ 'winner-cell': opp.scope > my.scope }">
          {{ opp.scope }}{{ opp.scope > 0 ? ' \u2022' : '' }}
        </td>
      </tr>
      <tr class="total-row">
        <td>{{ t('score.roundTotal') }}</td>
        <td>{{ myTotal }}</td>
        <td>{{ oppTotal }}</td>
      </tr>
      <tr v-if="globalScores" class="total-row">
        <td>{{ t('score.total') }}</td>
        <td>{{ globalScores.my }}</td>
        <td>{{ globalScores.opp }}</td>
      </tr>
    </table>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { RoundScores, ScoreCategory } from '@/types/game'
import { useI18n } from '@/i18n'

const props = defineProps<{
  scores: [RoundScores, RoundScores]
  myIndex: number
  myName: string
  opponentName: string
  globalScores?: { my: number; opp: number }
}>()

defineEmits<{
  rowClick: [category: ScoreCategory]
}>()

const { t } = useI18n()

const my = computed(() => props.scores[props.myIndex])
const opp = computed(() => props.scores[1 - props.myIndex])
const myTotal = computed(() => my.value.carte + my.value.denari + my.value.setteBello + my.value.primiera + my.value.scope)
const oppTotal = computed(() => opp.value.carte + opp.value.denari + opp.value.setteBello + opp.value.primiera + opp.value.scope)
</script>
