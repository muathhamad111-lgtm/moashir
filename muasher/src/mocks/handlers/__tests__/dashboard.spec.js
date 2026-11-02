import { describe, expect, it, beforeAll, afterAll, afterEach } from 'vitest'
import { setupServer } from 'msw/node'
import { dashboardHandlers } from '@/mocks/handlers/dashboard'
import { MOCK_API_BASE, mockFetch } from '@/test-utils/mockApi'

const server = setupServer(...dashboardHandlers)
const BASE = MOCK_API_BASE

async function call(role) {
  const suffix = role === undefined ? '' : `?role=${role}`
  const res = await mockFetch(`${BASE}/dashboard/summary${suffix}`)
  return { ok: res.ok, status: res.status, body: await res.json() }
}

async function retry(role, tries = 20) {
  let last
  for (let i = 0; i < tries; i += 1) {
    last = await call(role)
    if (last.ok) return last
  }
  throw new Error(`dashboard summary failed for role ${role} (status ${last?.status})`)
}

function hasActions(summary) {
  expect(Array.isArray(summary.actions)).toBe(true)
  expect(summary.actions.length).toBeGreaterThan(0)
  for (const action of summary.actions) {
    expect(action.id).toBeTruthy()
    expect(action.to).toBeTruthy()
    expect(action.text.ar).toBeTruthy()
    expect(action.text.en).toBeTruthy()
  }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

describe('dashboard summary mock', () => {
  it('returns an admin summary with the action center + all card groups', async () => {
    const { body } = await retry('admin')
    const summary = body.data
    expect(summary.role).toBe('admin')
    hasActions(summary)
    expect(summary.summary.length).toBeGreaterThan(0)
    expect(summary.assessments.length).toBeGreaterThan(0)
    expect(summary.payments.length).toBeGreaterThan(0)
    expect(summary.distribution.byRegion.length).toBeGreaterThan(0)
    expect(summary.lowestCriteria.length).toBeGreaterThan(0)
    expect(summary.activity.length).toBeGreaterThan(0)
  })

  it('returns tailored summaries per role', async () => {
    const follower = (await retry('follower')).body.data
    expect(follower.role).toBe('follower')
    expect(follower.requests.length).toBeGreaterThan(0)
    hasActions(follower)

    const assessor = (await retry('assessor')).body.data
    expect(assessor.tasks.length).toBeGreaterThan(0)

    const committeeAssessor = (await retry('committee-assessor')).body.data
    expect(committeeAssessor.stats.length).toBeGreaterThan(0)

    const secretary = (await retry('secretary')).body.data
    expect(secretary.approvalQueue.length).toBeGreaterThan(0)

    const entity = (await retry('entity')).body.data
    expect(entity.score.value).toBeGreaterThan(0)
    expect(entity.criteria.length).toBeGreaterThan(0)

    const finance = (await retry('finance')).body.data
    expect(finance.revenueTrend.length).toBeGreaterThan(0)

    const content = (await retry('content-officer')).body.data
    expect(content.summary.length).toBeGreaterThan(0)
  })

  it('aliases filtering-officer to the follower dashboard', async () => {
    const { body } = await retry('filtering-officer')
    expect(body.data.role).toBe('follower')
    expect(body.meta.requestedRole).toBe('filtering-officer')
    expect(body.meta.resolvedRole).toBe('follower')
  })

  it('aliases criteria-officer to the content dashboard', async () => {
    const { body } = await retry('criteria-officer')
    expect(body.data.role).toBe('content-officer')
  })

  it('falls back to the entity dashboard for an unknown role', async () => {
    const { body } = await retry('mystery-role')
    expect(body.data.role).toBe('entity')
  })

  it('every action links to a bilingual, non-null count-or-null shape', async () => {
    const { body } = await retry('entity')
    for (const action of body.data.actions) {
      expect('count' in action).toBe(true)
      expect(typeof action.severity).toBe('string')
    }
  })
})
