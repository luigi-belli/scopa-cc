<template>
  <div class="disconnect-banner" v-if="visible">
    {{ t('effect.disconnect') }}
  </div>
</template>

<script setup lang="ts">
import { useI18n } from '@/i18n'

defineProps<{
  visible: boolean
}>()

const { t } = useI18n()
</script>
