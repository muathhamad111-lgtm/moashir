import { describe, expect, it, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import DashboardIndex from '@/views/dashboard/Index.vue'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { dashboardService } from '@/services/dashboardService'

vi.mock('@/services/dashboardService', () => ({
  dashboardService: { getBoard: vi.fn(() => new Promise(() => {})) },
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

  it('renders the journey home (not a staff board) for an entity account', () => {
    const wrapper = mountWithRoles(['entity'])
    expect(wrapper.findComponent({ name: 'JourneyHome' }).exists()).toBe(true)
    expect(wrapper.find('.dashboard__title').exists()).toBe(false)
    expect(dashboardService.getBoard).not.toHaveBeenCalled()
  })
})
