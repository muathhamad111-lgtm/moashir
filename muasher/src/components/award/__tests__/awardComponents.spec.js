import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { createTestGlobals } from '@/test-utils'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'
import CycleActionsCard from '../CycleActionsCard.vue'
import CycleStatusBadge from '../CycleStatusBadge.vue'
import FilteringDecisionDialog from '../FilteringDecisionDialog.vue'
import FilteringDecisionList from '../FilteringDecisionList.vue'
import PublicCycleCard from '../PublicCycleCard.vue'
import AwardCycleChooser from '../AwardCycleChooser.vue'

function render(component, props, { roles = [], extra = {}, locale = 'en' } = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(sessionUserFor(roles, extra))
  return mount(component, { props, global: { plugins: globals.plugins }, attachTo: document.body })
}

const cycle = {
  id: 31,
  status: 'submission-open',
  canManage: true,
  name: { ar: 'دورة', en: 'Cycle' },
  year: 2026,
}

describe('public award intro components', () => {
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
    ).toEqual(['Start the committee assessment', 'Close the cycle'])
    const draft = render(
      CycleActionsCard,
      {
        cycle: {
          ...cycle,
          id: 32,
          status: 'draft',
          openBlock: { code: 'cycle.another_open', concurrency: 'single', cycle },
        },
        openBlock: { code: 'cycle.another_open', concurrency: 'single', cycle },
      },
      { roles: ['system_admin'] },
    )
    expect(draft.find('.btn--primary').attributes('disabled')).toBeDefined()
    expect(draft.text()).toContain('Cycle 2026 is running')
    expect(draft.text()).toContain('allows one running cycle at a time')
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

  it('words a blocked opening after the active concurrency setting and never blocks in concurrent mode', () => {
    const staggered = render(
      CycleActionsCard,
      {
        cycle: { ...cycle, id: 32, status: 'draft' },
        openBlock: { code: 'cycle.another_open_for_submission', concurrency: 'staggered', cycle },
      },
      { roles: ['system_admin'] },
    )
    expect(staggered.text()).toContain('Applications are open in Cycle 2026')
    expect(staggered.find('.btn--primary').attributes('disabled')).toBeDefined()
    const arabic = render(
      CycleActionsCard,
      {
        cycle: { ...cycle, id: 32, status: 'draft' },
        openBlock: { code: 'cycle.another_open_for_submission', concurrency: 'staggered', cycle },
      },
      { roles: ['system_admin'], locale: 'ar' },
    )
    expect(arabic.text()).toContain('التقديم مفتوح في دورة 2026')
    const concurrent = render(
      CycleActionsCard,
      { cycle: { ...cycle, id: 32, status: 'draft' }, openBlock: null },
      { roles: ['system_admin'] },
    )
    expect(concurrent.text()).not.toContain('Submissions cannot open yet')
    expect(concurrent.find('.btn--primary').attributes('disabled')).toBeUndefined()
    const refused = render(
      CycleActionsCard,
      {
        cycle: { ...cycle, id: 32, status: 'draft' },
        blocked: { code: 'cycle.another_open_for_submission', cycleId: 31 },
      },
      { roles: ['system_admin'] },
    )
    expect(refused.text()).toContain('Another cycle is open for applications')
  })

  it('lists the open cycles to choose from with their submission windows', async () => {
    const choices = [
      {
        id: 41,
        name: { ar: 'دورة الشمال', en: 'North cycle' },
        year: 2026,
        submissionStart: '2026-11-01T00:00:00Z',
        submissionEnd: '2026-12-01T00:00:00Z',
      },
      {
        id: 40,
        name: { ar: 'دورة الجنوب', en: 'South cycle' },
        year: 2026,
        submissionStart: '2026-10-20T00:00:00Z',
        submissionEnd: '2026-11-30T00:00:00Z',
      },
    ]
    const chooser = render(AwardCycleChooser, { choices, modelValue: 41 })
    expect(chooser.findAll('input[type="radio"]')).toHaveLength(2)
    expect(chooser.find('[data-testid="award-cycle-choice-41"]').element.checked).toBe(true)
    expect(chooser.text()).toContain('North cycle')
    expect(chooser.text()).toContain('Applications from 2026-10-20 to 2026-11-30')
    await chooser.find('[data-testid="award-cycle-choice-40"]').setValue(true)
    expect(chooser.emitted('update:modelValue')).toEqual([[40]])
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
  })
})
