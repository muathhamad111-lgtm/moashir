import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { dashboardHandlers } from '@/mocks/handlers/dashboard'
import { MOCK_API_BASE, clearMockSession, mockFetch, seedMockSession } from '@/test-utils/mockApi'
import { setRandomFaults } from '@/mocks/contract/faults'
import { DASHBOARD_BOARDS } from '@/constants/dashboardBoards'

const server = setupServer(...dashboardHandlers)

async function summary(board) {
  const suffix = board ? `?board=${board}` : ''
  const response = await mockFetch(`${MOCK_API_BASE}/dashboard/summary${suffix}`)
  return { status: response.status, body: await response.json() }
}

beforeAll(() => {
  setRandomFaults(false)
  server.listen({ onUnhandledRequest: 'error' })
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('staff dashboard mock (Task-35 contract)', () => {
  it('lands on the highest board and lists the union of held boards', async () => {
    seedMockSession({ id: 8, roles: ['request_follower', 'assessor'] })
    const { status, body } = await summary()
    expect(status).toBe(200)
    expect(body.data.board).toBe('request_follower')
    expect(body.data.landingBoard).toBe('request_follower')
    expect(body.data.availableBoards).toEqual(['request_follower', 'assessor'])
  })

  it('serves generic cards and widgets with known formats', async () => {
    seedMockSession({ id: 1, roles: ['system_admin'] })
    const { body } = await summary('system_admin')
    expect(body.data.cards.length).toBeGreaterThan(0)
    body.data.cards.forEach((card) => {
      expect(typeof card.key).toBe('string')
      expect(['count', 'percent', 'money', 'days', 'date']).toContain(card.format)
    })
    expect(body.data.widgets.map((widget) => widget.key)).toContain('entities_by_region')
  })

  it('carries the Action Center items of every held role without context', async () => {
    seedMockSession({ id: 1, roles: ['system_admin'] })
    const { body } = await summary()
    const keys = body.data.actionCenter.map((item) => item.key)
    expect(keys).toEqual(expect.arrayContaining(['assign_requests', 'answer_contact_messages']))
    body.data.actionCenter.forEach((item) => expect(item.context).toBeNull())
  })

  it('refuses a board that is not held and an unknown board', async () => {
    seedMockSession({ id: 10, roles: ['assessor'] })
    expect((await summary('finance_officer')).status).toBe(403)
    expect((await summary('finance_officer')).body.code).toBe('board.not_held')
    expect((await summary('admin')).status).toBe(422)
  })

  it('answers 401 without a session', async () => {
    expect((await summary()).status).toBe(401)
  })

  it('knows every board key of the backend', () => {
    expect(DASHBOARD_BOARDS).toHaveLength(11)
  })
})
