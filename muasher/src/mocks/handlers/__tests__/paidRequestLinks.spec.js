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

describe('paid drafts in the request list', () => {
  it('lists a paid draft with its progress in the list and in the preparing stage', async () => {
    seedMockSession(FOLLOWER)
    const all = await call('/requests?filter[path]=accredited')
    const row = all.body.data.find((item) => item.id === ACCREDITED_FIXTURE_IDS.draft)
    expect(row.status).toBe('draft')
    expect(row.assessor).toBeNull()
    expect(row.progress.subs_total).toBeGreaterThan(row.progress.subs_complete)
    expect(row.opened_at).toBeTruthy()
    expect(Object.keys(row.payment).sort()).toEqual(['is_test', 'status'])
    const preparing = await call('/requests?filter[path]=accredited&filter[stage]=preparing')
    expect(preparing.body.data.map((item) => item.status)).toEqual(
      preparing.body.data.map(() => 'draft'),
    )
    expect(all.body.meta.stats.by_stage.preparing).toBe(preparing.body.meta.pagination.total)
    expect(all.body.meta.path_counts[0].preparing).toBe(preparing.body.meta.pagination.total)
  })

  it('gives the finance identifiers of a draft payment to finance holders only', async () => {
    seedMockSession(ADMIN)
    const { body } = await call('/requests?filter[stage]=preparing')
    expect(body.data[0].payment.id).toBe(DRAFT_PAYMENT)
    expect(body.data[0].payment.reference).toBeTruthy()
  })

  it('refuses an unknown stage and keeps the list from Finance', async () => {
    seedMockSession(FOLLOWER)
    expect((await call('/requests?filter[stage]=archived')).status).toBe(422)
    seedMockSession(OFFICER)
    expect((await call('/requests?filter[stage]=preparing')).status).toBe(403)
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
