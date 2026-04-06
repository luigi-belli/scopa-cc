<template>
  <div class="disconnect-banner" v-if="visible">
    L'avversario si è disconnesso. Hai vinto!
  </div>
</template>

<script setup lang="ts">
defineProps<{
  visible: boolean
}>()
</script>
