import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import RequestHeaderActions from '../RequestHeaderActions.vue'
import AppMenu from '@/components/ui/AppMenu.vue'
import RequestAssignButton from '../RequestAssignButton.vue'
import AssignmentHistoryList from '../AssignmentHistoryList.vue'
import RequestQueueFilters from '../RequestQueueFilters.vue'
import AppSelect from '@/components/form/AppSelect.vue'
import ReviewNotices from '@/components/review/ReviewNotices.vue'

function render(component, props, { roles = [], extra = {} } = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles, extra))
  return mount(component, { props, global: { plugins: globals.plugins } })
}

// Admin review 2026-11-20 (R2): every request action sits at the end of the page header.
function menuLabels(wrapper) {
  const menu = wrapper.findComponent(AppMenu)
  return menu.exists() ? menu.props('actions').map((action) => action.label) : []
}

function buttonLabels(wrapper) {
  return wrapper
    .findAll('.request-actions > button, .request-actions > a')
    .map((button) => button.text())
}

describe('RequestHeaderActions', () => {
  it('puts the assignment first for a request waiting for an assessor', () => {
    const detail = { id: 9105, path: 'accredited', status: 'submitted', assignmentHistory: [] }
    const wrapper = render(
      RequestHeaderActions,
      { detail, assignmentMode: 'assign' },
      { roles: ['request_follower'] },
    )
    expect(buttonLabels(wrapper)).toEqual(['Assign an assessor', 'Download all evidence'])
    expect(wrapper.find('[data-testid="request-assign"]').classes()).toContain('btn--primary')
  })

  it('opens the review first and keeps the reassignment beside it while the review runs', () => {
    const detail = {
      id: 9108,
      path: 'accredited',
      status: 'under-review',
      assignmentHistory: [{ id: 1, status: 'active', assessor: { id: 10 } }],
    }
    const wrapper = render(
      RequestHeaderActions,
      { detail, assignmentMode: 'reassign' },
      { roles: ['request_follower'] },
    )
    expect(buttonLabels(wrapper)).toEqual(['Open the review', 'Reassign', 'Download all evidence'])
  })

  it('names the review button for the assignee by the stage, «Open the review» for others', () => {
    const history = (assessorId) => [{ id: 1, status: 'active', assessor: { id: assessorId } }]
    const reviewButton = (status, assessorId, roles) =>
      render(
        RequestHeaderActions,
        {
          detail: { id: 9107, path: 'accredited', status, assignmentHistory: history(assessorId) },
        },
        { roles },
      )
        .find('[data-testid="request-open-review"]')
        .text()
    expect(reviewButton('assigned', 7001, ['assessor'])).toBe('Start the assessment')
    expect(reviewButton('under-review', 7001, ['assessor'])).toBe('Continue the assessment')
    expect(reviewButton('needs-completion', 7001, ['assessor'])).toBe('Continue the assessment')
    expect(reviewButton('completed', 7001, ['assessor'])).toBe('View the assessment')
    expect(reviewButton('under-review', 10, ['request_follower'])).toBe('Open the review')
  })

  it('keeps the Super Admin actions in the menu, after the others', () => {
    const detail = {
      id: 9102,
      path: 'accredited',
      status: 'completed',
      assignmentHistory: [{ id: 1 }],
    }
    expect(
      menuLabels(render(RequestHeaderActions, { detail }, { roles: ['system_admin'] })),
    ).toEqual([])
    const superAdmin = render(
      RequestHeaderActions,
      { detail },
      {
        roles: [],
        extra: {
          isSuperAdmin: true,
          permissions: ['requests.assign', 'requests.review.read', 'evidence.view'],
        },
      },
    )
    expect(buttonLabels(superAdmin)).toEqual(['Open the review', 'Download all evidence'])
    expect(menuLabels(superAdmin)).toEqual([
      'Override scores',
      'Reopen the request',
      'Change the status',
    ])
    const refunded = render(
      RequestHeaderActions,
      { detail: { id: 9103, path: 'accredited', status: 'refunded', assignmentHistory: [] } },
      { roles: [], extra: { isSuperAdmin: true } },
    )
    expect(menuLabels(refunded)).not.toContain('Change the status')
  })

  it('opens the review in the override mode from «Override scores» (design §B.3.5)', async () => {
    const globals = createTestGlobals({ locale: 'en' })
    setActivePinia(globals.pinia)
    useAuthStore(globals.pinia).setUser(
      sessionUserFor([], { isSuperAdmin: true, permissions: ['requests.review.read'] }),
    )
    const detail = { id: 9102, path: 'accredited', status: 'completed', assignmentHistory: [] }
    const wrapper = mount(RequestHeaderActions, { props: { detail }, global: globals })
    wrapper.findComponent(AppMenu).vm.$emit('select', 'override')
    await vi.waitFor(() =>
      expect(globals.router.currentRoute.value.query).toEqual({ override: '1' }),
    )
    expect(globals.router.currentRoute.value.path).toBe('/admin/requests/9102/review')
  })

  it('offers return and withdraw from the menu when the request allows them', async () => {
    const wrapper = render(
      RequestHeaderActions,
      {
        detail: {
          id: 9203,
          path: 'advanced',
          status: 'awaiting-assignment',
          assignmentHistory: [],
        },
        canReturn: true,
        canWithdraw: true,
      },
      { roles: ['request_follower'] },
    )
    expect(menuLabels(wrapper)).toEqual(['Return for completion', 'Withdraw the request'])
    wrapper.findComponent(AppMenu).vm.$emit('select', 'withdraw')
    expect(wrapper.emitted('withdraw')).toHaveLength(1)
  })

  it('denies an unknown permission key', () => {
    const wrapper = render(
      RequestHeaderActions,
      { detail: { id: 9105, path: 'accredited', status: 'submitted', assignmentHistory: [] } },
      { roles: [], extra: { permissions: ['requests.assign.all'] } },
    )
    expect(buttonLabels(wrapper)).toEqual([])
    expect(menuLabels(wrapper)).toEqual([])
  })
})

