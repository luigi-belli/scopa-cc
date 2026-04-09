<template>
  <div class="disconnect-overlay" v-if="visible" role="dialog" aria-modal="true" aria-labelledby="disconnect-msg">
    <div class="disconnect-dialog">
      <p id="disconnect-msg">{{ t('effect.disconnect') }}</p>
      <button ref="okBtnRef" class="disconnect-ok-btn" @click="$emit('dismiss')">OK</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'
import { useI18n } from '@/i18n'

const props = defineProps<{
  visible: boolean
}>()

defineEmits<{
  dismiss: []
}>()

const { t } = useI18n()

const okBtnRef = ref<HTMLButtonElement | null>(null)

watch(() => props.visible, async (isVisible) => {
  if (isVisible) {
    await nextTick()
    okBtnRef.value?.focus()
  }
})
</script>
