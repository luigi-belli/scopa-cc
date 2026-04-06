<template>
  <div class="turn-indicator" :class="{ active: isMyTurn }">
    {{ isMyTurn ? 'Il tuo turno' : 'Turno avversario' }}
  </div>
</template>

<script setup lang="ts">
defineProps<{
  isMyTurn: boolean
}>()
</script>
