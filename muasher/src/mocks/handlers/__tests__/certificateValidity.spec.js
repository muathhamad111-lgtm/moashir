import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from '@/mocks/handlers'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'
import { pinMockClock, releaseMockClock } from '@/test-utils/mockClock'
import { CERTIFICATE_SCENARIOS } from '@/mocks/stores/certificateScenarios'

const server = setupServer(...handlers)
const SCENARIO_COOKIE = 'moashir_mock_scenario'
const NAMAA = { id: 7601, roles: ['entity'], entityId: 6001 }
const RUWAD = { id: 7605, roles: ['entity'], entityId: 6005 }
const ISSUED = '/certificates?filter[status]=issued&per_page=1'

async function levelCertificate() {
  const response = await wireFetch(`${MOCK_API_BASE}${ISSUED}`, {
    headers: { 'accept-language': 'ar' },
  })
  return (await response.json()).data[0]
}

function setScenario(value) {
  document.cookie = `${SCENARIO_COOKIE}=${value}; path=/`
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => pinMockClock())
afterEach(() => {
  releaseMockClock()
  setScenario('; max-age=0')
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('certificate validity in the mock contract', () => {
  it('derives the validity of an issued certificate from its expiry date', async () => {
    seedMockSession(NAMAA)
    const certificate = await levelCertificate()
    expect(certificate.valid_until).toBe('2027-09-04')
    expect(certificate.validity).toBe('valid')
    expect(certificate.days_until_expiry).toBe(303)
    expect(certificate.is_expired).toBe(false)
  })

  it('serves an expiring-soon certificate 18 days before its expiry', async () => {
    seedMockSession(RUWAD)
    setScenario(CERTIFICATE_SCENARIOS.EXPIRING)
    const certificate = await levelCertificate()
    expect(certificate.valid_until).toBe('2026-11-23')
    expect(certificate.validity).toBe('expiring_soon')
    expect(certificate.days_until_expiry).toBe(18)
  })

  it('serves an expired certificate that keeps its level and status', async () => {
    seedMockSession(NAMAA)
    setScenario(CERTIFICATE_SCENARIOS.EXPIRED)
    const certificate = await levelCertificate()
    expect(certificate.status).toBe('issued')
    expect(certificate.level).toBe('silver')
    expect(certificate.validity).toBe('expired')
    expect(certificate.days_until_expiry).toBeNull()
    expect(certificate.is_expired).toBe(true)
  })
})
