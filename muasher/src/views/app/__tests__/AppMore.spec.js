import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AppMore from '@/views/app/AppMore.vue'
import { useAuthStore } from '@/stores/auth'
import { ROLES } from '@/constants/roles'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

const toggleLocale = vi.fn()
vi.mock('@/composables/useLocale', () => ({ useLocale: () => ({ toggleLocale }) }))

vi.mock('@/services/dashboardService', () => ({
  dashboardService: { getActionCenter: vi.fn().mockResolvedValue({ data: [], generatedAt: null }) },
}))

async function factory(user) {
  const globals = createTestGlobals({ locale: 'en' })
  useAuthStore(globals.pinia).setUser(user)
  await globals.router.push({ name: ROUTE_NAMES.APP_MORE })
  const wrapper = mount(AppMore, { global: globals })
  await flushPromises()
  return wrapper
}

const navIds = (wrapper) =>
  wrapper.findAll('[data-nav]').map((entry) => entry.attributes('data-nav'))

describe('AppMore', () => {
  it('lists only the allowed destinations the tab bar does not hold', async () => {
    const wrapper = await factory(sessionUserFor([ROLES.REQUEST_FOLLOWER]))
    const ids = navIds(wrapper)
    expect(ids).not.toContain('requests')
    expect(ids).not.toContain('dashboard')
    expect(ids).not.toContain('admin-users')
    expect(ids).not.toContain('finance')
  })

  it('holds nothing for an account without permissions besides its account entries', async () => {
    const wrapper = await factory(sessionUserFor([ROLES.REQUEST_FOLLOWER], { permissions: [] }))
    expect(navIds(wrapper)).toEqual([])
    expect(wrapper.find('[data-testid="app-more-logout"]').exists()).toBe(true)
  })

  it('names the account and its roles, the Super Admin included', async () => {
    const wrapper = await factory(sessionUserFor([], { isSuperAdmin: true, name: 'Test Admin' }))
    expect(wrapper.text()).toContain('Test Admin')
    expect(wrapper.text()).toContain('Super Admin')
  })

  it('switches the language from the menu', async () => {
    const wrapper = await factory(sessionUserFor([ROLES.ASSESSOR]))
    await wrapper.find('[data-testid="app-more-locale"]').trigger('click')
    expect(toggleLocale).toHaveBeenCalledOnce()
  })
})
