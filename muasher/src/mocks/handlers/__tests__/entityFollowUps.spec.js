import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { entitiesHandlers } from '@/mocks/handlers/entities'
import { entityLifecycleHandlers } from '@/mocks/handlers/entityLifecycle'
import { entityProfileChangesHandlers } from '@/mocks/handlers/entityProfileChanges'
import { privacyRequestsHandlers } from '@/mocks/handlers/privacyRequests'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...entityLifecycleHandlers, ...entityProfileChangesHandlers, ...entitiesHandlers, ...privacyRequestsHandlers)

const SYSTEM_ADMIN = { id: 2, name: 'منيرة بنت خالد العتيبي', roles: ['system_admin'] }
const FINANCE = { id: 31, roles: ['finance_officer'] }
const ENTITY = { id: 5001, roles: ['entity'], entityId: 5001 }
const PRIVACY_ONLY = { id: 40, roles: ['content_officer'] }

async function call(method, path, body) {
  const init = { method, headers: { 'accept-language': 'en' } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => resetEntitiesMock())
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('profile change history mocks', () => {
  it('gives staff the identity and role of staff edits, with a null-name case', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const { status, body } = await call('GET', '/entities/5001/profile-changes')
    expect(status).toBe(200)
    expect(body.meta.pagination).toMatchObject({ page: 1, total: 4 })
    const staffRow = body.data.find((row) => row.id === 9002)
    expect(staffRow.changed_by_staff).toEqual({ name: 'منيرة بنت خالد العتيبي', roles: [{ key: 'system_admin', label: { ar: 'مدير النظام', en: 'System Administrator' } }] })
    expect(body.data.find((row) => row.id === 9003).changed_by_staff).toEqual({ name: null, roles: [] })
    expect(body.data.find((row) => row.id === 9004).changed_by_staff).toBeNull()
  })

  it('never returns staff identity on the entity own history', async () => {
    seedMockSession(ENTITY)
    const { body } = await call('GET', '/entities/me/profile-changes')
    expect(body.data.map((row) => row.changed_by)).toEqual(['system', 'staff', 'staff', 'entity'])
    expect(JSON.stringify(body)).not.toContain('changed_by_staff')
    expect(JSON.stringify(body)).not.toContain('منيرة')
  })

  it('denies the contact-only finance view', async () => {
    seedMockSession(FINANCE)
    expect((await call('GET', '/entities/5001/profile-changes')).status).toBe(403)
  })
})

describe('privacy entity picker mock', () => {
  it('lists entities for privacy.requests.manage only, with is_deleted and reference search', async () => {
    seedMockSession(PRIVACY_ONLY)
    expect((await call('GET', '/privacy/entities')).status).toBe(403)
    seedMockSession(SYSTEM_ADMIN)
    const all = await call('GET', '/privacy/entities?limit=20')
    expect(all.body.meta.limit).toBe(20)
    expect(all.body.data.some((item) => item.is_deleted === true)).toBe(true)
    const byReference = await call('GET', '/privacy/entities?search=ENT-005001')
    expect(byReference.body.data).toEqual([{ id: 5001, reference: 'ENT-005001', name: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' }, is_deleted: false }])
    expect((await call('GET', '/privacy/entities?limit=50')).status).toBe(422)
  })
})

describe('frozen flag on the deletion impact', () => {
  it('marks open requests of a deleted entity as frozen', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const before = await call('GET', '/entities/5001/deletion-impact')
    expect(before.body.data.open_requests[0].is_frozen).toBe(false)
    const deleted = await call('GET', '/entities/7002/deletion-impact')
    expect(deleted.body.data.open_requests[0]).toMatchObject({ number: 'ADV-2026-000044', is_frozen: true })
  })
})
