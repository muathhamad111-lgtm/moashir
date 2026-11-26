import { readCookie } from '../contract/cookies'

const SCENARIO_COOKIE = 'moashir_mock_scenario'

// A slow session check, as on a distant production server: the boot waits long enough for
// the official waiting page to show.
export const SLOW_SESSION_SCENARIO = 'slow-session'

const SESSION_LATENCY_MS = 120
const SLOW_SESSION_LATENCY_MS = 1500

export function sessionLatencyMs() {
  const isSlow =
    typeof document !== 'undefined' && readCookie(SCENARIO_COOKIE) === SLOW_SESSION_SCENARIO
  return isSlow ? SLOW_SESSION_LATENCY_MS : SESSION_LATENCY_MS
}
