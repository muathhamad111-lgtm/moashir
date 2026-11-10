import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetAssessmentSettings } from '@/mocks/stores/accredited/assessmentSettingsStore'
import { resetAccreditedOpening } from '@/mocks/stores/accredited/accreditedOpening'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { ACCREDITED_FIXTURE_IDS } from '@/mocks/fixtures/accreditedRequests'
import { BANK_TRANSFER_IDS } from '@/mocks/fixtures/bankTransfers'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...handlers)

const FOLLOWER = { id: 8, roles: ['request_follower'] }
const ADMIN = { id: 1, roles: ['system_admin'] }
const OFFICER = { id: 22, roles: ['finance_officer'] }
const DRAFT_PAYMENT = '3f0c9a52-6d1e-4b7a-9c21-5a1e00000012'
const PREPARING_KEYS = [
  'award_cycle',
  'entity',
  'id',
  'is_frozen',
  'last_activity_at',
  'number',
  'opened_at',
  'path',
  'path_label',
  'payment',
  'progress',
  'status',
]

async function call(path) {
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, {
    headers: { 'accept-language': 'en' },
  })
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

const keys = (value) => Object.keys(value).sort()

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

describe('paid requests waiting for the entity', () => {
  it('lists the paid drafts with the payment, progress and last activity, outside the queue', async () => {
    seedMockSession(FOLLOWER)
    const { body } = await call('/requests/preparing?filter[path]=accredited')
    const row = body.data.find((item) => item.id === ACCREDITED_FIXTURE_IDS.draft)
    expect(keys(row)).toEqual(PREPARING_KEYS)
    expect(row.status).toBe('draft')
    expect(keys(row.payment)).toEqual([
      'channel',
      'is_test',
      'paid_at',
      'receipt',
      'reference',
      'status',
    ])
    expect(row.progress.subs_total).toBeGreaterThan(row.progress.subs_complete)
    expect(body.meta.path_counts[0].preparing).toBe(body.meta.pagination.total)
    const queue = await call('/requests?filter[path]=accredited')
    expect(queue.body.data.map((item) => item.id)).not.toContain(ACCREDITED_FIXTURE_IDS.draft)
    expect(queue.body.meta.path_counts[0].preparing).toBe(body.meta.pagination.total)
  })

  it('gives the finance identifiers to finance holders only and refuses Finance the list', async () => {
    seedMockSession(ADMIN)
    const admin = await call('/requests/preparing')
    expect(admin.body.data[0].payment.id).toBe(DRAFT_PAYMENT)
    seedMockSession(OFFICER)
    expect((await call('/requests/preparing')).status).toBe(403)
  })

  it('refuses the assignment filters on the paid drafts', async () => {
    seedMockSession(FOLLOWER)
    expect((await call('/requests/preparing?filter[status]=draft')).status).toBe(422)
    expect((await call('/requests/preparing?sort=-submitted_at')).status).toBe(422)
  })
})

describe('linked request on the finance records', () => {
  it('shows Finance the number, status and progress without a link', async () => {
    seedMockSession(OFFICER)
    const { body } = await call(`/finance/transactions/${DRAFT_PAYMENT}`)
    expect(body.data.linked_request).toMatchObject({ number: 'ACC-2026-000004', status: 'draft' })
    expect(body.data.linked_request).not.toHaveProperty('id')
    expect(keys(body.data.linked_request.progress)).toEqual(['subs_complete', 'subs_total'])
  })

  it('links the request for the holders of the request queue', async () => {
    seedMockSession(ADMIN)
    const { body } = await call(`/finance/transactions/${DRAFT_PAYMENT}`)
    expect(body.data.linked_request.id).toBe(ACCREDITED_FIXTURE_IDS.draft)
  })

  it('answers no linked request on a transfer still under review', async () => {
    seedMockSession(OFFICER)
    const { body } = await call(
      `/finance/bank-transfers/${BANK_TRANSFER_IDS.underReviewAccredited}`,
    )
    expect(body.data.linked_request).toBeNull()
  })
})
