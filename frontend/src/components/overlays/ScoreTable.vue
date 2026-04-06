<template>
  <div class="score-table" v-html="tableHtml"></div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { RoundScores } from '@/types/game'

const props = defineProps<{
  scores: [RoundScores, RoundScores]
  myIndex: number
  myName: string
  opponentName: string
  globalScores?: { my: number; opp: number }
}>()

function esc(s: string | number): string {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function row(label: string, v1: number | string, v2: number | string): string {
  const c1 = v1 > v2 ? 'winner-cell' : ''
  const c2 = v2 > v1 ? 'winner-cell' : ''
  return `<tr><td>${esc(label)}</td><td class="${c1}">${esc(v1)}</td><td class="${c2}">${esc(v2)}</td></tr>`
}

const tableHtml = computed(() => {
  const my = props.scores[props.myIndex]
  const opp = props.scores[props.myIndex === 0 ? 1 : 0]

  const sbRow = (() => {
    const c1 = my.setteBello ? 'winner-cell' : ''
    const c2 = opp.setteBello ? 'winner-cell' : ''
    const v1 = my.setteBello ? '✓' : '✗'
    const v2 = opp.setteBello ? '✓' : '✗'
    return `<tr><td>Sette Bello</td><td class="${c1}">${v1}</td><td class="${c2}">${v2}</td></tr>`
  })()

  const myTotal = my.carte + my.denari + my.setteBello + my.primiera + my.scope
  const oppTotal = opp.carte + opp.denari + opp.setteBello + opp.primiera + opp.scope

  const globalRow = props.globalScores
    ? `<tr class="total-row"><td>Punteggio</td><td>${props.globalScores.my}</td><td>${props.globalScores.opp}</td></tr>`
    : ''

  return `<table>
    <tr><th></th><th>${esc(props.myName)}</th><th>${esc(props.opponentName)}</th></tr>
    ${row('Carte', my.carte, opp.carte)}
    ${row('Denari', my.denari, opp.denari)}
    ${sbRow}
    ${row('Primiera', my.primiera || '-', opp.primiera || '-')}
    ${row('Scope', my.scope, opp.scope)}
    <tr class="total-row"><td>Totale turno</td><td>${myTotal}</td><td>${oppTotal}</td></tr>
    ${globalRow}
  </table>`
})
</script>
