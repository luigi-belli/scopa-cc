<template>
  <div class="turn-indicator" :class="{ active: isMyTurn }">
    {{ isMyTurn ? t('turn.mine') : t('turn.opponent') }}
  </div>
</template>

<script setup lang="ts">
import { useI18n } from '@/i18n'

defineProps<{
  isMyTurn: boolean
}>()

const { t } = useI18n()
</script>
