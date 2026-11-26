import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import RequestSummaryHeader from '../RequestSummaryHeader.vue'

// Request page v4.1 (layout A): one summary card — the identity with the status once at its end, then who holds
// the request and its dates for this path and state; the request's actions sit in the row above.
function render(detail, { roles = ['system_admin'], locale = 'en' } = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles))
  return mount(RequestSummaryHeader, {
    props: { detail, assignmentMode: 'reassign', refreshLogo: vi.fn() },
    global: { plugins: globals.plugins },
  })
}

const UNDER_REVIEW = {
  id: 9108,
  number: 'ACC-2026-000008',
  path: 'accredited',
  pathLabel: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
  status: 'under-review',
  isFrozen: false,
  entity: {
    id: 6002,
    name: { ar: 'جمعية إحسان', en: 'Ehsan Society' },
    reference: 'ENT-006002',
    logo: null,
  },
  assessor: { id: 10, name: 'Dr. Sultan' },
  isReassigned: true,
  assignedAt: '2026-10-26T11:00:00Z',
  submittedAt: '2026-10-25T08:00:00Z',
  dueAt: '2026-11-08T08:00:00Z',
  isOverdue: true,
  completedAt: null,
  completionDeadline: null,
  record: { createdAt: '2026-10-09T07:50:00Z', statusHistory: [{ at: '2026-10-27T06:00:00Z' }] },
  assignmentHistory: [{ id: 1 }, { id: 2 }],
  completionRequests: [],
  payment: { paid: true, details: { reference: 'PAY-2026-000019' } },
}

function groupsOf(wrapper) {
  return wrapper.findAll('.record-group').map((group) => ({
    title: group.find('h2').text(),
    terms: group.findAll('.record-facts__term').map((term) => term.text()),
  }))
}

describe('RequestSummaryHeader', () => {
  it('shows the identity, the status once with its date, and the assignment and dates groups', () => {
    const wrapper = render(UNDER_REVIEW)
    expect(wrapper.find('h1').text()).toBe('ACC-2026-000008')
    expect(wrapper.find('.record-identity__kind').text()).toBe('Accredited assessment')
    expect(wrapper.findAll('.record-status__badge')).toHaveLength(1)
    expect(wrapper.find('.record-status__since').text()).toBe('Since 2026-10-27')
    expect(groupsOf(wrapper)).toEqual([
      { title: 'Assignment', terms: ['Assessor', 'Assigned on'] },
      { title: 'Dates', terms: ['Submitted', 'Due'] },
    ])
    expect(wrapper.find('.record-summary__bar .request-actions').exists()).toBe(true)
    expect(wrapper.find('.record-summary__back').attributes('href')).toBe('/admin/requests')
  })

  it('writes the reassigned and overdue flags as text notes, not floating badges', () => {
    const wrapper = render(UNDER_REVIEW)
    const assessor = wrapper.find('[data-testid="request-assessor"]')
    expect(assessor.text()).toBe('Dr. Sultan')
    expect(wrapper.findAll('.record-fact__note').map((note) => note.text())).toEqual([
      'Reassigned',
      'Overdue',
    ])
    expect(wrapper.find('.record-facts .badge').exists()).toBe(false)
  })

  it('opens the entity record for staff who view entities and names it for the others', () => {
    const staff = render(UNDER_REVIEW, { locale: 'ar' })
    expect(staff.find('[data-testid="request-entity"]').attributes('href')).toBe(
      '/admin/entities/6002',
    )
    expect(staff.find('.record-identity__subject').text()).toContain('ENT-006002')
    const other = render(UNDER_REVIEW, { roles: [] })
    expect(other.find('[data-testid="request-entity"]').element.tagName).toBe('SPAN')
  })

  it('shows the entity logo the API sends, and the building mark without one', () => {
    const withLogo = render({
      ...UNDER_REVIEW,
      entity: { ...UNDER_REVIEW.entity, logo: { id: 'f1', url: 'https://files.test/logo.png' } },
    })
    expect(withLogo.find('.entity-head-logo__image').attributes('src')).toBe(
      'https://files.test/logo.png',
    )
    expect(render(UNDER_REVIEW).find('.entity-head-logo__image').exists()).toBe(false)
  })

  it('keeps only the dates group for a draft and adds the frozen note under the status', () => {
    const wrapper = render({
      ...UNDER_REVIEW,
      status: 'draft',
      isFrozen: true,
      assessor: null,
      isReassigned: false,
      submittedAt: null,
      dueAt: null,
      assignedAt: null,
      record: {
        createdAt: '2026-09-03T07:00:00Z',
        updatedAt: '2026-09-04T07:00:00Z',
        statusHistory: [],
      },
    })
    expect(groupsOf(wrapper)).toEqual([{ title: 'Dates', terms: ['Opened on', 'Last updated'] }])
    expect(wrapper.find('.record-status .record-fact__note--danger').text()).toBe('Frozen')
  })

  it('shows no award stage on an advanced request while the award build is on hold', () => {
    const wrapper = render({
      ...UNDER_REVIEW,
      number: 'ADV-2026-000002',
      path: 'advanced',
      status: 'under-initial-assessment',
    })
    expect(wrapper.text()).not.toMatch(/award stage/i)
    expect(groupsOf(wrapper).map((group) => group.title)).toEqual(['Assignment', 'Dates'])
  })
})
