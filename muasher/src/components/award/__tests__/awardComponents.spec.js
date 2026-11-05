import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import AdvancedRequestCard from '../AdvancedRequestCard.vue'
import AdvancedEligibilityList from '../AdvancedEligibilityList.vue'
import CycleActionsCard from '../CycleActionsCard.vue'
import CycleStatusBadge from '../CycleStatusBadge.vue'
import FilteringDecisionDialog from '../FilteringDecisionDialog.vue'
import FilteringDecisionList from '../FilteringDecisionList.vue'
import PublicCycleCard from '../PublicCycleCard.vue'

function render(component, props, { roles = [], extra = {}, locale = 'en' } = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles, extra))
  return mount(component, { props, global: { plugins: globals.plugins }, attachTo: document.body })
}

const eligible = {
  eligible: true,
  windowOpen: true,
  conditions: [
    { rule: 'min_entity_age', passed: true, required: 3, actual: 17 },
    { rule: 'region', passed: true, required: null, actual: null },
  ],
}
const notEligible = {
  eligible: false,
  windowOpen: true,
  conditions: [
    { rule: 'min_entity_age', passed: false, required: 3, actual: 1 },
    { rule: 'region', passed: true, required: null, actual: null },
  ],
}
const cycle = {
  id: 31,
  status: 'submission-open',
  canManage: true,
  name: { ar: 'دورة', en: 'Cycle' },
  year: 2026,
}

describe('advanced entity components', () => {
  it('offers to apply only when eligible and lists the failed conditions otherwise', () => {
    const apply = render(AdvancedRequestCard, { request: null, eligibility: eligible })
    expect(apply.text()).toContain('Your entity meets the entry conditions')
    expect(apply.find('.advanced-request__actions .btn--primary').text()).toBe(
      'Apply for the award',
    )
    const refused = render(AdvancedRequestCard, { request: null, eligibility: notEligible })
    expect(refused.findAll('.advanced-request__actions button')).toHaveLength(0)
    expect(refused.find('.eligibility-list__item--failed').text()).toContain(
      'at least 3 years (your entity: 1 years)',
    )
  })

  it('shows the draft, completion, masked review and closed states without any filtering outcome', () => {
    const draft = render(AdvancedRequestCard, {
      request: { id: 9300, number: 'ADV-2026-000007', status: 'draft' },
      eligibility: eligible,
    })
    expect(draft.find('.advanced-request__link').text()).toBe('Continue the draft')
    expect(draft.find('.btn--danger').text()).toBe('Delete the draft')
    const completion = render(AdvancedRequestCard, {
      request: { id: 9201, number: 'ADV-2026-000001', status: 'needs-completion' },
      eligibility: eligible,
    })
    expect(completion.find('.advanced-request__link').text()).toBe('Complete the request')
    const review = render(AdvancedRequestCard, {
      request: {
        id: 9204,
        number: 'ADV-2026-000004',
        status: 'under-review',
        submittedAt: '2026-10-10T08:00:00Z',
      },
      eligibility: eligible,
    })
    expect(review.text()).toContain('Your request is under review')
    expect(review.text()).not.toMatch(/filtering|qualif/i)
    const rejected = render(AdvancedRequestCard, {
      request: {
        id: 9206,
        number: 'ADV-2026-000006',
        status: 'rejected-conditions',
        conditionFailures: [{ rule: 'region', passed: false }],
      },
      eligibility: { ...notEligible, eligible: false },
    })
    expect(rejected.find('.eligibility-list__item--failed').text()).toContain('region')
    expect(rejected.findAll('.advanced-request__actions button')).toHaveLength(0)
  })

  it('renders the eligibility checks with text, never color alone, in Arabic', () => {
    const list = render(
      AdvancedEligibilityList,
      { conditions: eligible.conditions, windowOpen: false },
      { locale: 'ar' },
    )
    expect(list.findAll('.eligibility-list__state').map((item) => item.text())).toEqual([
      'مستوفى',
      'مستوفى',
      'مغلقة',
    ])
  })

  it('shows the public cycle states', () => {
    expect(render(PublicCycleCard, { status: 'empty', cycle: null }).text()).toContain(
      'No award cycle is running',
    )
    const card = render(PublicCycleCard, {
      status: 'ready',
      cycle: {
        id: 31,
        name: { ar: 'دورة', en: 'Cycle' },
        year: 2026,
        isAcceptingApplications: true,
        submissionStart: '2026-10-01T00:00:00Z',
        submissionEnd: '2026-12-15T00:00:00Z',
        committeeStart: '2027-01-04T00:00:00Z',
        committeeEnd: '2027-02-15T00:00:00Z',
        announcementAt: null,
        conditions: { minEntityAgeYears: 3 },
        regions: [{ id: 1, code: 'riyadh', name: { ar: 'الرياض', en: 'Riyadh' } }],
      },
    })
    expect(card.findAll('.cycle-regions__item')).toHaveLength(1)
    expect(card.text()).toContain('Minimum entity age: 3 years')
  })
})

