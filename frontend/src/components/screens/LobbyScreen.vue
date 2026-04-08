<template>
  <div class="screen active">
    <div class="lobby-container">
      <h1 class="game-title">{{ t('lobby.title') }}</h1>
      <p class="subtitle">{{ t('lobby.subtitle') }}</p>

      <div class="lobby-form">
        <div class="form-group">
          <label for="player-name">{{ t('lobby.playerName') }}</label>
          <input
            id="player-name"
            v-model="playerName"
            type="text"
            :placeholder="t('lobby.playerNamePlaceholder')"
            maxlength="30"
            autocomplete="off"
            @input="saveName"
          >
        </div>

        <div class="form-group">
          <label>{{ t('lobby.language') }}</label>
          <div class="language-selector">
            <button
              class="lang-btn" :class="{ selected: locale === 'it' }"
              @click="setLocale('it')"
            >🇮🇹 Italiano</button>
            <button
              class="lang-btn" :class="{ selected: locale === 'en' }"
              @click="setLocale('en')"
            >🇬🇧 English</button>
          </div>
        </div>

        <div class="form-group">
          <label>{{ t('lobby.deckStyle') }}</label>
          <DeckSelector />
        </div>

        <div class="lobby-divider"><span>{{ t('lobby.twoPlayers') }}</span></div>

        <div class="form-group">
          <label for="game-name">{{ t('lobby.gameName') }}</label>
          <input
            id="game-name"
            v-model="gameName"
            type="text"
            :placeholder="t('lobby.gameNamePlaceholder')"
            maxlength="60"
            autocomplete="off"
            @keydown.enter="createGame"
          >
        </div>

        <div class="lobby-buttons">
          <button class="btn btn-primary" @click="createGame">{{ t('lobby.newGame') }}</button>
          <button class="btn btn-secondary" @click="joinGame">{{ t('lobby.join') }}</button>
        </div>

        <div class="lobby-divider"><span>{{ t('lobby.onePlayer') }}</span></div>

        <button class="btn btn-single" @click="startSinglePlayer">
          {{ t('lobby.playClaude') }}
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
import { useI18n } from '@/i18n'
import DeckSelector from '@/components/lobby/DeckSelector.vue'

const router = useRouter()
const api = useApi()
const { selectedDeck } = useDeckStyle()
const store = useGameStore()
const { t, locale, setLocale } = useI18n()

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
    error.value = t('lobby.errorNameRequired')
    return false
  }
  if (requireGameName && !gameName.value.trim()) {
    error.value = t('lobby.errorGameNameRequired')
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
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : String(e)
  }
}

async function joinGame() {
  if (!validate()) return
  try {
    const lookup = await api.lookupGame(gameName.value.trim())
    if (lookup.length === 0) {
      error.value = t('lobby.errorGameNotFound')
      return
    }
    const result = await api.joinGame(lookup[0].id, playerName.value.trim())
    store.setGame(result.gameId, result.playerToken, 1)
    // Don't commitState here — let GameScreen run the deal animation
    store.pendingState = result.gameState
    router.push({ name: 'game', params: { gameId: result.gameId } })
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : String(e)
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
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : String(e)
  }
}
</script>
