import { describe, expect, it, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import DashboardIndex from '@/views/dashboard/Index.vue'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { dashboardService } from '@/services/dashboardService'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/dashboardService', () => ({
  dashboardService: {
    getBoard: vi.fn(() => new Promise(() => {})),
    getOverview: vi.fn(() => new Promise(() => {})),
  },
}))

function mountWithRoles(roles) {
  const globals = createTestGlobals({ locale: 'ar' })
  const auth = useAuthStore(globals.pinia)
  auth.setUser({ name: { ar: 'مستخدم', en: 'User' }, roles })
  return shallowMount(DashboardIndex, { global: { plugins: globals.plugins } })
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('dashboard entry (Index.vue)', () => {
  it('renders the staff dashboard for every staff account', () => {
    ;[['system_admin'], ['request_follower', 'filtering_officer'], ['entity', 'assessor']].forEach(
      (roles) => {
        const wrapper = mountWithRoles(roles)
        expect(wrapper.findComponent({ name: 'StaffDashboard' }).exists()).toBe(true)
        expect(wrapper.find('.dashboard__title').text()).toContain('مستخدم')
      },
    )
  })

  it('greets a staff user without a name without a placeholder word', () => {
    const globals = createTestGlobals({ locale: 'ar' })
    useAuthStore(globals.pinia).setUser({ name: null, roles: ['system_admin'] })
    const wrapper = shallowMount(DashboardIndex, { global: { plugins: globals.plugins } })
    expect(wrapper.find('.dashboard__title').text()).toBe('أهلًا بك')
  })

  it('renders the journey home (not a staff board) for an entity account', () => {
    const wrapper = mountWithRoles(['entity'])
    expect(wrapper.findComponent({ name: 'JourneyHome' }).exists()).toBe(true)
    expect(wrapper.find('.dashboard__title').exists()).toBe(false)
    expect(dashboardService.getBoard).not.toHaveBeenCalled()
  })

  it('shows the platform overview after the actions to permission holders only, in place of the admin board', () => {
    ;[
      [['system_admin'], true],
      [['finance_officer'], false],
      [['request_follower', 'assessor'], false],
    ].forEach(([roles, shows]) => {
      const globals = createTestGlobals({ locale: 'ar' })
      useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
      const wrapper = shallowMount(DashboardIndex, {
        global: { plugins: globals.plugins, renderStubDefaultSlot: true },
      })
      const staff = wrapper.findComponent({ name: 'StaffDashboard' })
      expect(wrapper.findComponent({ name: 'ExecutiveOverview' }).exists()).toBe(shows)
      expect(staff.exists()).toBe(true)
      expect(staff.props('coveredBoards')).toEqual(shows ? ['system_admin'] : [])
    })
  })
})
