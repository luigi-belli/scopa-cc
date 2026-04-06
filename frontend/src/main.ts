import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import './css/style.css'
import './css/cards.css'
import './css/animations.css'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'lobby',
      component: () => import('./components/screens/LobbyScreen.vue'),
    },
    {
      path: '/waiting/:gameId',
      name: 'waiting',
      component: () => import('./components/screens/WaitingScreen.vue'),
      props: true,
    },
    {
      path: '/game/:gameId',
      name: 'game',
      component: () => import('./components/screens/GameScreen.vue'),
      props: true,
    },
  ],
})

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
