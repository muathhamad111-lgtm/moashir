import { readCookie } from '../contract/cookies'
import { riyadhDateKey } from '@/utils/datetime'

const SCENARIO_COOKIE = 'moashir_mock_scenario'
const DAY_MS = 86_400_000
const ISSUED = 'issued'
const ACCREDITED = 'accredited'

export const CERTIFICATE_SCENARIOS = Object.freeze({
  EXPIRING: 'certificate-expiring',
  EXPIRED: 'certificate-expired',
  PENDING: 'certificate-pending',
})

const SCENARIO_DAY_OFFSETS = Object.freeze({
  [CERTIFICATE_SCENARIOS.EXPIRING]: 18,
  [CERTIFICATE_SCENARIOS.EXPIRED]: -12,
})

function activeScenario() {
  return typeof document === 'undefined' ? null : readCookie(SCENARIO_COOKIE)
}

export function scenarioValidUntil(certificate) {
  const offset = SCENARIO_DAY_OFFSETS[activeScenario()]
  if (offset === undefined || certificate.status !== ISSUED || certificate.kind !== ACCREDITED) {
    return null
  }
  return riyadhDateKey(new Date(Date.now() + offset * DAY_MS))
}

export function certificatesPending() {
  return activeScenario() === CERTIFICATE_SCENARIOS.PENDING
}
