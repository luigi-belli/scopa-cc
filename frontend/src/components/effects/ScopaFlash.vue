<template>
  <div class="scopa-flash" v-if="visible">
    <div class="scopa-flash-text">{{ t('effect.scopa') }}</div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from '@/i18n'

const { t } = useI18n()

const visible = ref(false)

function show() {
  visible.value = true
  setTimeout(() => {
    visible.value = false
  }, 1500)
}

defineExpose({ show })
</script>