describe('cycle staff components', () => {
  it('offers the stage actions to managers and explains a blocked opening', () => {
    const labels = (wrapper) => wrapper.findAll('button').map((button) => button.text())
    expect(labels(render(CycleActionsCard, { cycle }, { roles: ['system_admin'] }))).toEqual([
      'Close submissions',
    ])
    expect(
      labels(
        render(
          CycleActionsCard,
          { cycle: { ...cycle, status: 'submission-closed' } },
          { roles: ['system_admin'] },
        ),
      ),
    ).toEqual(['Close the cycle'])
    const draft = render(
      CycleActionsCard,
      { cycle: { ...cycle, id: 32, status: 'draft' }, blockingCycle: cycle },
      { roles: ['system_admin'] },
    )
    expect(draft.find('.btn--primary').attributes('disabled')).toBeDefined()
    expect(draft.text()).toContain('Cycle 2026 is running')
    expect(
      labels(
        render(
          CycleActionsCard,
          { cycle: { ...cycle, canManage: false } },
          { roles: ['filtering_officer'] },
        ),
      ),
    ).toEqual([])
    expect(
      labels(
        render(
          CycleActionsCard,
          { cycle: { ...cycle, status: 'closed' }, canReopen: true },
          { roles: [] },
        ),
      ),
    ).toEqual(['Reopen the cycle'])
  })

  it('lists the requests that block the close', () => {
    const blocked = render(
      CycleActionsCard,
      {
        cycle: { ...cycle, status: 'submission-closed' },
        blocked: {
          code: 'cycle.requests_pending',
          requests: [{ id: 9203, number: 'ADV-2026-000003', status: 'awaiting-review-assignment' }],
        },
      },
      { roles: ['system_admin'] },
    )
    expect(blocked.findAll('.cycle-blocked__item')).toHaveLength(1)
    expect(blocked.find('.cycle-blocked__link').attributes('href')).toBe('/admin/requests/9203')
  })

  it('labels every cycle status', () => {
    expect(render(CycleStatusBadge, { status: 'closed' }).text()).toBe('Closed')
    expect(
      render(CycleStatusBadge, { status: 'committee-assessment' }, { locale: 'ar' }).text(),
    ).toBe('مرحلة تقييم اللجنة')
  })
})

describe('filtering components', () => {
  const row = {
    id: 9205,
    number: 'ADV-2026-000005',
    meetsThreshold: false,
    initialResult: { percent: '57.50' },
  }

  it('asks for an exception reason below the threshold and a decision reason to disqualify', async () => {
    const qualify = render(
      FilteringDecisionDialog,
      { modelValue: true, mode: 'qualify', cycleId: 31, row, threshold: '60.00' },
      { roles: ['filtering_officer'] },
    )
    await flushPromises()
    expect(document.body.textContent).toContain(
      'The initial result (57.50%) is below the qualification threshold (60.00%)',
    )
    expect(document.querySelector('textarea[name="overrideReason"]')).not.toBeNull()
    expect(document.querySelector('textarea[name="reason"]')).toBeNull()
    qualify.unmount()
    const above = render(
      FilteringDecisionDialog,
      {
        modelValue: true,
        mode: 'qualify',
        cycleId: 31,
        row: { ...row, meetsThreshold: true },
        threshold: '60.00',
      },
      { roles: [], extra: { isSuperAdmin: true } },
    )
    await flushPromises()
    expect(document.querySelector('textarea[name="overrideReason"]')).toBeNull()
    expect(document.querySelector('textarea[name="reason"]')).not.toBeNull()
    above.unmount()
    const disqualify = render(
      FilteringDecisionDialog,
      { modelValue: true, mode: 'disqualify', cycleId: 31, row, threshold: '60.00' },
      { roles: ['filtering_officer'] },
    )
    await flushPromises()
    expect(document.querySelector('textarea[name="reason"]')).not.toBeNull()
    disqualify.unmount()
  })

  it('shows the decision history with the exception badge and hides the Super Admin name', () => {
    const list = render(FilteringDecisionList, {
      decisions: [
        {
          id: 1,
          outcome: 'qualified',
          isOverride: true,
          initialPercent: '57.50',
          thresholdPercent: '60.00',
          reason: 'سبب',
          note: null,
          decidedBy: { id: null, name: null },
          decidedAt: '2026-11-02T08:00:00Z',
        },
      ],
    })
    expect(list.text()).toContain('Exception')
    expect(list.text()).toContain('Initial result 57.50%, threshold 60.00%')
    expect(render(FilteringDecisionList, { decisions: [] }).text()).toContain('No decision yet.')
  })
})
