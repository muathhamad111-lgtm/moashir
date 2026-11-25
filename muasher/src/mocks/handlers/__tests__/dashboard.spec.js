import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
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

async function assessorOverview(query = '') {
  const response = await mockFetch(`${MOCK_API_BASE}/dashboard/assessor-overview${query}`)
  return { status: response.status, body: await response.json() }
}

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
    expect(body.data.widgets.map((widget) => widget.key)).toEqual(['activity'])
  })

  it('serves the Action Center items of every held role from its own endpoint, not the summary', async () => {
    seedMockSession({ id: 1, roles: ['system_admin'] })
    const { body } = await summary()
    expect(body.data).not.toHaveProperty('actionCenter')
    const center = await (await mockFetch(`${MOCK_API_BASE}/dashboard/action-center`)).json()
    const keys = center.data.map((item) => item.key)
    expect(keys).toEqual(expect.arrayContaining(['assign_requests', 'answer_contact_messages']))
    center.data.forEach((item) => expect(item.context).toBeNull())
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

  it('knows every board key of the backend; the executive view is the overview, not a board', () => {
    expect(DASHBOARD_BOARDS).toHaveLength(10)
    expect(DASHBOARD_BOARDS).not.toContain('executive')
  })
})

async function overview(query = '') {
  const response = await mockFetch(`${MOCK_API_BASE}/dashboard/overview${query}`)
  return { status: response.status, body: await response.json() }
}

describe('executive overview mock', () => {
  it('serves the month to date with a comparison, trends, paths and the snapshot', async () => {
    seedMockSession({ id: 1, roles: ['system_admin'] })
    const { status, body } = await overview()
    expect(status).toBe(200)
    expect(body.data.period.preset).toBe('month')
    expect(body.data.period.from.endsWith('-01')).toBe(true)
    expect(body.data.cards.map((card) => card.key)).toEqual([
      'new_entities',
      'requests_submitted',
      'assessments_completed',
      'accredited_average_percent',
      'payments_collected',
      'entities_active',
    ])
    expect(body.data.trends.bucket).toBe('day')
    expect(body.data.trends.series.entities).toHaveLength(body.data.trends.buckets.length)
    expect(body.data.paths.map((row) => row.path)).toEqual(['self', 'accredited', 'advanced'])
    expect(body.data.snapshot.map((widget) => widget.key)).toContain('entities_by_level')
  })

  it('places the period on the map: region figures add up their cities, requests match the card', async () => {
    seedMockSession({ id: 1, roles: ['system_admin'] })
    const { body } = await overview()
    const { regions, cities } = body.data.geography
    const sum = (rows, key) => rows.reduce((total, row) => total + row[key], 0)
    const requestsCard = body.data.cards.find((card) => card.key === 'requests_submitted')
    expect(regions).toHaveLength(13)
    expect(cities.every((city) => city.latitude !== null && city.longitude !== null)).toBe(true)
    expect(sum(regions, 'requests')).toBe(requestsCard.value)
    for (const region of regions) {
      const own = cities.filter((city) => city.regionId === region.regionId)
      expect(region.entities).toBe(sum(own, 'entities'))
    }
    expect(body.data.snapshot.map((widget) => widget.key)).not.toContain('entities_by_region')
  })

  it('buckets a long custom period by month and refuses a malformed one', async () => {
    seedMockSession({ id: 1, roles: ['system_admin'] })
    const { body } = await overview('?period=custom&from=2026-01-01&to=2026-10-31')
    expect(body.data.trends.bucket).toBe('month')
    expect(body.data.trends.buckets).toHaveLength(10)
    expect(body.data.previousPeriod).toEqual({
      from: '2025-03-03',
      to: '2025-12-31',
      basis: 'preceding_days',
    })

    const reversed = await overview('?period=custom&from=2026-10-31&to=2026-01-01')
    expect(reversed.status).toBe(422)
    expect(Object.keys(reversed.body.errors)).toEqual(['to'])
    expect((await overview('?period=custom')).body.errors).toHaveProperty('from')
    expect((await overview('?period=decade')).status).toBe(422)
    expect(
      (await overview('?period=custom&from=2025-12-01&to=2026-01-10')).body.errors,
    ).toHaveProperty('from')
  })

  describe('on a calendar month, quarter or year (today 25 November 2026)', () => {
    beforeAll(() => {
      vi.useFakeTimers({ toFake: ['Date'] })
      vi.setSystemTime(new Date('2026-11-25T09:00:00Z'))
    })
    afterAll(() => vi.useRealTimers())

    it('compares a running period with the same span of the previous one', async () => {
      seedMockSession({ id: 1, roles: ['system_admin'] })
      const { body } = await overview('?period=year')
      expect(body.data.period).toEqual({ from: '2026-01-01', to: '2026-11-25', preset: 'year' })
      expect(body.data.previousPeriod).toEqual({
        from: '2025-01-01',
        to: '2025-11-25',
        basis: 'elapsed_span',
      })
    })

    it('shows a finished month in full, compared with the previous month in full', async () => {
      seedMockSession({ id: 1, roles: ['system_admin'] })
      const { body } = await overview('?period=month&year=2026&month=9')
      expect(body.data.period).toEqual({ from: '2026-09-01', to: '2026-09-30', preset: 'month' })
      expect(body.data.previousPeriod).toEqual({
        from: '2026-08-01',
        to: '2026-08-31',
        basis: 'previous_full',
      })
    })

    it('refuses a future period and one before the first month', async () => {
      seedMockSession({ id: 1, roles: ['system_admin'] })
      expect((await overview('?period=month&year=2026&month=12')).body.errors).toHaveProperty(
        'year',
      )
      expect((await overview('?period=year&year=2025')).body.errors).toHaveProperty('year')
    })
  })

  it('keeps the overview to holders of the executive permission', async () => {
    seedMockSession({ id: 41, roles: ['finance_officer'] })
    expect((await overview()).status).toBe(403)
    clearMockSession()
    expect((await overview()).status).toBe(401)
  })

  it('serves an assessor their period figures, trend, paths and levels; others are refused', async () => {
    seedMockSession({ id: 10, roles: ['assessor'] })
    const { status, body } = await assessorOverview('?period=year')
    expect(status).toBe(200)
    expect(body.data.period.preset).toBe('year')
    expect(body.data.cards.map((card) => card.key)).toEqual([
      'completed',
      'average_duration_days',
      'average_result_percent',
      'completion_requests',
    ])
    const completed = body.data.cards[0].value
    expect(body.data.trends.series.completed.reduce((sum, value) => sum + value, 0)).toBe(completed)
    expect(body.data.paths.reduce((sum, row) => sum + row.completed, 0)).toBe(completed)
    expect(body.data.levels.map((row) => row.key)).toEqual([
      'diamond',
      'gold',
      'silver',
      'bronze',
      'none',
    ])
    expect((await assessorOverview('?period=custom&from=2026-11-10')).status).toBe(422)

    seedMockSession({ id: 1, roles: ['system_admin'] })
    expect((await assessorOverview()).status).toBe(403)
    clearMockSession()
    expect((await assessorOverview()).status).toBe(401)
  })
})
