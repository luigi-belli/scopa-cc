<template>
  <div class="screen active">
    <div class="lobby-container">
      <h1 class="game-title">Scopa</h1>
      <p class="subtitle">Il gioco di carte italiano</p>

      <div class="lobby-form">
        <div class="form-group">
          <label for="player-name">Il tuo nome</label>
          <input
            id="player-name"
            v-model="playerName"
            type="text"
            placeholder="Inserisci il tuo nome"
            maxlength="30"
            autocomplete="off"
            @input="saveName"
          >
        </div>

        <div class="form-group">
          <label for="game-name">Nome della partita</label>
          <input
            id="game-name"
            v-model="gameName"
            type="text"
            placeholder="Scegli un nome per la partita"
            maxlength="60"
            autocomplete="off"
            @keydown.enter="createGame"
          >
        </div>

        <div class="form-group">
          <label>Stile delle carte</label>
          <DeckSelector />
        </div>

        <div class="lobby-buttons">
          <button class="btn btn-primary" @click="createGame">Nuova Partita</button>
          <button class="btn btn-secondary" @click="joinGame">Unisciti</button>
        </div>

        <div class="lobby-divider"><span>oppure</span></div>

        <button class="btn btn-single" @click="startSinglePlayer">
          Gioca contro Claude
        </button>

        <div class="error-message">{{ error }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { useDeckStyle } from '@/composables/useDeckStyle'
import { useGameStore } from '@/stores/gameStore'
import DeckSelector from '@/components/lobby/DeckSelector.vue'

const router = useRouter()
const api = useApi()
const { selectedDeck } = useDeckStyle()
const store = useGameStore()

const playerName = ref('')
const gameName = ref('')
const error = ref('')

onMounted(() => {
  // If there's an active game session, resume it instead of showing lobby
  if (store.restoreSession() && store.gameId) {
    router.replace({ name: 'game', params: { gameId: store.gameId } })
    return
  }
  store.$reset()
  const saved = localStorage.getItem('scopa-player-name')
  if (saved) playerName.value = saved
})

function saveName() {
  localStorage.setItem('scopa-player-name', playerName.value.trim())
}

function validate(requireGameName = true): boolean {
  if (!playerName.value.trim()) {
    error.value = 'Inserisci il tuo nome.'
    return false
  }
  if (requireGameName && !gameName.value.trim()) {
    error.value = 'Inserisci il nome della partita.'
    return false
  }
  error.value = ''
  return true
}

async function createGame() {
  if (!validate()) return
  try {
    const result = await api.createGame(
      playerName.value.trim(),
      gameName.value.trim(),
      false,
      selectedDeck.value
    )
    store.setGame(result.gameId, result.playerToken, 0)
    router.push({ name: 'waiting', params: { gameId: result.gameId } })
  } catch (e: any) {
    error.value = e.message
  }
}

async function joinGame() {
  if (!validate()) return
  try {
    const lookup = await api.lookupGame(gameName.value.trim())
    if (lookup.length === 0) {
      error.value = 'Partita non trovata'
      return
    }
    const result = await api.joinGame(lookup[0].id, playerName.value.trim())
    store.setGame(result.gameId, result.playerToken, 1)
    // Don't commitState here — let GameScreen run the deal animation
    store.pendingState = result.gameState
    router.push({ name: 'game', params: { gameId: result.gameId } })
  } catch (e: any) {
    error.value = e.message
  }
}

async function startSinglePlayer() {
  if (!validate(false)) return
  try {
    const result = await api.createGame(
      playerName.value.trim(),
      null,
      true,
      selectedDeck.value
    )
    store.setGame(result.gameId, result.playerToken, 0)
    // Don't commitState here — let GameScreen run the deal animation
    if (result.gameState) {
      store.pendingState = result.gameState
    }
    router.push({ name: 'game', params: { gameId: result.gameId } })
  } catch (e: any) {
    error.value = e.message
  }
}
</script>
