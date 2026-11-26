import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AppTabBar from '@/components/layout/AppTabBar.vue'
import { useAuthStore } from '@/stores/auth'
import { useActionCenterStore } from '@/stores/actionCenter'
import { ROLES } from '@/constants/roles'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'
import { dashboardService } from '@/services/dashboardService'

vi.mock('@/services/dashboardService', () => ({
  dashboardService: { getActionCenter: vi.fn() },
}))

beforeEach(() => {
  dashboardService.getActionCenter.mockResolvedValue({ data: [], generatedAt: null })
})

async function factory({ roles = [], extra = {}, at = { name: ROUTE_NAMES.APP_DASHBOARD } } = {}) {
  const globals = createTestGlobals({ locale: 'ar' })
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles, extra))
  await globals.router.push(at)
  const wrapper = mount(AppTabBar, { global: globals })
  await flushPromises()
  return { wrapper, globals }
}

const tabIds = (wrapper) => wrapper.findAll('[data-tab]').map((tab) => tab.attributes('data-tab'))
const activeTab = (wrapper) => wrapper.find('[aria-current="page"]').attributes('data-tab')

describe('AppTabBar', () => {
  it('gives the request follower home, requests, kit and entities, then «المزيد»', async () => {
    const { wrapper } = await factory({ roles: [ROLES.REQUEST_FOLLOWER] })
    expect(tabIds(wrapper)).toEqual([
      'dashboard',
      'requests',
      'bag-requests',
      'admin-entities',
      'more',
    ])
    expect(wrapper.text()).toContain('الطلبات')
    expect(wrapper.text()).toContain('المزيد')
  })

  it('follows permissions, not role names: no permission, no tab (deny by default)', async () => {
    const { wrapper } = await factory({
      roles: [ROLES.REQUEST_FOLLOWER],
      extra: { permissions: ['dashboard.view'] },
    })
    expect(tabIds(wrapper)).toEqual(['dashboard', 'more'])
  })

  it('marks the tab that owns the current screen, including the screens below it', async () => {
    const { wrapper } = await factory({
      roles: [ROLES.REQUEST_FOLLOWER],
      at: { name: ROUTE_NAMES.REQUESTS_DETAIL, params: { id: 9105 } },
    })
    expect(activeTab(wrapper)).toBe('requests')
  })

  it('marks «المزيد» on a screen no tab owns', async () => {
    const { wrapper } = await factory({
      roles: [ROLES.REQUEST_FOLLOWER],
      at: { name: ROUTE_NAMES.NOTIFICATIONS_INDEX },
    })
    expect(activeTab(wrapper)).toBe('more')
  })

  it('shows pending work on its tab and names it for screen readers', async () => {
    const { wrapper, globals } = await factory({ roles: [ROLES.REQUEST_FOLLOWER] })
    useActionCenterStore(globals.pinia).items = [{ link: '/admin/requests', count: 4 }]
    await flushPromises()
    const requests = wrapper.find('[data-tab="requests"]')
    expect(requests.find('.mobile-tab-bar__count').text()).toBe('4')
    expect(requests.find('.mobile-tab-bar__sr').text()).toContain('4')
  })
})
