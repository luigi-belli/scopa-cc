<template>
  <div class="scopa-flash" v-if="visible">
    <div class="scopa-flash-text">SCOPA!</div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const visible = ref(false)

function show() {
  visible.value = true
  setTimeout(() => {
    visible.value = false
  }, 1500)
}

defineExpose({ show })
</script>
