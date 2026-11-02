import config from '../../playwright.config.js'

export const FORCED_FAULT_COOKIE = 'moashir_mock_fault'
const APP_URL = config.use.baseURL

export function forceMockFault(page, fault) {
  return page.context().addCookies([
    { name: FORCED_FAULT_COOKIE, value: encodeURIComponent(JSON.stringify([fault])), url: APP_URL },
  ])
}

export function clearMockFault(page) {
  return page.context().clearCookies({ name: FORCED_FAULT_COOKIE })
}
