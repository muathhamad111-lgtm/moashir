import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { setupServer } from 'msw/node'
import { http, HttpResponse } from 'msw'
import { apiClient } from '@/api/client'
import { onSessionExpired } from '@/api/sessionEvents'
import { i18n } from '@/i18n'

const ORIGIN = window.location.origin
const requests = []
let csrfFetches = 0
let mismatchesLeft = 0

function record(request) {
  requests.push({
    method: request.method,
    path: new URL(request.url).pathname,
    language: request.headers.get('accept-language'),
    xsrf: request.headers.get('x-xsrf-token'),
    authorization: request.headers.get('authorization'),
  })
}

const server = setupServer(
  http.get(`${ORIGIN}/sanctum/csrf-cookie`, () => {
    csrfFetches += 1
    document.cookie = `XSRF-TOKEN=${encodeURIComponent(`token-${csrfFetches}==`)}; path=/`
    return new HttpResponse(null, { status: 204 })
  }),
  http.all(`${ORIGIN}/api/v1/echo`, ({ request }) => {
    record(request)
    if (mismatchesLeft > 0) {
      mismatchesLeft -= 1
      return HttpResponse.json({ message: 'CSRF token mismatch.', code: 'csrf.token_mismatch', errors: {} }, { status: 419 })
    }
    return HttpResponse.json({ data: { ok: true }, meta: {}, errors: null })
  }),
  http.get(`${ORIGIN}/api/v1/private`, ({ request }) => {
    record(request)
    return HttpResponse.json(
      { data: null, meta: {}, message: 'Unauthenticated.', code: 'unauthenticated', errors: {} },
      { status: 401 },
    )
  }),
)

function clearCookies() {
  document.cookie = 'XSRF-TOKEN=; path=/; max-age=0'
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  requests.length = 0
  csrfFetches = 0
  mismatchesLeft = 0
  clearCookies()
  i18n.global.locale.value = 'ar'
})
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

describe('apiClient — Sanctum SPA transport', () => {
  it('sends Accept-Language with the active locale on every request', async () => {
    await apiClient.get('/echo')
    i18n.global.locale.value = 'en'
    await apiClient.get('/echo')
    expect(requests.map((entry) => entry.language)).toEqual(['ar', 'en'])
  })

  it('fetches the CSRF cookie before the first state-changing call and sends X-XSRF-TOKEN', async () => {
    await apiClient.post('/echo', { name: 'value' })
    await apiClient.put('/echo', { name: 'value' })
    expect(csrfFetches).toBe(1)
    expect(requests.map((entry) => entry.xsrf)).toEqual(['token-1==', 'token-1=='])
  })

  it('does not fetch the CSRF cookie for reads', async () => {
    await apiClient.get('/echo')
    expect(csrfFetches).toBe(0)
  })

  it('never sends a bearer token and keeps browser storage empty', async () => {
    localStorage.clear()
    sessionStorage.clear()
    await apiClient.post('/echo', {})
    expect(requests[0].authorization).toBeNull()
    expect(localStorage.length).toBe(0)
    expect(sessionStorage.length).toBe(0)
  })

  it('re-fetches the CSRF cookie once on 419 and retries the request', async () => {
    document.cookie = 'XSRF-TOKEN=stale; path=/'
    mismatchesLeft = 1
    const response = await apiClient.post('/echo', {})
    expect(response.data.data.ok).toBe(true)
    expect(csrfFetches).toBe(1)
    expect(requests.map((entry) => entry.xsrf)).toEqual(['stale', 'token-1=='])
  })

  it('reports the session as expired when the retry also fails with 419', async () => {
    const listener = vi.fn()
    const stop = onSessionExpired(listener)
    document.cookie = 'XSRF-TOKEN=stale; path=/'
    mismatchesLeft = 2
    await expect(apiClient.post('/echo', {})).rejects.toMatchObject({ normalized: { status: 419 } })
    expect(csrfFetches).toBe(1)
    expect(listener).toHaveBeenCalledTimes(1)
    stop()
  })

  it('reports the session as expired on 401 and normalizes the error', async () => {
    const listener = vi.fn()
    const stop = onSessionExpired(listener)
    const error = await apiClient.get('/private').catch((caught) => caught)
    expect(error.normalized).toEqual({
      message: 'Unauthenticated.',
      fieldErrors: {},
      meta: {},
      code: 'unauthenticated',
      status: 401,
    })
    expect(listener).toHaveBeenCalledTimes(1)
    stop()
  })

  it('lets the auth flow opt out of the session-expired redirect', async () => {
    const listener = vi.fn()
    const stop = onSessionExpired(listener)
    await apiClient.get('/private', { skipSessionExpiry: true }).catch(() => {})
    expect(listener).not.toHaveBeenCalled()
    stop()
  })
})
