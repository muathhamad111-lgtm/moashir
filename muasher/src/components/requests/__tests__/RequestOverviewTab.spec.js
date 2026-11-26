import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import RequestOverviewTab from '../RequestOverviewTab.vue'

// Request page v4.1 (layout A): the overview holds the «بيانات الطلب» card with this state's details, and the result
// of a completed request that has no level (one with a level shows it in the ribbon above the tabs).
function render(detail) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(['request_follower']))
  return mount(RequestOverviewTab, { props: { detail }, global: { plugins: globals.plugins } })
}

const BASE = {
  id: 9104,
  path: 'accredited',
  status: 'completed',
  entity: { id: 6001, name: { en: 'Namaa' }, reference: 'ENT-006001', level: 'silver' },
  record: { result: { total: 666, maxTotal: 1000, percent: 66.6 }, level: null },
  assignmentHistory: [{ id: 1 }],
  completionRequests: [],
  payment: { paid: true, details: null },
}

function termsOf(wrapper) {
  return wrapper.findAll('.record-facts__term').map((term) => term.text())
}

describe('RequestOverviewTab', () => {
  it('shows the request data and the result of a completed request without a level', () => {
    const wrapper = render(BASE)
    expect(wrapper.find('.request-overview__data h2').text()).toBe('Request data')
    expect(termsOf(wrapper)).toEqual(["Entity's current level", 'Fee', 'Assignments'])
    expect(wrapper.find('[data-testid="request-result"]').text()).toContain('66.6%')
    expect(wrapper.find('[data-testid="request-result"]').text()).toContain('666 of 1000')
  })

  it('leaves the result to the ribbon once the request has a level', () => {
    const wrapper = render({ ...BASE, record: { ...BASE.record, level: 'silver' } })
    expect(wrapper.find('[data-testid="request-result"]').exists()).toBe(false)
  })

  it('keeps the completion requests out of the overview, in their own tab', () => {
    const wrapper = render({
      ...BASE,
      status: 'needs-completion',
      completionRequests: [{ id: 2, status: 'open', items: [], notes: 'Attach the statements.' }],
    })
    expect(wrapper.find('[data-testid="request-result"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Attach the statements.')
    expect(termsOf(wrapper)).toContain('Completion requests')
  })

  it('drops the fee when the payment tab shows it', () => {
    const wrapper = render({ ...BASE, payment: { paid: true, details: { reference: 'PAY-1' } } })
    expect(termsOf(wrapper)).not.toContain('Fee')
  })
})
