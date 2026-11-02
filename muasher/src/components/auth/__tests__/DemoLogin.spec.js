import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import DemoLogin from '@/components/auth/DemoLogin.vue'
import { demoSessionService } from '@/services/demoSessionService'
import { useAuthStore } from '@/stores/auth'
import { DEMO_PERSONAS } from '@/constants/demoPersonas'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/demoSessionService', () => ({
  demoSessionService: { start: vi.fn() },
}))

beforeEach(() => vi.clearAllMocks())

describe('DemoLogin (local development only)', () => {
  it('lists one persona per canonical role plus the committee assessor', () => {
    const wrapper = mount(DemoLogin, { global: createTestGlobals({ locale: 'en' }) })
    expect(wrapper.findAll('button')).toHaveLength(DEMO_PERSONAS.length)
    expect(wrapper.text()).toContain('Demo login')
    expect(wrapper.text()).toContain('Committee assessor')
  })

  it('opens a mock cookie session and goes to the persona home', async () => {
    const globals = createTestGlobals({ locale: 'en' })
    demoSessionService.start.mockResolvedValue({ data: { id: 1, roles: ['system_admin'], permissions: ['dashboard.view'] } })
    const push = vi.spyOn(globals.router, 'push')
    const wrapper = mount(DemoLogin, { global: globals })
    await wrapper.findAll('button')[0].trigger('click')
    await flushPromises()
    expect(demoSessionService.start).toHaveBeenCalledWith('system_admin')
    expect(useAuthStore(globals.pinia).isAuthenticated).toBe(true)
    expect(push).toHaveBeenCalledWith({ name: ROUTE_NAMES.ADMIN_SETTINGS })
    expect(localStorage.length).toBe(0)
  })
})
