import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { profileHandlers } from '@/mocks/handlers/profile'
import { authHandlers } from '@/mocks/handlers/auth'
import { forgetLocales, forgetTableColumns } from '@/mocks/stores/preferencesStore'
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
  forgetTableColumns()
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
    expect(saved.body.data).toEqual({ locale: 'en', theme: 'system', tableColumns: {} })
    const session = await call('GET', '/auth/session')
    expect(session.body.data.locale).toBe('en')
    expect((await call('GET', '/me/preferences')).body.data.locale).toBe('en')
  })

  it('keeps the columns chosen per table and returns them on the next read', async () => {
    seedMockSession({ id: 7102, roles: ['finance_officer'] })
    const saved = await call('PATCH', '/me/preferences', {
      table_columns: { 'finance-transactions': ['entity', 'total', 'receipt'] },
    })
    expect(saved.status).toBe(200)
    expect(saved.body.data.tableColumns).toEqual({
      'finance-transactions': ['entity', 'total', 'receipt'],
    })
    const read = await call('GET', '/me/preferences')
    expect(read.body.data.tableColumns['finance-transactions']).toEqual([
      'entity',
      'total',
      'receipt',
    ])
    await call('PATCH', '/me/preferences', { table_columns: { 'finance-transactions': null } })
    expect((await call('GET', '/me/preferences')).body.data.tableColumns).toEqual({})
  })

  it('rejects a table or a column the platform does not list', async () => {
    seedMockSession({ id: 7102, roles: ['finance_officer'] })
    const unknownTable = await call('PATCH', '/me/preferences', {
      table_columns: { 'secret-table': ['status'] },
    })
    expect(unknownTable.status).toBe(422)
    const unknownColumn = await call('PATCH', '/me/preferences', {
      table_columns: { 'finance-transactions': ['password'] },
    })
    expect(unknownColumn.status).toBe(422)
    expect((await call('GET', '/me/preferences')).body.data.tableColumns).toEqual({})
  })

  it('rejects an unsupported language', async () => {
    seedMockSession({ id: 7101, roles: ['criteria_officer'] })
    const rejected = await call('PATCH', '/me/preferences', { locale: 'fr' })
    expect(rejected.status).toBe(422)
    expect(rejected.body.errors.locale).toBeTruthy()
  })
})
