import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import CycleTable from '@/components/award/CycleTable.vue'
import FilteringTable from '@/components/award/FilteringTable.vue'
import ResultsRowTable from '@/components/committee/ResultsRowTable.vue'
import SecretaryEvaluationTable from '@/components/committee/SecretaryEvaluationTable.vue'
import SecretaryRequestTable from '@/components/committee/SecretaryRequestTable.vue'
import CommitteeAssignmentTable from '@/components/committee/CommitteeAssignmentTable.vue'
import CommitteeMemberTable from '@/components/committee/CommitteeMemberTable.vue'
import { useAuthStore } from '@/stores/auth'
import { useTableColumnsStore } from '@/stores/tableColumns'
import { sessionUserFor } from '@/test-utils/session'
import { createTestGlobals } from '@/test-utils'

const ENTITY = {
  id: 7,
  reference: 'ENT-000123',
  name: { ar: 'جهة', en: 'Namaa' },
  status: 'active',
}
const SUBMITTED_AT = '2026-10-02T09:00:00+00:00'

// Mounts a list table and turns on the given columns, as the viewer's saved choice does.
async function mountWithColumns(component, props, tableKey, keys) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(sessionUserFor(['system_admin']))
  const wrapper = mount(component, { props, global: globals })
  await flushPromises()
  useTableColumnsStore().choices = { [tableKey]: keys }
  await flushPromises()
  return wrapper
}

const cellOf = (wrapper, label) => wrapper.find(`tbody td[data-label="${label}"]`)
const dateIn = (cell) => cell.find('time').attributes('datetime')

