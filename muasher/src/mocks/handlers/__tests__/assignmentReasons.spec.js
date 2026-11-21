import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetAssessmentSettings } from '@/mocks/stores/accredited/assessmentSettingsStore'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { ACCREDITED_FIXTURE_IDS } from '@/mocks/fixtures/accreditedRequests'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

// Admin review 2026-11-20 (R3): the general settings decide whether assigning and reassigning need a reason.
const server = setupServer(...handlers)
const ADMIN = { id: 1, roles: ['system_admin'] }
const FOLLOWER = { id: 8, roles: ['request_follower'] }
const RULES = '/settings/assessment/assignment-reasons'

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
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('assignment reason rules', () => {
  it('starts with an optional assign reason and a required reassign reason', async () => {
    seedMockSession(ADMIN)
    const { body } = await call('GET', RULES)
    expect(body.data).toEqual({ assign_required: false, reassign_required: true })
  })

  it('saves both switches, refuses a non-boolean and keeps the rules from a follower', async () => {
    seedMockSession(ADMIN)
    const saved = await call('PUT', RULES, { assign_required: true, reassign_required: false })
    expect(saved.body.data).toEqual({ assign_required: true, reassign_required: false })
    expect(
      (await call('PUT', RULES, { assign_required: 'yes', reassign_required: true })).status,
    ).toBe(422)
    seedMockSession(FOLLOWER)
    expect((await call('GET', RULES)).status).toBe(403)
  })

  it('sends the rules with the eligible assessors and applies them to an assignment', async () => {
    seedMockSession(ADMIN)
    await call('PUT', RULES, { assign_required: true, reassign_required: true })
    seedMockSession(FOLLOWER)
    const list = await call(
      'GET',
      `/requests/assessors?assessment_id=${ACCREDITED_FIXTURE_IDS.submitted}`,
    )
    expect(list.body.meta.reason_rules).toEqual({ assign_required: true, reassign_required: true })
    const assessorId = list.body.data[0].id
    const refused = await call('POST', `/requests/${ACCREDITED_FIXTURE_IDS.submitted}/assign`, {
      assessor_id: assessorId,
    })
    expect(refused.status).toBe(422)
    expect(refused.body.errors).toHaveProperty('reason')
  })
})
