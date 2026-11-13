import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { entityBoardHandlers } from '@/mocks/handlers/entityBoard'
import { dashboardHandlers } from '@/mocks/handlers/dashboard'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { MOCK_API_BASE, clearMockSession, mockFetch, seedMockSession } from '@/test-utils/mockApi'
import { setRandomFaults } from '@/mocks/contract/faults'

const server = setupServer(...entityBoardHandlers, ...dashboardHandlers)
const NAMAA = { id: 7601, roles: ['entity'], entityId: 6001 }

async function get(path) {
  const response = await mockFetch(`${MOCK_API_BASE}${path}`)
  return { status: response.status, body: await response.json() }
}

beforeAll(() => {
  setRandomFaults(false)
  server.listen({ onUnhandledRequest: 'error' })
})
beforeEach(() => {
  resetAssessmentsStore()
  resetEntitiesMock()
  resetFinanceState()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('entity board mock (Task-46 contract)', () => {
  it('lists the entity Action Center items with their context', async () => {
    seedMockSession(NAMAA)
    const { status, body } = await get('/dashboard/action-center')
    expect(status).toBe(200)
    const keys = body.data.map((item) => item.key)
    expect(keys).toEqual(expect.arrayContaining(['complete_profile', 'submit_accredited_draft']))
    const draft = body.data.find((item) => item.key === 'submit_accredited_draft')
    expect(draft).toMatchObject({ count: 1, severity: 'warning', link: '/assessment/accredited' })
    expect(draft.context).toMatchObject({ assessmentId: 9101, reference: 'ACC-2026-000004' })
    expect(draft.context.remainingSubs).toBeGreaterThan(0)
    expect(body.data.find((item) => item.key === 'complete_profile').context.percent).toBe(60)
    expect(body.meta.total).toBe(body.data.reduce((sum, item) => sum + item.count, 0))
  })

  it('answers no items to staff and 401 to guests', async () => {
    seedMockSession({ id: 1, roles: ['system_admin'] })
    expect((await get('/dashboard/action-center')).body.data).toEqual([])
    clearMockSession()
    expect((await get('/dashboard/action-center')).status).toBe(401)
  })

  it('serves the entity board with the latest result and the payments card', async () => {
    seedMockSession(NAMAA)
    const { status, body } = await get('/dashboard/summary?board=entity')
    expect(status).toBe(200)
    expect(body.data.board).toBe('entity')
    const latest = body.data.widgets.find((widget) => widget.key === 'latest_result').data
    expect(latest).toMatchObject({ assessmentId: 9104, path: 'accredited' })
    expect(latest.pathLabel).toEqual({ ar: 'التقييم المعتمد', en: 'Accredited assessment' })
    expect(body.data.widgets.map((widget) => widget.key)).toContain('payments_card')
    expect(Array.isArray(body.data.actionCenter)).toBe(true)
  })

  it('leaves the staff boards to the staff dashboard mock', async () => {
    seedMockSession({ id: 1, roles: ['system_admin'] })
    const { body } = await get('/dashboard/summary?board=system_admin')
    expect(body.data.board).toBe('system_admin')
  })
})
