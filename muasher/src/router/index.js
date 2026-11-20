import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './routes'
import { setupGuards } from './guards'
import { scrollBehavior } from './scrollBehavior'
import { installChunkErrorRecovery } from './chunkErrorRecovery'
import { installNavigationState } from './navigationState'

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior,
})

// First, so the page counts as loading while the guards below still wait on the session.
installNavigationState(router)
setupGuards(router)
installChunkErrorRecovery(router)
