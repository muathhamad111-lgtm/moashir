import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AppTopBarPhone from '@/components/layout/AppTopBarPhone.vue'
import { useAuthStore } from '@/stores/auth'
import { ROLES } from '@/constants/roles'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/dashboardService', () => ({
  dashboardService: { getActionCenter: vi.fn().mockResolvedValue({ data: [], generatedAt: null }) },
}))

async function factory(at) {
  const globals = createTestGlobals({ locale: 'en' })
  useAuthStore(globals.pinia).setUser(sessionUserFor([ROLES.REQUEST_FOLLOWER]))
  await globals.router.push(at)
  const wrapper = mount(AppTopBarPhone, { global: globals })
  await flushPromises()
  return { wrapper, router: globals.router }
}

describe('AppTopBarPhone', () => {
  it('shows the logo, linking to the dashboard, on a tab screen', async () => {
    const { wrapper } = await factory({ name: ROUTE_NAMES.REQUESTS_INDEX })
    expect(wrapper.find('.navbar__phone-logo').attributes('alt')).toBeTruthy()
    expect(wrapper.find('[data-testid="phone-back"]').exists()).toBe(false)
  })

  it('shows back and the screen title below a tab, the title hidden from screen readers', async () => {
    const { wrapper } = await factory({ name: ROUTE_NAMES.REQUESTS_DETAIL, params: { id: 9105 } })
    expect(wrapper.find('[data-testid="phone-back"]').attributes('aria-label')).toBe('Back')
    const title = wrapper.find('.navbar__phone-title')
    expect(title.text()).not.toBe('')
    expect(title.attributes('aria-hidden')).toBe('true')
  })

  it('opens the owning tab when there is no in-app history, never leaving the platform', async () => {
    const { wrapper, router } = await factory({
      name: ROUTE_NAMES.REQUESTS_DETAIL,
      params: { id: 9105 },
    })
    window.history.replaceState({}, '')
    const back = vi.spyOn(router, 'back')
    await wrapper.find('[data-testid="phone-back"]').trigger('click')
    await flushPromises()
    expect(back).not.toHaveBeenCalled()
    expect(router.currentRoute.value.name).toBe(ROUTE_NAMES.REQUESTS_INDEX)
  })

  it('steps back when the router recorded an in-app previous screen', async () => {
    const { wrapper, router } = await factory({
      name: ROUTE_NAMES.REQUESTS_DETAIL,
      params: { id: 9105 },
    })
    window.history.replaceState({ back: '/admin/requests' }, '')
    const back = vi.spyOn(router, 'back').mockImplementation(() => {})
    await wrapper.find('[data-testid="phone-back"]').trigger('click')
    expect(back).toHaveBeenCalledOnce()
  })
})