describe('RequestAssignButton', () => {
  it('labels a first assignment and a reassignment and emits the mode', async () => {
    const assign = render(RequestAssignButton, { mode: 'assign' })
    const reassign = render(RequestAssignButton, { mode: 'reassign' })
    expect([assign.text(), reassign.text()]).toEqual(['Assign an assessor', 'Reassign'])
    expect(assign.classes()).toContain('btn--primary')
    await reassign.trigger('click')
    expect(reassign.emitted('assign')).toEqual([['reassign']])
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
    const filters = {
      path: 'accredited',
      stage: '',
      cycleId: '',
      status: '',
      overdue: '',
      assessorId: '',
    }
    const cycles = [{ id: 31, name: { ar: 'دورة الجائزة', en: 'Award cycle' }, year: 2026 }]
    const accredited = render(RequestQueueFilters, {
      path: 'accredited',
      filters,
      sort: '-submitted_at',
      cycles,
    })
    expect(accredited.findAll('.dropdown-field')).toHaveLength(4)
    const advanced = render(RequestQueueFilters, {
      path: 'advanced',
      filters: { ...filters, path: 'advanced' },
      sort: '-submitted_at',
      cycles,
    })
    expect(advanced.findAll('.dropdown-field')).toHaveLength(5)
  })

  // Operator note 2026-11-22: the stage moved from chips above the table into the filter panel.
  it('offers every stage with its count first in the filter panel', async () => {
    const filters = {
      path: 'accredited',
      stage: 'overdue',
      status: '',
      overdue: '',
      assessorId: '',
    }
    const stageCounts = {
      all: 8,
      preparing: 1,
      awaiting_assignment: 1,
      in_progress: 3,
      overdue: 2,
      closed: 1,
    }
    const wrapper = render(RequestQueueFilters, {
      path: 'accredited',
      filters,
      sort: '-submitted_at',
      stageCounts,
    })
    const stage = wrapper.findAllComponents(AppSelect)[0]
    expect(stage.props('label')).toBe('Request stage')
    expect(stage.props('modelValue')).toBe('overdue')
    expect(stage.props('options')).toEqual([
      { value: '', label: 'All (8)' },
      { value: 'preparing', label: 'Being prepared (1)' },
      { value: 'awaiting_assignment', label: 'Awaiting assignment (1)' },
      { value: 'in_progress', label: 'In progress (3)' },
      { value: 'overdue', label: 'Overdue (2)' },
      { value: 'closed', label: 'Closed (1)' },
    ])
    stage.vm.$emit('update:modelValue', 'closed')
    expect(wrapper.emitted('filter')).toEqual([['stage', 'closed']])
    const bare = render(RequestQueueFilters, { path: 'accredited', filters, sort: '-submitted_at' })
    expect(bare.findAllComponents(AppSelect)[0].props('options')[5].label).toBe('Closed')
  })
})

describe('ReviewNotices', () => {
  const review = (status, mode = 'edit') => ({
    mode,
    assessment: { status, isFrozen: false },
    completionRequest: null,
  })

  it('puts the start button inside the not-started notice for the assignee only', async () => {
    const assignee = render(ReviewNotices, { review: review('assigned'), canStart: true })
    const start = assignee.find('[data-testid="review-start"]')
    expect(start.text()).toBe('Start the assessment')
    await start.trigger('click')
    expect(assignee.emitted('start')).toHaveLength(1)
    expect(
      render(ReviewNotices, { review: review('assigned') })
        .find('[data-testid="review-start"]')
        .exists(),
    ).toBe(false)
  })

  it('explains the read-only view, the start and the wait for the entity', () => {
    expect(render(ReviewNotices, { review: review('under-review', 'read_only') }).text()).toContain(
      'Read-only view',
    )
    expect(render(ReviewNotices, { review: review('assigned') }).text()).toContain(
      'The assessment has not started',
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