describe('award and committee optional columns', () => {
  it('shows the entry fee and the threshold of a cycle once chosen', async () => {
    const cycle = {
      id: 3,
      name: { ar: 'دورة', en: 'Cycle 2026' },
      year: 2026,
      status: 'submission-open',
      regions: [],
      conditions: { minEntityAgeYears: 3, disqualificationScore: '60.00' },
      entryFeeQuote: { total: '1150.00', currency: 'SAR' },
      openedAt: SUBMITTED_AT,
    }
    const wrapper = await mountWithColumns(
      CycleTable,
      { items: [cycle], status: 'success' },
      'award-cycles',
      ['status', 'entryFee', 'disqualificationScore', 'openedAt'],
    )
    expect(cellOf(wrapper, 'Entry fee').text()).toContain('1,150.00')
    expect(cellOf(wrapper, 'Qualification threshold').text()).toMatch(/^60(\.00)?%$/)
    expect(dateIn(cellOf(wrapper, 'Opened on'))).toBe(SUBMITTED_AT)
  })

  it('shows the due date, the score and the decision of a filtering row', async () => {
    const row = {
      id: 11,
      number: 'ADV-2026-0011',
      status: 'qualified',
      entity: ENTITY,
      initialResult: { total: '385.00', maxTotal: '500.00', percent: '77.00' },
      dueAt: '2026-10-20T08:00:00+00:00',
      isOverdue: true,
      decision: { outcome: 'qualified', decidedAt: SUBMITTED_AT },
    }
    const wrapper = await mountWithColumns(
      FilteringTable,
      { cycleId: 3, items: [row], status: 'success' },
      'award-filtering',
      ['status', 'dueAt', 'score', 'entityReference', 'decision'],
    )
    expect(dateIn(cellOf(wrapper, 'Due'))).toBe(row.dueAt)
    expect(cellOf(wrapper, 'Due').text()).toContain('Overdue')
    expect(cellOf(wrapper, 'Score').text()).toBe('385.00 of 500.00')
    expect(cellOf(wrapper, 'Entity number').text()).toBe('ENT-000123')
    expect(cellOf(wrapper, 'Filtering decision').text()).toContain('Qualified')
  })

  it('shows when a ranked request was submitted', async () => {
    const row = {
      assessmentId: 11,
      number: 'ADV-2026-0011',
      status: 'awaiting-regional-ranking',
      entity: ENTITY,
      averagePercent: '88.50',
      regionalRank: null,
      submittedAt: SUBMITTED_AT,
      approvedAt: null,
    }
    const wrapper = await mountWithColumns(
      ResultsRowTable,
      { rows: [row], rankField: 'regionalRank' },
      'award-results',
      ['status', 'average', 'rank', 'submittedAt', 'approvedAt'],
    )
    expect(dateIn(cellOf(wrapper, 'Submitted on'))).toBe(SUBMITTED_AT)
    expect(cellOf(wrapper, 'Average approved on').text()).toBe('')
  })

  it('shows when a committee evaluation started', async () => {
    const evaluation = {
      id: 5,
      member: { id: 2, user: { id: 9, name: 'Sara Al-Harbi' }, status: 'active' },
      status: 'in-progress',
      counted: false,
      isSubstitute: false,
      percent: null,
      startedAt: SUBMITTED_AT,
    }
    const wrapper = await mountWithColumns(
      SecretaryEvaluationTable,
      { evaluations: [evaluation], approvesSubstitution: () => false },
      'award-secretary-evaluations',
      ['status', 'startedAt'],
    )
    expect(dateIn(cellOf(wrapper, 'Started on'))).toBe(SUBMITTED_AT)
  })

  it('shows the due date and the assessors counted of a secretary row', async () => {
    const row = {
      id: 11,
      number: 'ADV-2026-0011',
      status: 'awaiting-secretary-approval',
      entity: ENTITY,
      initialPercent: '77.00',
      average: { percent: '88.50', memberCount: 3, isManual: false, computedAt: SUBMITTED_AT },
      committee: { assignments: 3, completed: 3, pendingSubstitutions: 0 },
      dueAt: '2026-10-20T08:00:00+00:00',
      isOverdue: false,
    }
    const wrapper = await mountWithColumns(
      SecretaryRequestTable,
      { cycleId: 3, items: [row], status: 'success' },
      'award-secretary-requests',
      ['status', 'initialPercent', 'dueAt', 'assessorsCounted', 'manualAverage'],
    )
    expect(cellOf(wrapper, 'Initial result').text()).toContain('77.00')
    expect(dateIn(cellOf(wrapper, 'Due'))).toBe(row.dueAt)
    expect(cellOf(wrapper, 'Assessors counted').text()).toBe('3')
    expect(cellOf(wrapper, 'Manual average').text()).toBe('No')
  })

  it('shows when the assessor submitted a committee assignment', async () => {
    const row = {
      id: 21,
      cycle: { id: 3, name: { ar: 'دورة', en: 'Cycle 2026' }, status: 'committee-assessment' },
      entity: { visibility: 'code', code: 'C-01', region: null },
      status: 'completed',
      membershipStatus: 'active',
      isSubstitute: false,
      percent: '80.00',
      reviewProgress: null,
      submittedAt: SUBMITTED_AT,
    }
    const wrapper = await mountWithColumns(
      CommitteeAssignmentTable,
      { items: [row], status: 'success' },
      'committee-assignments',
      ['status', 'percent', 'submittedAt'],
    )
    expect(dateIn(cellOf(wrapper, 'Submitted on'))).toBe(SUBMITTED_AT)
  })

  it('shows when a committee seat was disabled', async () => {
    const member = {
      id: 2,
      user: { id: 9, name: 'Sara Al-Harbi' },
      status: 'disabled',
      progress: { notStarted: 0, inProgress: 1, completed: 0 },
      appointedAt: SUBMITTED_AT,
      disabledAt: '2026-10-12T08:00:00+00:00',
      removedAt: null,
    }
    const wrapper = await mountWithColumns(
      CommitteeMemberTable,
      { members: [member], status: 'success' },
      'committee-members',
      ['status', 'disabledAt', 'removedAt'],
    )
    expect(dateIn(cellOf(wrapper, 'Disabled on'))).toBe(member.disabledAt)
    expect(cellOf(wrapper, 'Removed on').text()).toBe('')
  })
})
