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
  contentService: { getStatus: vi.fn() },
}))

function freshRouter() {
  const router = createRouter({ history: createMemoryHistory(), routes })
  setupGuards(router)
  return router
}

function maintenanceOn() {
  contentService.getStatus.mockResolvedValue({ data: { maintenance: true, publishedAt: null } })
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
    expect(contentService.getStatus).not.toHaveBeenCalled()
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

  it.each([
    ['/verify', ROUTE_NAMES.VERIFY],
    ['/verify/9F3K-MQ2T-7XHD-M4PA', ROUTE_NAMES.VERIFY_CERTIFICATE],
    ['/terms', ROUTE_NAMES.LEGAL_TERMS],
    ['/privacy', ROUTE_NAMES.LEGAL_PRIVACY],
    ['/accessibility', ROUTE_NAMES.LEGAL_ACCESSIBILITY],
    ['/auth/forgot-password', ROUTE_NAMES.AUTH_FORGOT_PASSWORD],
    ['/auth/reset-password/a1b2c3d4e5f6', ROUTE_NAMES.AUTH_RESET_PASSWORD],
    ['/auth/invitations/invite-sarah-whitfield-2026', ROUTE_NAMES.AUTH_INVITATION],
  ])('keeps %s reachable for visitors while the homepage is unpublished', async (path, name) => {
    maintenanceOn()
    const router = freshRouter()
    await router.push(path)
    expect(router.currentRoute.value.name).toBe(name)
    expect(contentService.getStatus).not.toHaveBeenCalled()
  })

  it('still sends the other public pages to the maintenance page', async () => {
    maintenanceOn()
    const router = freshRouter()
    await router.push('/verify')
    await router.push('/lenses')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.PUBLIC_MAINTENANCE)
  })

  it('lets visitors through when the homepage is published', async () => {
    contentService.getStatus.mockResolvedValue({
      data: { maintenance: false, publishedAt: '2026-11-01T08:00:00Z' },
    })
    const router = freshRouter()
    await router.push('/')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.PUBLIC_HOME)
  })

  it('checks the status once and fails open after a retry when the API is unreachable', async () => {
    contentService.getStatus.mockRejectedValue(new Error('offline'))
    const router = freshRouter()
    await router.push('/')
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.PUBLIC_HOME)
    expect(contentService.getStatus).toHaveBeenCalledTimes(2)
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
