import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  build: {
    modulePreload: { polyfill: false },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  test: {
    globals: false,
    environment: 'node',
  },
  server: {
    proxy: {
      '/api': 'http://localhost:8080',
      '/.well-known/mercure': {
        target: 'http://localhost:3000',
        headers: { 'X-Accel-Buffering': 'no' },
      },
    },
  },
})
