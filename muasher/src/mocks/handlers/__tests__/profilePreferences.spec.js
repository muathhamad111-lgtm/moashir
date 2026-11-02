import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { profileHandlers } from '@/mocks/handlers/profile'
import { authHandlers } from '@/mocks/handlers/auth'
import { forgetLocales } from '@/mocks/stores/preferencesStore'
import { MOCK_API_BASE, clearMockSession, mockFetch, seedMockSession } from '@/test-utils/mockApi'

const server = setupServer(...profileHandlers, ...authHandlers)

async function call(method, path, body) {
  const init = { method, headers: {} }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await mockFetch(`${MOCK_API_BASE}${path}`, init)
  return { status: response.status, body: await response.json() }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  forgetLocales()
  document.cookie = 'locale=ar; path=/'
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('mock /me/preferences', () => {
  it('requires a session', async () => {
    expect((await call('PATCH', '/me/preferences', { locale: 'en' })).status).toBe(401)
  })

  it('saves the language on the profile so the next session check returns it', async () => {
    seedMockSession({ id: 7101, roles: ['criteria_officer'], locale: 'ar' })
    const saved = await call('PATCH', '/me/preferences', { locale: 'en' })
    expect(saved.body.data).toEqual({ locale: 'en', theme: 'system' })
    const session = await call('GET', '/auth/session')
    expect(session.body.data.locale).toBe('en')
    expect((await call('GET', '/me/preferences')).body.data.locale).toBe('en')
  })

  it('rejects an unsupported language', async () => {
    seedMockSession({ id: 7101, roles: ['criteria_officer'] })
    const rejected = await call('PATCH', '/me/preferences', { locale: 'fr' })
    expect(rejected.status).toBe(422)
    expect(rejected.body.errors.locale).toBeTruthy()
  })
})
