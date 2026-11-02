import { readCookie } from '../../contract/cookies'

export const MOCK_ENVIRONMENT_COOKIE = 'moashir_mock_env'

const PRODUCTION = 'production'

export function mockIsProduction() {
  return readCookie(MOCK_ENVIRONMENT_COOKIE) === PRODUCTION
}
