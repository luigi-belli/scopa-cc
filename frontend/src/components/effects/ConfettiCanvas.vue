<template>
  <canvas ref="canvas" class="confetti-canvas"></canvas>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

const canvas = ref<HTMLCanvasElement>()
let animId = 0
let particles: Array<{
  x: number; y: number; vx: number; vy: number
  color: string; size: number; rotation: number; rv: number
}> = []

function start() {
  if (!canvas.value) return
  const ctx = canvas.value.getContext('2d')
  if (!ctx) return

  canvas.value.width = window.innerWidth
  canvas.value.height = window.innerHeight

  const colors = ['#ffd700', '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ff8a65']

  for (let i = 0; i < 150; i++) {
    particles.push({
      x: Math.random() * canvas.value.width,
      y: -20 - Math.random() * 200,
      vx: (Math.random() - 0.5) * 4,
      vy: Math.random() * 3 + 2,
      color: colors[Math.floor(Math.random() * colors.length)],
      size: Math.random() * 8 + 4,
      rotation: Math.random() * 360,
      rv: (Math.random() - 0.5) * 10,
    })
  }

  function animate() {
    if (!ctx || !canvas.value) return
    ctx.clearRect(0, 0, canvas.value.width, canvas.value.height)

    particles.forEach(p => {
      p.x += p.vx
      p.y += p.vy
      p.vy += 0.05
      p.rotation += p.rv

      ctx.save()
      ctx.translate(p.x, p.y)
      ctx.rotate((p.rotation * Math.PI) / 180)
      ctx.fillStyle = p.color
      ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6)
      ctx.restore()
    })

    particles = particles.filter(p => p.y < (canvas.value?.height ?? 2000) + 50)

    if (particles.length > 0) {
      animId = requestAnimationFrame(animate)
    }
  }

  animate()
}

onUnmounted(() => {
  cancelAnimationFrame(animId)
})

defineExpose({ start })
</script>
