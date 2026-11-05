import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import RequestActionsCard from '../RequestActionsCard.vue'
import AssignmentHistoryList from '../AssignmentHistoryList.vue'
import RequestQueueFilters from '../RequestQueueFilters.vue'
import ReviewNotices from '@/components/review/ReviewNotices.vue'

function render(component, props, { roles = [], extra = {} } = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles, extra))
  return mount(component, { props, global: { plugins: globals.plugins } })
}

function labels(wrapper) {
  return wrapper.findAll('button').map((button) => button.text())
}

describe('RequestActionsCard', () => {
  it('shows assignment to the follower and hides it from the assessor', () => {
    const detail = { id: 9105, path: 'accredited', status: 'submitted', assignmentHistory: [] }
    expect(
      labels(
        render(RequestActionsCard, { detail, canAssign: true }, { roles: ['request_follower'] }),
      ),
    ).toEqual(['Assign an assessor', 'Download all evidence'])
    expect(
      labels(render(RequestActionsCard, { detail, canAssign: true }, { roles: ['assessor'] })),
    ).toEqual(['Download all evidence'])
  })

  it('shows reopen and status change to the Super Admin only', () => {
    const detail = {
      id: 9102,
      path: 'accredited',
      status: 'completed',
      assignmentHistory: [{ id: 1 }],
    }
    expect(
      labels(render(RequestActionsCard, { detail }, { roles: ['system_admin'] })),
    ).not.toContain('Reopen the request')
    const superAdmin = labels(
      render(
        RequestActionsCard,
        { detail },
        {
          roles: [],
          extra: {
            isSuperAdmin: true,
            permissions: ['requests.assign', 'requests.review.read', 'evidence.view'],
          },
        },
      ),
    )
    expect(superAdmin).toEqual([
      'Open the review',
      'Download all evidence',
      'Reopen the request',
      'Change the status',
    ])
    const refunded = labels(
      render(
        RequestActionsCard,
        { detail: { id: 9103, path: 'accredited', status: 'refunded', assignmentHistory: [] } },
        { roles: [], extra: { isSuperAdmin: true } },
      ),
    )
    expect(refunded).not.toContain('Change the status')
  })

  it('denies an unknown permission key', () => {
    expect(
      labels(
        render(
          RequestActionsCard,
          {
            detail: { id: 9105, path: 'accredited', status: 'submitted', assignmentHistory: [] },
            canAssign: true,
          },
          { roles: [], extra: { permissions: ['requests.assign.all'] } },
        ),
      ),
    ).toEqual([])
  })
})

describe('AssignmentHistoryList and filters', () => {
  it('shows who assigned and why only when the server sends it', () => {
    const shared = {
      id: 1,
      assessor: { id: 11, name: 'أ. هند بنت إبراهيم الزهراني' },
      status: 'reassigned',
      assignedAt: '2026-10-26T07:00:00Z',
      startedAt: null,
      endedAt: '2026-10-26T11:00:00Z',
    }
    const global = render(AssignmentHistoryList, {
      history: [
        {
          ...shared,
          assignedBy: { id: 8, name: 'ياسر بن فهد الغامدي' },
          reason: 'إعادة توزيع الأحمال',
          endReason: 'forced_transition',
        },
      ],
    })
    expect(global.text()).toContain('ياسر بن فهد الغامدي')
    expect(global.text()).toContain('Status changed by the Super Admin')
    const own = render(AssignmentHistoryList, { history: [shared] })
    expect(own.text()).not.toContain('Assigned by')
    expect(render(AssignmentHistoryList, { history: [] }).text()).toContain(
      'No assignment has been recorded yet.',
    )
  })

  it('offers the statuses of the served path and the cycle filter on the advanced path only', () => {
    const filters = { path: 'accredited', cycleId: '', status: '', overdue: '', assessorId: '' }
    const cycles = [{ id: 31, name: { ar: 'دورة الجائزة', en: 'Award cycle' }, year: 2026 }]
    const accredited = render(RequestQueueFilters, {
      path: 'accredited',
      filters,
      sort: '-submitted_at',
      cycles,
    })
    expect(accredited.findAll('.dropdown')).toHaveLength(3)
    const advanced = render(RequestQueueFilters, {
      path: 'advanced',
      filters: { ...filters, path: 'advanced' },
      sort: '-submitted_at',
      cycles,
    })
    expect(advanced.findAll('.dropdown')).toHaveLength(4)
  })
})

describe('ReviewNotices', () => {
  const review = (status, mode = 'edit') => ({
    mode,
    assessment: { status, isFrozen: false },
    completionRequest: null,
  })

  it('explains the read-only view, the start and the wait for the entity', () => {
    expect(render(ReviewNotices, { review: review('under-review', 'read_only') }).text()).toContain(
      'Read-only view',
    )
    expect(render(ReviewNotices, { review: review('assigned') }).text()).toContain(
      'The review has not started',
    )
    expect(render(ReviewNotices, { review: review('needs-completion') }).text()).toContain(
      'Waiting for the entity',
    )
  })

  it('offers the score override to the Super Admin on a published result only', () => {
    expect(
      render(
        ReviewNotices,
        { review: review('completed', 'read_only') },
        { roles: ['system_admin'] },
      )
        .find('.btn--danger')
        .exists(),
    ).toBe(false)
    const superAdmin = render(
      ReviewNotices,
      { review: review('completed', 'read_only') },
      { extra: { isSuperAdmin: true } },
    )
    expect(superAdmin.find('.btn--danger').text()).toBe('Override scores')
  })
})
