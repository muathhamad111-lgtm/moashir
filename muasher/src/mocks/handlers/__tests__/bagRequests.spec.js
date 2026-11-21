import { describe, expect, it, beforeAll, afterAll, afterEach } from 'vitest'
import { setupServer } from 'msw/node'
import { bagRequestsHandlers, resetBagRequests } from '@/mocks/handlers/bagRequests'
import { EVALUATOR_IDS } from '@/mocks/fixtures/bagRequests'
import { MOCK_API_BASE, mockFetch } from '@/test-utils/mockApi'

const server = setupServer(...bagRequestsHandlers)
const BASE = MOCK_API_BASE

async function jsonGet(path) {
  const res = await mockFetch(`${BASE}${path}`, { method: 'GET' })
  return { ok: res.ok, status: res.status, body: await res.json() }
}
async function jsonPost(path, body) {
  const res = await mockFetch(`${BASE}${path}`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify(body ?? {}),
  })
  return { ok: res.ok, status: res.status, body: await res.json() }
}
async function retryGet(path, tries = 20) {
  for (let i = 0; i < tries; i += 1) {
    const r = await jsonGet(path)
    if (r.ok) return r
  }
  throw new Error(`get failed: ${path}`)
}
async function retryPost(path, body, tries = 20) {
  for (let i = 0; i < tries; i += 1) {
    const r = await jsonPost(path, body)
    if (r.ok) return r
  }
  throw new Error(`post failed: ${path}`)
}
async function postUntilResolved(path, body, tries = 20) {
  let res
  for (let i = 0; i < tries; i += 1) {
    res = await jsonPost(path, body)
    if (res.status !== 500) return res
  }
  return res
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => {
  server.resetHandlers()
  resetBagRequests()
})
afterAll(() => server.close())

describe('bag requests — follower queue', () => {
  it('lists rows with a frozen accredited reference + self/advanced context + stats', async () => {
    const { body } = await retryGet('/bag/requests')
    expect(body.data.length).toBeGreaterThan(0)
    const row = body.data[0]
    expect(row.referenceSnapshot.assessmentId).toBeTruthy()
    expect(row).toHaveProperty('selfScorePercent')
    expect(row).toHaveProperty('advancedScorePercent')
    expect(body.meta.stats.total).toBeGreaterThan(0)
    expect(body.meta.stats).toHaveProperty('awaiting')
  })

  it('filters by status', async () => {
    const { body } = await retryGet('/bag/requests?filter[status]=awaiting-assignment')
    expect(body.data.every((r) => r.status === 'awaiting-assignment')).toBe(true)
  })

  it('flags a repeat entity as having a previous bag', async () => {
    const { body } = await retryGet('/bag/requests?search=bag-req-7001')
    expect(body.data[0].hasPreviousBag).toBe(true)
  })
})

describe('bag requests — evaluator directory + queue', () => {
  it('lists active evaluators', async () => {
    const { body } = await retryGet('/bag/requests/evaluators')
    expect(body.data.length).toBeGreaterThan(0)
    expect(body.data[0]).toHaveProperty('id')
    expect(body.data[0]).toHaveProperty('name')
  })

  it('returns ONLY the requests assigned to a given evaluator (§11)', async () => {
    const { body } = await retryGet(`/bag/evaluator/requests?evaluatorId=${EVALUATOR_IDS.SULTAN}`)
    expect(body.data.length).toBeGreaterThan(0)
    expect(body.data.every((r) => r.assignedEvaluatorId === EVALUATOR_IDS.SULTAN)).toBe(true)
  })
})

describe('bag requests — assignment', () => {
  it('assigns an awaiting request to an evaluator (awaiting-assignment → assigned)', async () => {
    const assigned = await retryPost('/bag/requests/bag-req-7001/assign', {
      evaluatorId: EVALUATOR_IDS.HIND,
    })
    expect(assigned.body.data.status).toBe('assigned')
    expect(assigned.body.data.assignedEvaluatorId).toBe(EVALUATOR_IDS.HIND)
    expect(assigned.body.data.assignmentHistory.at(-1).action).toBe('assigned')
  })

  it('rejects an assignment to a non-evaluator id with a 422', async () => {
    const res = await postUntilResolved('/bag/requests/bag-req-7001/assign', {
      evaluatorId: 999999,
    })
    expect(res.status).toBe(422)
  })

  it('reassign REQUIRES a reason (§10/§16) — 422 without it', async () => {
    const res = await postUntilResolved('/bag/requests/bag-req-7003/reassign', {
      evaluatorId: EVALUATOR_IDS.DAVID,
    })
    expect(res.status).toBe(422)
    expect(res.body.errors.reason).toBeTruthy()
  })

  it('reassign with a reason records it and preserves the prior assignment in history', async () => {
    const before = await retryGet('/bag/requests/bag-req-7003')
    const priorLen = before.body.data.assignmentHistory.length
    const res = await retryPost('/bag/requests/bag-req-7003/reassign', {
      evaluatorId: EVALUATOR_IDS.DAVID,
      reason: 'Original evaluator is on leave',
    })
    const history = res.body.data.assignmentHistory
    expect(history.length).toBe(priorLen + 1)
    expect(history.at(-1).action).toBe('reassigned')
    expect(history.at(-1).reason).toBe('Original evaluator is on leave')
    expect(res.body.data.assignedEvaluatorId).toBe(EVALUATOR_IDS.DAVID)
  })

  it('refuses to reassign a completed request', async () => {
    const res = await postUntilResolved('/bag/requests/bag-req-6600/reassign', {
      evaluatorId: EVALUATOR_IDS.SULTAN,
      reason: 'x',
    })
    expect(res.status).toBe(422)
  })
})
