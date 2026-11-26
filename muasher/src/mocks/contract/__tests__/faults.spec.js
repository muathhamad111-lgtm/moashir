import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { setupServer } from 'msw/node'
import { http } from '@/mocks/contract/http'
import { envelope } from '@/mocks/contract/responses'
import {
  FORCED_FAULT_COOKIE,
  RANDOM_FAULTS_COOKIE,
  clearMockFaults,
  failsRandomly,
  forceMockFault,
  randomFaultsEnabled,
  setRandomFaults,
} from '@/mocks/contract/faults'
import { MOCK_API_BASE, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(http.get('/api/v1/probe', () => envelope({ ok: true })))

function setCookie(name, value) {
  document.cookie = `${name}=${encodeURIComponent(value)}; path=/`
}

function clearCookie(name) {
  document.cookie = `${name}=; path=/; max-age=0`
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => {
  clearMockFaults()
  clearCookie(RANDOM_FAULTS_COOKIE)
  clearCookie(FORCED_FAULT_COOKIE)
  vi.restoreAllMocks()
})
afterAll(() => server.close())

describe('mock fault switch', () => {
  it('keeps random failures off in unit tests unless a test asks for them', () => {
    vi.spyOn(Math, 'random').mockReturnValue(0)
    const maybeFail = failsRandomly(0.5)
    expect(randomFaultsEnabled()).toBe(false)
    expect(maybeFail()).toBe(false)
    setRandomFaults(true)
    expect(maybeFail()).toBe(true)
  })

  it('follows the cookie that the e2e configuration sets', () => {
    setCookie(RANDOM_FAULTS_COOKIE, 'on')
    expect(randomFaultsEnabled()).toBe(true)
    setCookie(RANDOM_FAULTS_COOKIE, 'off')
    expect(randomFaultsEnabled()).toBe(false)
  })

  it('forces an explicit error on one endpoint through the contract facade', async () => {
    forceMockFault({ method: 'GET', path: '/api/v1/probe', status: 503 })
    const failed = await wireFetch(`${MOCK_API_BASE}/probe`, {
      headers: { 'accept-language': 'en' },
    })
    expect(failed.status).toBe(503)
    expect(await failed.json()).toMatchObject({ code: 'service_unavailable', data: null })
    clearMockFaults()
    expect((await wireFetch(`${MOCK_API_BASE}/probe`)).status).toBe(200)
  })

  it('reads forced errors from the cookie used by the e2e helper', async () => {
    setCookie(
      FORCED_FAULT_COOKIE,
      JSON.stringify([{ method: 'GET', path: '/api/v1/probe', status: 500 }]),
    )
    expect((await wireFetch(`${MOCK_API_BASE}/probe`)).status).toBe(500)
  })
})
