import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import RequestFactGroups from '../RequestFactGroups.vue'

// The review page's «الطلب» tab (request page v4.1): the whole request in the record's titled groups.
function render(detail, roles = ['assessor']) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
  return mount(RequestFactGroups, { props: { detail }, global: { plugins: globals.plugins } })
}

const DETAIL = {
  path: 'accredited',
  status: 'under-review',
  isFrozen: true,
  entity: { id: 6002, name: { en: 'Ehsan Society' }, reference: 'ENT-006002', level: 'gold' },
  assessor: { id: 10, name: 'Dr. Sultan' },
  isReassigned: true,
  assignedAt: '2026-10-26T11:00:00Z',
  submittedAt: '2026-10-25T08:00:00Z',
  dueAt: '2026-11-08T08:00:00Z',
  isOverdue: false,
  record: { statusHistory: [] },
  assignmentHistory: [{ id: 1 }],
  completionRequests: [],
  payment: { paid: true, details: null },
}

describe('RequestFactGroups', () => {
  it('lists the request, its assignment, dates and details as titled groups', () => {
    const wrapper = render(DETAIL)
    expect(wrapper.findAll('.record-group h2').map((title) => title.text())).toEqual([
      'Request',
      'Assignment',
      'Dates',
      'Other details',
    ])
    const entity = wrapper.find('[data-testid="request-entity"]')
    expect(entity.text()).toBe('Ehsan Society')
    expect(wrapper.text()).toContain('ENT-006002')
    expect(wrapper.findAll('.level-badge')).toHaveLength(1)
    expect(wrapper.find('[data-testid="request-assessor"]').text()).toBe('Dr. Sultan')
    expect(wrapper.text()).toContain('Reassigned')
    expect(wrapper.find('.record-fact__note--danger').text()).toBe('Frozen')
  })

  it('links the entity only for staff who view entities', () => {
    expect(
      render(DETAIL, ['system_admin']).find('a[data-testid="request-entity"]').attributes('href'),
    ).toBe('/admin/entities/6002')
    expect(render(DETAIL, []).find('a[data-testid="request-entity"]').exists()).toBe(false)
  })
})
