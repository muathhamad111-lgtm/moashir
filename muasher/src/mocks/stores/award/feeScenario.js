import { readCookie } from '../../contract/cookies'

const SCENARIO_COOKIE = 'moashir_mock_scenario'
const OPEN_STATUS = 'submission-open'
const FREE_FEE = '0.00'

export const AWARD_FEE_SCENARIO = 'award-fee'
export const SCENARIO_ENTRY_FEE = '500.00'
export const SCENARIO_ANCHOR = '2026-11-05T09:00:00.000Z'

function pricedScenario() {
  return typeof document !== 'undefined' && readCookie(SCENARIO_COOKIE) === AWARD_FEE_SCENARIO
}

export function seededEntryFee(fixture) {
  if (fixture.status === OPEN_STATUS && pricedScenario()) return SCENARIO_ENTRY_FEE
  return fixture.entryFee ?? FREE_FEE
}

export function scenarioAnchor() {
  return pricedScenario() ? SCENARIO_ANCHOR : null
}
