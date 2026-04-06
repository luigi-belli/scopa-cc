<template>
  <div class="waiting">
    <h2>In attesa dell'avversario...</h2>
    <div class="spinner"></div>
    <p style="color: #a8c8a0;">Condividi il nome della partita con il tuo avversario</p>
    <button class="btn btn-secondary" @click="handleBack">Torna alla lobby</button>
  </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useGameStore } from '@/stores/gameStore'
import { useApi } from '@/composables/useApi'
import { useMercure } from '@/composables/useMercure'

const props = defineProps<{
  gameId: string
}>()

const router = useRouter()
const store = useGameStore()
const api = useApi()

const { connect, disconnect } = useMercure(props.gameId, {
  onGameState(data) {
    store.commitState(data)
    router.push({ name: 'game', params: { gameId: props.gameId } })
  },
})

let heartbeatInterval: ReturnType<typeof setInterval>

onMounted(() => {
  connect(0)  // Player who creates game is always player 0
  heartbeatInterval = setInterval(() => {
    api.heartbeat(props.gameId).catch(() => {})
  }, 10000)
})

onUnmounted(() => {
  disconnect()
  clearInterval(heartbeatInterval)
})

function handleBack() {
  api.leaveGame(props.gameId).catch(() => {})
  store.$reset()
  router.push({ name: 'lobby' })
}
</script>
