import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetAssessmentSettings } from '@/mocks/stores/accredited/assessmentSettingsStore'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { resetInsightsRules } from '@/mocks/stores/insights/insightsRules'
import { resetMaturityTexts } from '@/mocks/stores/insights/maturityTexts'
import { resetAssessorSummaries } from '@/mocks/stores/insights/assessorSummaries'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...handlers)

const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001 }
const RAWABI = { id: 7603, roles: ['entity'], entityId: 6003 }
const IEMAR = { id: 7702, roles: ['entity'], entityId: 7002 }
const ADMIN = { id: 1, roles: ['system_admin'] }
const ASSESSOR = { id: 10, roles: ['assessor'] }
const CONTENT_OFFICER = { id: 30, roles: ['content_officer'] }
const FINANCE_OFFICER = { id: 22, roles: ['finance_officer'] }
const SUPER_ADMIN = { id: 900, roles: [], superAdmin: true }
const LATEST = 9102
const PREVIOUS = 9109
const UNDER_REVIEW = 9108
const SELF_RESULT = 9001
const OTHER_ENTITY = 9103

async function call(method, path, body) {
  const init = { method, headers: { 'accept-language': 'en' } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetCriteriaStore()
  resetEntitiesMock()
  resetFinanceState()
  resetAssessmentSettings()
  resetAssessmentsStore()
  resetInsightsRules()
  resetMaturityTexts()
  resetAssessorSummaries()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('insights mock — overview, results and library', () => {
  it('selects the latest accredited result and lists eligible results newest first', async () => {
    seedMockSession(ALBIRR)
    const { body } = await call('GET', '/entities/me/insights')
    expect(body.data.selected.assessment_id).toBe(LATEST)
    expect(body.data.empty_state).toBeNull()
    expect(body.data.rules).toEqual({
      gap_thresholds_version: 1,
      maturity_bands_version: 1,
      effective_since: null,
    })
    const results = await call('GET', '/entities/me/insights/results')
    expect(results.body.data.map((row) => row.assessment_id)).toEqual([LATEST, PREVIOUS])
    expect(results.body.meta.pagination.per_page).toBe(15)
  })

  it('answers the in-progress and no-result empty states with an action', async () => {
    seedMockSession(RAWABI)
    const progress = await call('GET', '/entities/me/insights')
    expect(progress.body.data.empty_state.reason).toBe('in_progress')
    expect(progress.body.data.empty_state.cta.kind).toBe('view_request')
    seedMockSession(IEMAR)
    const none = await call('GET', '/entities/me/insights')
    expect(none.body.data.empty_state).toEqual({
      reason: 'no_verified_result',
      cta: { kind: 'start_accredited', assessment_id: null },
    })
  })

  it('gives staff the same overview without actions and denies an entity the staff route', async () => {
    seedMockSession(ADMIN)
    const staff = await call('GET', '/entities/6003/insights')
    expect(staff.body.data.empty_state.cta).toBeNull()
    seedMockSession(ALBIRR)
    expect((await call('GET', '/entities/6003/insights')).status).toBe(403)
    seedMockSession(ASSESSOR)
    expect((await call('GET', '/entities/5001/insights')).status).toBe(403)
  })

  it('lists the library per result with the coming-soon reports', async () => {
    seedMockSession(ALBIRR)
    const { body } = await call('GET', '/entities/me/report-library')
    expect(body.data).toHaveLength(2)
    expect(body.data[0].items.map((item) => item.kind)).toEqual([
      'feedback_report',
      'board_report',
      'certificate',
    ])
    expect(body.data[1].items[2]).toMatchObject({ available: false, status: null })
    expect(body.meta.coming_soon).toEqual([
      { kind: 'periodic' },
      { kind: 'annual' },
      { kind: 'benchmark' },
    ])
  })
})

describe('insights mock — tools', () => {
  it('classifies every sub-criterion exactly once and orders gaps by lost points', async () => {
    seedMockSession(ALBIRR)
    const { body } = await call('GET', `/assessments/${PREVIOUS}/insights/strengths-gaps`)
    const { counts, items } = body.data
    const total = counts.strength + counts.opportunity + counts.critical_gap
    const listed = items.strengths.length + items.opportunities.length + items.critical_gaps.length
    expect(listed).toBe(total)
    const lost = items.opportunities.map((item) => Number(item.lost_points))
    expect(lost).toEqual([...lost].sort((a, b) => b - a))
    items.critical_gaps.forEach((item) => expect(Number(item.percent)).toBeLessThan(50))
    expect(Object.keys(items.opportunities[0].assessor)).toEqual([
      'strengths',
      'improvement_points',
    ])
  })

  it('compares with the earlier eligible result and signs the delta', async () => {
    seedMockSession(ALBIRR)
    const latest = await call('GET', `/assessments/${LATEST}/insights/maturity`)
    expect(latest.body.data.previous.assessment_id).toBe(PREVIOUS)
    expect(latest.body.data.overall.delta_pp).toMatch(/^[+-]?\d+\.\d{2}$/)
    expect(latest.body.data.mains[0].comparison).toBe('matched')
    const earliest = await call('GET', `/assessments/${PREVIOUS}/insights/maturity`)
    expect(earliest.body.data.previous).toBeNull()
    expect(earliest.body.data.mains[0].comparison).toBe('no_previous')
  })

  it('quotes the assessor summary in the executive summary', async () => {
    seedMockSession(ALBIRR)
    const { body } = await call('GET', `/assessments/${LATEST}/insights/executive-summary`)
    expect(body.data.assessor_summary.version).toBe(2)
    expect(body.data.certificate).toEqual({ standing: 'level', level: 'silver' })
    expect(body.data.strong_areas.length).toBeLessThanOrEqual(3)
    expect(body.data.strong_areas[0]).toHaveProperty('quote')
  })

  it('hides self and foreign results and refuses ineligible ones with the reason', async () => {
    seedMockSession(ALBIRR)
    expect((await call('GET', `/assessments/${SELF_RESULT}/insights/maturity`)).status).toBe(404)
    expect((await call('GET', `/assessments/${OTHER_ENTITY}/insights/maturity`)).status).toBe(404)
    seedMockSession(ADMIN)
    const open = await call('GET', `/assessments/${UNDER_REVIEW}/insights/maturity`)
    expect(open.status).toBe(409)
    expect(open.body.code).toBe('insights.result_not_eligible')
    expect(open.body.meta.reason).toBe('not_completed')
    seedMockSession(CONTENT_OFFICER)
    expect((await call('GET', `/assessments/${LATEST}/insights/maturity`)).status).toBe(403)
  })

  it('returns a signed board-report link and refuses an unknown language', async () => {
    seedMockSession(ALBIRR)
    const { status, body } = await call('POST', `/assessments/${LATEST}/board-report/export`, {
      locale: 'en',
    })
    expect(status).toBe(200)
    expect(body.data.file_name).toBe('board-report-ACC-2026-000001-en.pdf')
    expect(body.data.url).toContain('signature=')
    const invalid = await call('POST', `/assessments/${LATEST}/board-report/export`, {
      locale: 'fr',
    })
    expect(invalid.status).toBe(422)
  })
})

describe('insights mock — settings and maturity texts', () => {
  it('raises the rules version on a real change and re-classifies at once', async () => {
    seedMockSession(ADMIN)
    const unchanged = await call('PUT', '/settings/insights', {
      gap_thresholds: { critical_below: '50', strength_from: '80.00' },
    })
    expect(unchanged.body.data.gap_thresholds.version).toBe(1)
    const changed = await call('PUT', '/settings/insights', {
      gap_thresholds: { critical_below: '55.00', strength_from: '80.00' },
    })
    expect(changed.body.data.gap_thresholds).toEqual({
      critical_below: '55.00',
      strength_from: '80.00',
      version: 2,
    })
    expect(changed.body.data.aligned).toBe(false)
    seedMockSession(ALBIRR)
    const tool = await call('GET', `/assessments/${PREVIOUS}/insights/strengths-gaps`)
    expect(tool.body.data.rules.version).toBe(2)
    tool.body.data.items.critical_gaps.forEach((item) =>
      expect(Number(item.percent)).toBeLessThan(55),
    )
  })

  it('refuses rules out of order, with three decimals, a missing reason or a wrong role', async () => {
    seedMockSession(ADMIN)
    const descending = await call('PUT', '/settings/insights', {
      maturity_bands: { developing_from: '70.00', mature_from: '60.00', leading_from: '80.00' },
    })
    expect(descending.status).toBe(422)
    expect(Object.keys(descending.body.errors)).toEqual(['maturity_bands'])
    const decimals = await call('PUT', '/settings/insights', {
      gap_thresholds: { critical_below: '50.005', strength_from: '80.00' },
    })
    expect(decimals.status).toBe(422)
    seedMockSession(SUPER_ADMIN)
    const noReason = await call('PUT', '/settings/insights', {
      gap_thresholds: { critical_below: '45.00', strength_from: '80.00' },
    })
    expect(noReason.body.code).toBe('reason_required')
    seedMockSession(CONTENT_OFFICER)
    expect((await call('GET', '/settings/insights')).status).toBe(403)
  })

  it('lets the Content Officer write an override but never delete a generic row', async () => {
    seedMockSession(CONTENT_OFFICER)
    const list = await call('GET', '/content/maturity-texts')
    expect(list.body.data.filter((row) => row.is_generic)).toHaveLength(4)
    const saved = await call('PUT', '/content/maturity-texts/leading', {
      main_criterion_code: 'M5',
      text: { ar: 'نص خاص بالمعيار.', en: 'A criterion text.' },
    })
    expect(saved.status).toBe(200)
    expect(saved.body.data.in_published_version).toBe(true)
    const markup = await call('PUT', '/content/maturity-texts/leading', {
      text: { ar: '<b>نص</b>', en: 'Text' },
    })
    expect(markup.status).toBe(422)
    expect((await call('DELETE', `/content/maturity-texts/${list.body.data[0].id}`)).status).toBe(
      422,
    )
    expect((await call('DELETE', `/content/maturity-texts/${saved.body.data.id}`)).status).toBe(204)
    seedMockSession(ALBIRR)
    expect((await call('GET', '/content/maturity-texts')).status).toBe(403)
  })
})

describe('assessor summary and VAT number mocks', () => {
  it('saves, versions and clears the summary for the assignee only', async () => {
    seedMockSession(ASSESSOR)
    const saved = await call('PUT', `/assessments/${UNDER_REVIEW}/review/summary`, {
      summary: '  ملخص المقيّم.  ',
    })
    expect(saved.body.data.summary).toMatchObject({ body: 'ملخص المقيّم.', version: 1 })
    const same = await call('PUT', `/assessments/${UNDER_REVIEW}/review/summary`, {
      summary: 'ملخص المقيّم.',
    })
    expect(same.body.data.summary.version).toBe(1)
    const tooLong = await call('PUT', `/assessments/${UNDER_REVIEW}/review/summary`, {
      summary: 'أ'.repeat(2001),
    })
    expect(tooLong.status).toBe(422)
    const cleared = await call('PUT', `/assessments/${UNDER_REVIEW}/review/summary`, {
      summary: null,
    })
    expect(cleared.body.data.summary).toBeNull()
    seedMockSession({ id: 7602, roles: ['entity'], entityId: 6002 })
    const owner = await call('PUT', `/assessments/${UNDER_REVIEW}/review/summary`, { summary: 'x' })
    expect(owner.status).toBe(403)
  })

  it('accepts, validates and clears the platform VAT number for the System Administrator', async () => {
    seedMockSession(ADMIN)
    const initial = await call('GET', '/settings/finance')
    expect(initial.body.data.vat_registration_reminder).toBe(true)
    const invalid = await call('PUT', '/settings/finance', {
      vat_registration_number: '200012345600003',
    })
    expect(invalid.status).toBe(422)
    const saved = await call('PUT', '/settings/finance', {
      vat_registration_number: '300012345600003',
    })
    expect(saved.body.data.vat_registration_number).toBe('300012345600003')
    expect(saved.body.data.vat_registration_reminder).toBe(false)
    const cleared = await call('PUT', '/settings/finance', { vat_registration_number: null })
    expect(cleared.body.data.vat_registration_number).toBeNull()
    seedMockSession(FINANCE_OFFICER)
    expect((await call('GET', '/settings/finance')).status).toBe(403)
  })
})
