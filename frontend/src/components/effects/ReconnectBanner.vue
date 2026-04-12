<template>
  <div class="reconnect-banner" v-if="visible">
    <span class="reconnect-spinner"></span>
    {{ t('effect.reconnecting') }}
  </div>
</template>

<script setup lang="ts">
import { useI18n } from '@/i18n'

defineProps<{
  visible: boolean
}>()

const { t } = useI18n()
</script>
