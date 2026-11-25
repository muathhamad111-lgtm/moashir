import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetAssessmentSettings } from '@/mocks/stores/accredited/assessmentSettingsStore'
import { resetAccreditedOpening } from '@/mocks/stores/accredited/accreditedOpening'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

// The completion rounds and the override preview (design §B.3.3, §B.3.5) in the mock API.
const server = setupServer(...handlers)
const SULTAN = { id: 10, roles: ['assessor'] }
const TAMKEEN = { id: 6006, roles: ['entity'], entityId: 6006 }
const SUPER_ADMIN = { id: 900, roles: [], superAdmin: true }
const FOLLOWER = { id: 8, roles: ['request_follower', 'filtering_officer'] }
const DAY_MS = 86400000

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

function allUnits(criteria, value = '8') {
  const sheet = { subs: {}, points: {} }
  criteria.mains.forEach((main) =>
    main.subs.forEach((sub) => {
      if (sub.points.length) sub.points.forEach((point) => (sheet.points[point.id] = value))
      else sheet.subs[sub.id] = value
    }),
  )
  return sheet
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetCriteriaStore()
  resetEntitiesMock()
  resetFinanceState()
  resetAssessmentSettings()
  resetAccreditedOpening()
  resetAssessmentsStore()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('review rounds mock', () => {
  it('records each round of an item the entity was asked to complete', async () => {
    seedMockSession(SULTAN)
    await call('POST', '/assessments/9107/start-review', {})
    const criteria = (await call('GET', '/assessments/9107/review')).body.data.criteria
    const point = criteria.mains[0].subs[0].points[0]
    await call('PUT', '/assessments/9107/review/scores', { scores: allUnits(criteria, '3') })
    const sent = await call('POST', '/assessments/9107/request-completion', {
      notes: 'Please attach the approved plan.',
      items: [{ sub_criterion_id: criteria.mains[0].subs[0].id, kind: 'evidence', note: 'Plan' }],
      deadline_at: new Date(Date.now() + 7 * DAY_MS).toISOString(),
      expiry_outcome: 'decline',
    })
    expect(sent.status).toBe(200)
    seedMockSession(TAMKEEN)
    expect((await call('POST', '/assessments/9107/submit', {})).status).toBe(200)
    seedMockSession(SULTAN)
    // The same value again is this round's result (the API's re-confirmation rule).
    await call('PUT', '/assessments/9107/review/scores', {
      scores: { subs: {}, points: { [point.id]: '3' } },
    })
    const { body } = await call('GET', '/assessments/9107/review/rounds')
    expect(body.data.requests).toHaveLength(1)
    expect(body.data.requests[0]).toMatchObject({ round: 2, status: 'resubmitted' })
    expect(body.data.requests[0].items[0]).toMatchObject({ kind: 'evidence', note: 'Plan' })
    expect(body.data.units[`point-${point.id}`].map((entry) => entry.round)).toEqual([1, 2])
    expect(body.data.units[`point-${point.id}`][1]).toMatchObject({ raw_value: '3.00' })
  })
})

// Production report 2026-11-25: completing before the completed items had this round's result.
describe('completion after a round mock', () => {
  it('refuses to complete until each completed item has this round’s result', async () => {
    seedMockSession(SULTAN)
    await call('POST', '/assessments/9107/start-review', {})
    const criteria = (await call('GET', '/assessments/9107/review')).body.data.criteria
    const sub = criteria.mains[0].subs[0]
    await call('PUT', '/assessments/9107/review/scores', { scores: allUnits(criteria, '3') })
    await call('POST', '/assessments/9107/request-completion', {
      notes: 'Please attach the approved plan.',
      items: [{ sub_criterion_id: sub.id, kind: 'evidence', note: 'Plan' }],
      deadline_at: new Date(Date.now() + 7 * DAY_MS).toISOString(),
      expiry_outcome: 'decline',
    })
    seedMockSession(TAMKEEN)
    await call('POST', '/assessments/9107/submit', {})
    seedMockSession(SULTAN)

    const refused = await call('POST', '/assessments/9107/complete', {})
    expect(refused.status).toBe(422)
    expect(refused.body.code).toBe('scores.round_incomplete')
    const byPoints = sub.points.length > 0
    const ids = byPoints ? sub.points.map((point) => point.id) : [sub.id]
    expect(refused.body.meta.missing.map((unit) => unit.id)).toEqual(ids)

    const again = Object.fromEntries(ids.map((id) => [id, '3']))
    const scores = byPoints ? { subs: {}, points: again } : { subs: again, points: {} }
    await call('PUT', '/assessments/9107/review/scores', { scores })
    expect((await call('POST', '/assessments/9107/complete', {})).status).toBe(200)
  })
})

describe('override preview mock', () => {
  it('computes the total and level of the edited scores without saving them', async () => {
    seedMockSession(SUPER_ADMIN)
    const review = (await call('GET', '/assessments/9104/review')).body.data
    const point = review.criteria.mains[0].subs[0].points[0]
    const before = review.scores.points[point.id].raw_value
    const { status, body } = await call('POST', '/assessments/9104/scores/override/preview', {
      scores: { subs: {}, points: { [point.id]: '10' } },
    })
    expect(status).toBe(200)
    expect(Number(body.data.after.percent)).toBeGreaterThan(Number(body.data.before.percent))
    expect(body.data.changes).toEqual([
      { code: point.code, field: `point-${point.id}`, before, after: '10.00' },
    ])
    const after = (await call('GET', '/assessments/9104/review')).body.data
    expect(after.scores.points[point.id].raw_value).toBe(before)
  })

  it('refuses the preview to anyone but the Super Admin', async () => {
    seedMockSession(FOLLOWER)
    const { status } = await call('POST', '/assessments/9104/scores/override/preview', {
      scores: { subs: {}, points: {} },
    })
    expect(status).toBe(403)
  })
})
