import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { findAssessment, resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { findCycle } from '@/mocks/stores/award/cycleStore'
import { discardQueuedJobs } from '@/mocks/stores/mockQueue'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

// «تأهيل كل من بلغ حد التأهل» (operator decision D-4): the mock mirrors the API contract of the bulk action.
const server = setupServer(...handlers)
const FILTERING_OFFICER = { id: 13, roles: ['filtering_officer'] }
const FOLLOWER = { id: 9, roles: ['request_follower'] }
const SUPER_ADMIN = { id: 900, roles: [], superAdmin: true }
const PATH = '/award/cycles/31/filtering/qualify-eligible'

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
  resetAssessmentsStore()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
  discardQueuedJobs()
})
afterAll(() => server.close())

describe('bulk qualification mock', () => {
  it('previews and qualifies the requests at the threshold, keeping the one below it', async () => {
    seedMockSession(FILTERING_OFFICER)
    const preview = await call('GET', PATH)
    expect(preview.status).toBe(200)
    expect(preview.body.data).toMatchObject({
      count: 1,
      threshold_percent: '60.00',
      own_assessments: 0,
    })
    expect(preview.body.data.requests.map((request) => request.id)).toEqual([9204])

    const done = await call('POST', PATH, {})
    expect(done.body.data).toMatchObject({ qualified: 1, qualified_ids: [9204], skipped: [] })
    expect(findAssessment(9204).status).toBe('qualified-for-committee')
    expect(findAssessment(9205).status).toBe('awaiting-filtering')

    expect((await call('POST', PATH, {})).body.data.qualified).toBe(0)
  })

  it('refuses outside the filtering window and to staff who may not decide', async () => {
    seedMockSession(FOLLOWER)
    expect((await call('POST', PATH, {})).status).toBe(403)
    seedMockSession(FILTERING_OFFICER)
    findCycle(31).status = 'approved'
    expect((await call('POST', PATH, {})).body.code).toBe('cycle.filtering_not_open')
  })

  it('asks the Super Admin for one reason', async () => {
    seedMockSession(SUPER_ADMIN)
    expect((await call('POST', PATH, {})).body.code).toBe('reason_required')
    const done = await call('POST', PATH, { reason: 'تأهيل جماعي بعد مراجعة القائمة كاملة' })
    expect(done.body.data.qualified).toBe(1)
  })
})
