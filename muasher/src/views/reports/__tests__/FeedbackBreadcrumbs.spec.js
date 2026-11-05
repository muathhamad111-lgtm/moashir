import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Feedback from '@/views/reports/Feedback.vue'
import { useAuthStore } from '@/stores/auth'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/feedbackReportService', () => ({
  feedbackReportService: {
    get: vi.fn(() => Promise.resolve({ data: { available: false } })),
    export: vi.fn(),
  },
}))

async function breadcrumbFor(roles) {
  const globals = createTestGlobals({ locale: 'en' })
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
  await globals.router.push({ name: ROUTE_NAMES.REPORTS_FEEDBACK, params: { assessmentId: 9104 } })
  const wrapper = mount(Feedback, { global: globals })
  await flushPromises()
  return wrapper.findAll('.portal-breadcrumb a').map((link) => link.attributes('href'))
}

describe('feedback report breadcrumbs', () => {
  it('lead the entity back to its journey and certificates', async () => {
    expect(await breadcrumbFor(['entity'])).toEqual(['/dashboard', '/certificates'])
  })

  it('lead the staff back to the request page', async () => {
    expect(await breadcrumbFor(['request_follower'])).toEqual(['/admin/requests/9104'])
  })
})
