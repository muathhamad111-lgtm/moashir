import { readCookie, writeCookie } from './cookies'
import { errorResponse } from './responses'

export const XSRF_COOKIE = 'XSRF-TOKEN'
export const XSRF_HEADER = 'x-xsrf-token'

const STATE_CHANGING = new Set(['post', 'put', 'patch', 'delete'])

export function issueXsrfCookie() {
  writeCookie(XSRF_COOKIE, crypto.randomUUID())
}

export function requiresCsrf(method) {
  return STATE_CHANGING.has(method)
}

export function hasValidCsrf(request) {
  const expected = readCookie(XSRF_COOKIE)
  return Boolean(expected) && request.headers.get(XSRF_HEADER) === expected
}

export function csrfMismatch() {
  return errorResponse(419, 'csrf.token_mismatch', 'CSRF token mismatch.')
}
