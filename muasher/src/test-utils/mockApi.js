import { XSRF_COOKIE, XSRF_HEADER } from '@/mocks/contract/csrf'
import { MOCK_SESSION_COOKIE } from '@/mocks/stores/sessionStore'
import { fromWire, toSnakeKey, toWire, hasCanonicalValue } from '@/api/caseMapping'
import { toCanonicalStatus } from '@/utils/canonicalStatus'

export const MOCK_API_BASE = `${window.location.origin}/api/v1`

const TEST_XSRF_TOKEN = 'unit-test-xsrf-token'

function primeCsrfCookie() {
  document.cookie = `${XSRF_COOKIE}=${TEST_XSRF_TOKEN}; path=/`
}

function encodeUrl(rawUrl) {
  const url = new URL(rawUrl)
  const encoded = new URLSearchParams()
  url.searchParams.forEach((value, key) => {
    encoded.append(toSnakeKey(key), hasCanonicalValue(key) ? toCanonicalStatus(value) : value)
  })
  url.search = encoded.toString()
  return url.toString()
}

function encodeBody(body) {
  if (typeof body !== 'string') return body
  return JSON.stringify(toWire(JSON.parse(body)))
}

async function decodeResponse(response) {
  if (!/application\/json/i.test(response.headers.get('content-type') ?? '')) return response
  const body = fromWire(await response.json())
  return new Response(JSON.stringify(body), {
    status: response.status,
    headers: { 'content-type': 'application/json' },
  })
}

export function wireFetch(url, init = {}) {
  primeCsrfCookie()
  const headers = { ...init.headers, [XSRF_HEADER]: TEST_XSRF_TOKEN }
  return fetch(url, { ...init, headers })
}

export async function mockFetch(url, init = {}) {
  const response = await wireFetch(encodeUrl(url), { ...init, body: encodeBody(init.body) })
  return decodeResponse(response)
}

export function seedMockSession(descriptor) {
  document.cookie = `${MOCK_SESSION_COOKIE}=${encodeURIComponent(JSON.stringify(descriptor))}; path=/`
}

export function clearMockSession() {
  document.cookie = `${MOCK_SESSION_COOKIE}=; path=/; max-age=0`
}
