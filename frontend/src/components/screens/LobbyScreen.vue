<template>
  <div class="screen active">
    <div class="lobby-container">
      <h1 class="game-title">{{ t('lobby.title') }}</h1>

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
            @input="savePreferences"
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
          <label>{{ t('lobby.gameType') }}</label>
          <div class="language-selector">
            <button
              class="lang-btn" :class="{ selected: gameType === 'scopa' }"
              @click="gameType = 'scopa'; savePreferences()"
            >{{ t('lobby.gameType.scopa') }}</button>
            <button
              class="lang-btn" :class="{ selected: gameType === 'briscola' }"
              @click="gameType = 'briscola'; savePreferences()"
            >{{ t('lobby.gameType.briscola') }}</button>
            <button
              class="lang-btn" :class="{ selected: gameType === 'tressette' }"
              @click="gameType = 'tressette'; savePreferences()"
            >{{ t('lobby.gameType.tressette') }}</button>
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
            @input="savePreferences"
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
import { useCardPreloader } from '@/composables/useCardPreloader'
import { setMercureCookie } from '@/composables/useMercure'
import { useGameStore } from '@/stores/gameStore'
import { useI18n } from '@/i18n'
import DeckSelector from '@/components/lobby/DeckSelector.vue'
import type { GameType } from '@/types/game'

const router = useRouter()
const api = useApi()
const { selectedDeck } = useDeckStyle()
useCardPreloader(selectedDeck)
const store = useGameStore()
const { t, locale, setLocale } = useI18n()

const playerName = ref('')
const gameName = ref('')
const gameType = ref<GameType>('scopa')
const error = ref('')

onMounted(() => {
  // If there's an active game session, resume it instead of showing lobby
  if (store.restoreSession() && store.gameId) {
    router.replace({ name: 'game', params: { gameId: store.gameId } })
    return
  }
  store.$reset()
  const savedName = localStorage.getItem('scopa-player-name')
  if (savedName) playerName.value = savedName
  const savedGameName = localStorage.getItem('scopa-game-name')
  if (savedGameName) gameName.value = savedGameName
  const savedGameType = localStorage.getItem('scopa-game-type')
  if (savedGameType === 'scopa' || savedGameType === 'briscola' || savedGameType === 'tressette') gameType.value = savedGameType
})

function savePreferences() {
  localStorage.setItem('scopa-player-name', playerName.value.trim())
  localStorage.setItem('scopa-game-name', gameName.value.trim())
  localStorage.setItem('scopa-game-type', gameType.value)
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
      selectedDeck.value,
      gameType.value
    )
    store.setGame(result.gameId, result.playerToken, 0)
    if (result.mercureToken) setMercureCookie(result.mercureToken)
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
    if (result.mercureToken) setMercureCookie(result.mercureToken)
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
      selectedDeck.value,
      gameType.value
    )
    store.setGame(result.gameId, result.playerToken, 0)
    if (result.mercureToken) setMercureCookie(result.mercureToken)
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
