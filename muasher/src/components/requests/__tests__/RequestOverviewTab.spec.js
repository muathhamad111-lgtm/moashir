import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import RequestOverviewTab from '../RequestOverviewTab.vue'

// Admin review 2026-11-20 (R2): the overview tab shows the result once the request is completed.
function render(detail) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(['request_follower']))
  return mount(RequestOverviewTab, {
    props: { detail },
    global: { plugins: globals.plugins, stubs: { RequestCertificateCard: true } },
  })
}

const BASE = {
  id: 9104,
  path: 'accredited',
  status: 'completed',
  record: { result: { total: 666, maxTotal: 1000, percent: 66.6 } },
  completionRequests: [],
  payment: { paid: true, details: null },
}

describe('RequestOverviewTab', () => {
  it('shows the result of a completed request', () => {
    const wrapper = render(BASE)
    expect(wrapper.find('[data-testid="request-result"]').text()).toContain('66.6%')
    expect(wrapper.find('[data-testid="request-result"]').text()).toContain('666 of 1000')
  })

  it('hides the result before completion and shows the open completion request', () => {
    const wrapper = render({
      ...BASE,
      status: 'needs-completion',
      completionRequests: [
        { id: 1, status: 'resubmitted', items: [], notes: 'old' },
        { id: 2, status: 'open', items: [], notes: 'Attach the audited statements.' },
      ],
    })
    expect(wrapper.find('[data-testid="request-result"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Attach the audited statements.')
  })
})
