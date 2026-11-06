import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { installSessionExpiry } from '@/plugins/sessionExpiry'
import { notifySessionExpired } from '@/api/sessionEvents'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'

function buildRouter() {
  const empty = { template: '<div />' }
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/auth/login', name: ROUTE_NAMES.AUTH_LOGIN, component: empty },
      {
        path: '/dashboard',
        name: ROUTE_NAMES.APP_DASHBOARD,
        component: empty,
        meta: { requiresAuth: true },
      },
      { path: '/', name: 'home', component: empty },
    ],
  })
}

beforeEach(() => setActivePinia(createPinia()))

describe('session expiry', () => {
  it('clears the user and sends a signed-in page back to login with the redirect', async () => {
    const router = buildRouter()
    await router.push('/dashboard')
    const stop = installSessionExpiry(router)
    const auth = useAuthStore()
    auth.setUser({ id: 1, roles: [], permissions: [] })
    notifySessionExpired()
    await router.isReady()
    await new Promise((resolve) => setTimeout(resolve))
    expect(auth.isAuthenticated).toBe(false)
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.AUTH_LOGIN)
    expect(router.currentRoute.value.query.redirect).toBe('/dashboard')
    stop()
  })

  it('ignores the event when nobody is signed in', async () => {
    const router = buildRouter()
    await router.push('/')
    const stop = installSessionExpiry(router)
    notifySessionExpired()
    await new Promise((resolve) => setTimeout(resolve))
    expect(router.currentRoute.value.path).toBe('/')
    stop()
  })
})
