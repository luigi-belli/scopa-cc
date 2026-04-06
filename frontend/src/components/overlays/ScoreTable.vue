<template>
  <div class="score-table" v-html="tableHtml"></div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { RoundScores } from '@/types/game'
import { useI18n } from '@/i18n'

const props = defineProps<{
  scores: [RoundScores, RoundScores]
  myIndex: number
  myName: string
  opponentName: string
  globalScores?: { my: number; opp: number }
}>()

const { t } = useI18n()

function esc(s: string | number): string {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

/** Row showing actual values with winner highlight and point indicator */
function row(label: string, v1: string, v2: string, p1: number, p2: number): string {
  const c1 = p1 > p2 ? 'winner-cell' : ''
  const c2 = p2 > p1 ? 'winner-cell' : ''
  const dot1 = p1 > 0 ? ' •' : ''
  const dot2 = p2 > 0 ? ' •' : ''
  return `<tr><td>${esc(label)}</td><td class="${c1}">${esc(v1)}${dot1}</td><td class="${c2}">${esc(v2)}${dot2}</td></tr>`
}

const tableHtml = computed(() => {
  const oppIndex = 1 - props.myIndex
  const my = props.scores[props.myIndex]
  const opp = props.scores[oppIndex]

  const carteRow = row(t('score.carte'), `${my.carteCount}`, `${opp.carteCount}`, my.carte, opp.carte)
  const denariRow = row(t('score.denari'), `${my.denariCount}`, `${opp.denariCount}`, my.denari, opp.denari)

  const sbC1 = my.hasSetteBello ? 'winner-cell' : ''
  const sbC2 = opp.hasSetteBello ? 'winner-cell' : ''
  const sbRow = `<tr><td>${esc(t('score.setteBello'))}</td><td class="${sbC1}">${my.hasSetteBello ? '✓' : '✗'}</td><td class="${sbC2}">${opp.hasSetteBello ? '✓' : '✗'}</td></tr>`

  const myPrim = my.primieraValue != null ? `${my.primieraValue}` : '—'
  const oppPrim = opp.primieraValue != null ? `${opp.primieraValue}` : '—'
  const primRow = row(t('score.primiera'), myPrim, oppPrim, my.primiera, opp.primiera)

  const scopeRow = row(t('score.scope'), `${my.scope}`, `${opp.scope}`, my.scope, opp.scope)

  const myTotal = my.carte + my.denari + my.setteBello + my.primiera + my.scope
  const oppTotal = opp.carte + opp.denari + opp.setteBello + opp.primiera + opp.scope

  const globalRow = props.globalScores
    ? `<tr class="total-row"><td>${esc(t('score.total'))}</td><td>${esc(props.globalScores.my)}</td><td>${esc(props.globalScores.opp)}</td></tr>`
    : ''

  return `<table>
    <tr><th></th><th>${esc(props.myName)}</th><th>${esc(props.opponentName)}</th></tr>
    ${carteRow}
    ${denariRow}
    ${sbRow}
    ${primRow}
    ${scopeRow}
    <tr class="total-row"><td>${esc(t('score.roundTotal'))}</td><td>${esc(myTotal)}</td><td>${esc(oppTotal)}</td></tr>
    ${globalRow}
  </table>`
})
</script>
