import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './routes'
import { setupGuards } from './guards'
import { scrollBehavior } from './scrollBehavior'
import { installChunkErrorRecovery } from './chunkErrorRecovery'

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior,
})

setupGuards(router)
installChunkErrorRecovery(router)
