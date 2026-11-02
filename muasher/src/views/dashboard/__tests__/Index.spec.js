import { describe, expect, it, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import DashboardIndex from '@/views/dashboard/Index.vue'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { dashboardService } from '@/services/dashboardService'

vi.mock('@/services/dashboardService', () => ({
  dashboardService: { getSummary: vi.fn(() => new Promise(() => {})) },
}))

function mountWithRoles(roles) {
  const globals = createTestGlobals({ locale: 'ar' })
  const auth = useAuthStore(globals.pinia)
  auth.setUser({ name: { ar: 'مستخدم', en: 'User' }, roles })
  const wrapper = shallowMount(DashboardIndex, {
    global: { plugins: globals.plugins },
  })
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  dashboardService.getSummary.mockImplementation(() => new Promise(() => {}))
})

describe('dashboard registry (Index.vue)', () => {
  it('renders the admin dashboard for an admin', () => {
    const wrapper = mountWithRoles(['system_admin'])
    expect(wrapper.findComponent({ name: 'AdminDashboard' }).exists()).toBe(true)
  })

  it('renders the entity dashboard for an entity', () => {
    const wrapper = mountWithRoles(['entity'])
    expect(wrapper.findComponent({ name: 'EntityDashboard' }).exists()).toBe(true)
  })

  it('renders the follower dashboard for a follower', () => {
    const wrapper = mountWithRoles(['request_follower'])
    expect(wrapper.findComponent({ name: 'FollowerDashboard' }).exists()).toBe(true)
  })

  it('picks the highest-privilege dashboard for a multi-role user', () => {
    const wrapper = mountWithRoles(['entity', 'assessor', 'system_admin'])
    expect(wrapper.findComponent({ name: 'AdminDashboard' }).exists()).toBe(true)
    expect(wrapper.findComponent({ name: 'EntityDashboard' }).exists()).toBe(false)
  })

  it('maps filtering-officer to the follower dashboard', () => {
    const wrapper = mountWithRoles(['filtering_officer'])
    expect(wrapper.findComponent({ name: 'FollowerDashboard' }).exists()).toBe(true)
  })

  it('falls back to the entity dashboard for an unknown role', () => {
    const wrapper = mountWithRoles(['ghost'])
    expect(wrapper.findComponent({ name: 'EntityDashboard' }).exists()).toBe(true)
  })

  it('shows the role label eyebrow and greeting', () => {
    const wrapper = mountWithRoles(['award_secretary'])
    expect(wrapper.find('.dashboard__eyebrow').text()).toBeTruthy()
    expect(wrapper.find('.dashboard__title').text()).toContain('مستخدم')
  })

  it('drops the board instead of falling back to the entity board when the session ends', async () => {
    const wrapper = mountWithRoles(['system_admin'])
    useAuthStore().clearSession()
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent({ name: 'AdminDashboard' }).exists()).toBe(false)
    expect(wrapper.findComponent({ name: 'EntityDashboard' }).exists()).toBe(false)
  })
})
