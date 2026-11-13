import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { usersHandlers } from '@/mocks/handlers/users'
import { resetStaffUsers } from '@/mocks/stores/staffUsers'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...usersHandlers)
const ADMIN = { id: 1, roles: ['system_admin'] }
const SECRETARY_ONLY = { id: 19, roles: ['award_secretary'] }
const USER_KEYS = [
  'assessor_qualifications',
  'created_at',
  'email',
  'entity_id',
  'id',
  'last_login_at',
  'mfa_enabled',
  'name',
  'phone',
  'request_scope',
  'roles',
  'status',
]
const REASON = 'إيقاف الحساب بقرار إداري لحين استكمال الإجراءات.'

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
beforeEach(() => {
  resetStaffUsers()
  seedMockSession(ADMIN)
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('users mock — real contract', () => {
  it('lists staff users only with the exact keys, stats and pagination', async () => {
    const { status, body } = await call('GET', '/users?per_page=50')
    expect(status).toBe(200)
    expect(Object.keys(body.data[0]).sort()).toEqual(USER_KEYS)
    expect(body.data.some((user) => user.roles.includes('entity'))).toBe(false)
    expect(Object.keys(body.meta.stats).sort()).toEqual([
      'active',
      'by_role',
      'invited',
      'locked',
      'suspended',
      'total',
    ])
    expect(body.meta.pagination).toMatchObject({ page: 1, per_page: 50 })
  })

  it('filters by role and status and refuses unknown filters', async () => {
    const byRole = await call('GET', '/users?filter[role]=filtering_officer&per_page=50')
    expect(byRole.body.data.every((user) => user.roles.includes('filtering_officer'))).toBe(true)
    const invited = await call('GET', '/users?filter[status]=invited')
    expect(invited.body.data.map((user) => user.status)).toEqual(['invited'])
    expect((await call('GET', '/users?filter[status]=inactive')).status).toBe(422)
  })

  it('invites, updates and validates staff users', async () => {
    const created = await call('POST', '/users', {
      name: 'نورة بنت سالم العنزي',
      email: 'noura.alanazi@moashir.example.sa',
      roles: ['award_secretary', 'system_admin'],
    })
    expect(created.status).toBe(201)
    expect(created.body.data).toMatchObject({
      status: 'invited',
      mfa_enabled: false,
      roles: ['award_secretary', 'system_admin'],
    })
    const updated = await call('PUT', `/users/${created.body.data.id}`, {
      roles: ['award_secretary'],
    })
    expect(updated.body.data.roles).toEqual(['award_secretary'])
    const duplicate = await call('POST', '/users', {
      name: 'اسم مكرر',
      email: 'noura.alanazi@moashir.example.sa',
      roles: ['assessor'],
    })
    expect(duplicate.status).toBe(422)
    expect(Object.keys(duplicate.body.errors)).toEqual(['email'])
  })

  it('suspends with a reason, reactivates, unlocks, resets MFA and resends invitations', async () => {
    expect((await call('POST', '/users/2/suspend', {})).status).toBe(422)
    const suspended = await call('POST', '/users/2/suspend', { reason: REASON })
    expect(suspended.body.data.status).toBe('suspended')
    expect((await call('POST', '/users/2/suspend', { reason: REASON })).body.code).toBe(
      'state.transition_illegal',
    )
    expect((await call('POST', '/users/2/activate', {})).body.data.status).toBe('active')
    expect((await call('POST', '/users/14/unlock', {})).body.data.status).toBe('active')
    expect(
      (await call('POST', '/users/2/mfa/reset', { reason: REASON })).body.data.mfa_enabled,
    ).toBe(false)
    expect((await call('POST', '/users/4/invitation/resend')).status).toBe(202)
    expect((await call('POST', '/users/2/invitation/resend')).status).toBe(409)
  })

  it('refuses actions on the own account and denies readers without the permission', async () => {
    const self = await call('POST', '/users/1/suspend', { reason: REASON })
    expect(self.status).toBe(422)
    expect(self.body.code).toBe('user.self')
    seedMockSession(SECRETARY_ONLY)
    expect((await call('GET', '/users')).status).toBe(403)
    expect((await call('GET', '/users/99999')).status).toBe(403)
  })
})
