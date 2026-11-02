import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { setupGuards } from '@/router/guards'
import { routes } from '@/router/routes'
import { useAuthStore } from '@/stores/auth'
import { useSiteStatusStore } from '@/stores/siteStatus'
import { contentService } from '@/services/contentService'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { ROLES } from '@/constants/roles'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/contentService', () => ({
  contentService: { getLanding: vi.fn() },
}))

function freshRouter() {
  const router = createRouter({ history: createMemoryHistory(), routes })
  setupGuards(router)
  return router
}

function maintenanceOn() {
  contentService.getLanding.mockResolvedValue({ data: { published: false, maintenance: true } })
}

beforeEach(() => {
  setActivePinia(createPinia())
  localStorage.clear()
  vi.clearAllMocks()
})
afterEach(() => localStorage.clear())

describe('maintenance guard (Q-96)', () => {
  it('sends visitors of public pages to the maintenance page while the homepage is unpublished', async () => {
    maintenanceOn()
    const router = freshRouter()
    await router.push('/')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.PUBLIC_MAINTENANCE)
    await router.push('/kpis')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.PUBLIC_MAINTENANCE)
  })

  it('keeps the login page reachable for staff and entities', async () => {
    maintenanceOn()
    const router = freshRouter()
    await router.push('/auth/login')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.AUTH_LOGIN)
    expect(contentService.getLanding).not.toHaveBeenCalled()
  })

  it('keeps the back office reachable for signed-in users', async () => {
    maintenanceOn()
    useAuthStore().setUser(sessionUserFor([ROLES.SYSTEM_ADMIN], { id: 'u1' }))
    const router = freshRouter()
    await router.push('/dashboard')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.APP_DASHBOARD)
    await router.push('/admin/settings/homepage')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.ADMIN_SETTINGS_HOMEPAGE)
  })

  it('lets visitors through when the homepage is published', async () => {
    contentService.getLanding.mockResolvedValue({ data: { published: true, hero: {} } })
    const router = freshRouter()
    await router.push('/')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.PUBLIC_HOME)
  })

  it('checks the status once and fails open after a retry when the API is unreachable', async () => {
    contentService.getLanding.mockRejectedValue(new Error('offline'))
    const router = freshRouter()
    await router.push('/')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.PUBLIC_HOME)
    expect(contentService.getLanding).toHaveBeenCalledTimes(2)
    expect(useSiteStatusStore().status).toBe('error')
  })

  it('follows a publication change made in the back office without a reload', async () => {
    maintenanceOn()
    const router = freshRouter()
    await router.push('/')
    useSiteStatusStore().markPublished(true)
    await router.push('/kpis')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.PUBLIC_KPIS)
  })
})

describe('branding route', () => {
  it('is forbidden for a non-admin staff member', async () => {
    useAuthStore().setUser(sessionUserFor([ROLES.CONTENT_OFFICER], { id: 'u2' }))
    const router = freshRouter()
    await router.push('/admin/branding')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.ERRORS_FORBIDDEN)
  })

  it('is reachable by the System Administrator', async () => {
    useAuthStore().setUser(sessionUserFor([ROLES.SYSTEM_ADMIN], { id: 'u3' }))
    const router = freshRouter()
    await router.push('/admin/branding')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.ADMIN_BRANDING)
  })
})
