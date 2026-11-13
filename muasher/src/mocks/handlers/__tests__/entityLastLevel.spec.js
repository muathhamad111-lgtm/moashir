import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { resetCriteriaStore } from '@/mocks/stores/criteriaStore'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetAssessmentsStore } from '@/mocks/stores/assessments/assessmentsStore'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { CERTIFICATE_SCENARIOS } from '@/mocks/stores/certificateScenarios'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'
import { pinMockClock, releaseMockClock } from '@/test-utils/mockClock'

const server = setupServer(...handlers)
const SCENARIO_COOKIE = 'moashir_mock_scenario'
const ADMIN = { id: 2, roles: ['system_admin'] }
const FOLLOWER = { id: 9, roles: ['request_follower'] }
const LIST = '/entities?per_page=50'
const ALBIRR = 5001
const NEVER_CERTIFIED = 6003

async function call(path) {
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, {
    headers: { 'accept-language': 'en' },
  })
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

async function rowOf(id, query = '') {
  return (await call(`${LIST}${query}`)).body.data.find((row) => row.id === id)
}

async function idsOf(query) {
  return (await call(`${LIST}${query}`)).body.data.map((row) => row.id)
}

function setScenario(value) {
  document.cookie = `${SCENARIO_COOKIE}=${value}; path=/`
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  pinMockClock()
  resetCriteriaStore()
  resetEntitiesMock()
  resetFinanceState()
  resetAssessmentsStore()
})
afterEach(() => {
  releaseMockClock()
  setScenario('; max-age=0')
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('entity last level mock — display', () => {
  it('serves the valid level as both the counted and the last level', async () => {
    seedMockSession(ADMIN)
    const row = await rowOf(ALBIRR)
    expect(row.level).toBe('silver')
    expect(row.last_level).toEqual({
      level: 'silver',
      valid_until: row.level_valid_until,
      validity: 'valid',
      days_until_expiry: expect.any(Number),
    })
    expect((await rowOf(NEVER_CERTIFIED)).last_level).toBeNull()
  })

  it('keeps the counted level and adds the countdown while the certificate expires soon', async () => {
    seedMockSession(ADMIN)
    setScenario(CERTIFICATE_SCENARIOS.EXPIRING)
    const row = await rowOf(ALBIRR)
    expect(row.level).toBe('silver')
    expect(row.last_level).toMatchObject({ level: 'silver', validity: 'expiring_soon' })
    expect(row.last_level.days_until_expiry).toBe(18)
  })

  it('drops the counted level after expiry and keeps the last level for display', async () => {
    seedMockSession(ADMIN)
    setScenario(CERTIFICATE_SCENARIOS.EXPIRED)
    const row = await rowOf(ALBIRR)
    expect(row).toMatchObject({ level: null, level_certificate_number: null })
    expect(row.last_level).toMatchObject({
      level: 'silver',
      validity: 'expired',
      days_until_expiry: null,
    })
  })

  it('adds the last level to the entity of a request queue row', async () => {
    seedMockSession(FOLLOWER)
    setScenario(CERTIFICATE_SCENARIOS.EXPIRED)
    const rows = (await call('/requests?per_page=50')).body.data
    const certified = rows.find((row) => row.entity?.last_level)
    expect(certified.entity.level).toBeNull()
    expect(certified.entity.last_level.validity).toBe('expired')
  })
})

describe('entity last level mock — counting', () => {
  it('counts an expired entity under no level and the expired filter, never at its old level', async () => {
    seedMockSession(ADMIN)
    setScenario(CERTIFICATE_SCENARIOS.EXPIRED)
    expect(await idsOf('&filter[level]=silver')).toEqual([])
    expect(await idsOf('&filter[level]=none')).toEqual(
      expect.arrayContaining([ALBIRR, NEVER_CERTIFIED]),
    )
    const expired = await idsOf('&filter[level]=expired')
    expect(expired).toContain(ALBIRR)
    expect(expired).not.toContain(NEVER_CERTIFIED)
  })

  it('lists no entity under the expired filter while every certificate is valid', async () => {
    seedMockSession(ADMIN)
    expect(await idsOf('&filter[level]=expired')).toEqual([])
    expect(await idsOf('&filter[level]=silver')).toContain(ALBIRR)
  })

  it('moves expired entities out of their level bars on the dashboard chart', async () => {
    seedMockSession(ADMIN)
    setScenario(CERTIFICATE_SCENARIOS.EXPIRED)
    const summary = (await call('/dashboard/summary?board=system_admin')).body.data
    const bars = summary.widgets.find((widget) => widget.key === 'entities_by_level').data
    const counts = Object.fromEntries(bars.map((bar) => [bar.key, bar.count]))
    expect([
      counts.participation,
      counts.bronze,
      counts.silver,
      counts.gold,
      counts.diamond,
    ]).toEqual([0, 0, 0, 0, 0])
  })
})
