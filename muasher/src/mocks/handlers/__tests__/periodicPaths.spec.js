import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { findAssessment, resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetAssessmentSettings } from '@/mocks/stores/accredited/assessmentSettingsStore'
import { resetAccreditedOpening } from '@/mocks/stores/accredited/accreditedOpening'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { ACCREDITED_FIXTURE_IDS as IDS } from '@/mocks/fixtures/accreditedRequests'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...handlers)
const NAMAA = { id: 6001, roles: ['entity'], entityId: 6001 }
const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001 }
const TODAY = new Date('2026-11-10T09:00:00Z')

async function call(method, path, body) {
  const init = { method, headers: { 'accept-language': 'en' } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  return { status: response.status, body: await response.json() }
}

function widget(body, key) {
  return body.data.widgets.find((entry) => entry.key === key)?.data
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(TODAY)
  resetCriteriaStore()
  resetEntitiesMock()
  resetFinanceState()
  resetAssessmentSettings()
  resetAccreditedOpening()
  resetAssessmentsStore()
})
afterEach(() => {
  vi.useRealTimers()
  clearMockSession()
  server.resetHandlers()
})
afterAll(() => server.close())

describe('periodic accredited mock (Task-79 contract)', () => {
  it('adds the level, paths and next_step widgets to the entity board', async () => {
    seedMockSession(NAMAA)
    const { body } = await call('GET', '/dashboard/summary?board=entity')
    const paths = widget(body, 'paths')
    expect(Object.keys(paths).sort()).toEqual(['accredited', 'award', 'self'])
    expect(paths.accredited.current_period.key).toBe('2026-Q4')
    expect(paths.accredited.period_status).toBe('not_submitted')
    expect(paths.accredited.open_request.status).toBe('draft')
    expect(paths.accredited.last_result.certificate.valid_until).toBe('2026-12-31')
    expect(widget(body, 'level').level).toBe('silver')
    const step = widget(body, 'next_step')
    expect([step.rank, step.key, step.target.href]).toEqual([
      3,
      'finish_draft',
      `/paths/accredited/${IDS.draft}?tab=request`,
    ])
    expect(step.context.period_ends_on).toBe('2026-12-31')
  })

  it('merges the missed periods into the accredited list as rows without a number', async () => {
    seedMockSession(ALBIRR)
    const { body } = await call('GET', '/assessments?filter[path]=accredited&per_page=50')
    const missed = body.data.filter((row) => row.row_type === 'missed_period')
    expect(missed.map((row) => row.period.key)).toEqual(['2026-Q2'])
    expect(missed[0]).toMatchObject({ id: null, number: null, period_status: 'missed' })
    expect(body.meta.missed_periods).toBe(1)
    expect(body.meta.pagination.total).toBe(body.data.length)
    const filtered = await call(
      'GET',
      '/assessments?filter[path]=accredited&filter[status]=completed',
    )
    expect(filtered.body.data.some((row) => row.row_type === 'missed_period')).toBe(false)
  })

  it('labels requests submitted before the rollout as pre-periodic', async () => {
    seedMockSession(ALBIRR)
    const { body } = await call('GET', '/assessments?filter[path]=accredited&per_page=50')
    const old = body.data.find((row) => row.number === 'ACC-2025-000012')
    expect(old.period_state).toBe('pre_periodic')
    expect(old.period_label.en).toBe('Before periodic assessment')
  })

  it('lists the tabs of a completed request and opens it on the result', async () => {
    seedMockSession(ALBIRR)
    const { body } = await call('GET', `/assessments/${IDS.completedOwn}`)
    expect(body.data.available_tabs).toEqual([
      'request',
      'result',
      'feedback',
      'certificate',
      'tools',
      'kit',
    ])
    expect(body.data.default_tab).toBe('result')
  })

  it('refuses a second submission in an assessed period with 409 and keeps the draft', async () => {
    findAssessment(IDS.completedEarlier).submittedAt = '2026-10-05T09:00:00Z'
    seedMockSession(NAMAA)
    const current = await call('GET', '/assessments/accredited/current')
    expect(current.body.data.periodic.period_status).toBe('assessed')
    expect(current.body.data.periodic.next_submission_from).toBe('2027-01-01')
    expect(current.body.data.apply.warning).toBe('period_already_assessed')
    const { status, body } = await call('POST', `/assessments/${IDS.draft}/submit`, {})
    expect(status).toBe(409)
    expect(body.code).toBe('period.already_assessed')
    expect(body.meta.next_submission_from).toBe('2027-01-01')
    expect(findAssessment(IDS.draft).status).toBe('draft')
  })
})
