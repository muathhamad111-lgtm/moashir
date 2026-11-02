import { describe, expect, it, beforeAll, afterAll, afterEach } from 'vitest'
import { setupServer } from 'msw/node'
import { usersHandlers } from '@/mocks/handlers/users'
import { MOCK_API_BASE, mockFetch } from '@/test-utils/mockApi'

const server = setupServer(...usersHandlers)
const BASE = MOCK_API_BASE

async function call(method, path, body) {
  const init = { method }
  if (body !== undefined) {
    init.headers = { 'content-type': 'application/json' }
    init.body = JSON.stringify(body)
  }
  const res = await mockFetch(`${BASE}${path}`, init)
  return { ok: res.ok, status: res.status, body: await res.json() }
}

async function retry(method, path, body, tries = 25) {
  let last
  for (let i = 0; i < tries; i += 1) {
    last = await call(method, path, body)
    if (last.ok) return last
  }
  throw new Error(`step failed: ${method} ${path} (status ${last?.status})`)
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

describe('users mock — multi-role directory (note 6)', () => {
  it('lists users carrying a roles[] array', async () => {
    const { body } = await retry('GET', '/users?per_page=50')
    expect(body.data.length).toBeGreaterThan(0)
    for (const user of body.data) {
      expect(Array.isArray(user.roles)).toBe(true)
    }
    const multi = body.data.find((u) => u.roles.length > 1)
    expect(multi).toBeTruthy()
  })

  it('counts a multi-role user under each of its roles in the stats', async () => {
    const { body } = await retry('GET', '/users?per_page=50')
    const byRole = body.meta.stats.byRole
    const total = Object.values(byRole).reduce((sum, n) => sum + n, 0)
    const roleCount = body.meta.stats.total
    expect(total).toBeGreaterThan(roleCount)
  })

  it('filters by any role a user holds (filter[role])', async () => {
    const { body } = await retry('GET', '/users?filter[role]=filtering_officer&perPage=50')
    expect(body.data.length).toBeGreaterThan(0)
    for (const user of body.data) {
      expect(user.roles).toContain('filtering_officer')
    }
    expect(body.data.some((u) => u.roles.includes('request_follower'))).toBe(true)
  })

  it('creates and updates a user with multiple roles', async () => {
    const created = await retry('POST', '/users', {
      name: 'Test Multi',
      email: `multi${Math.floor(1)}@muasher.sa`,
      roles: ['award_secretary', 'system_admin'],
      status: 'active',
    })
    expect(created.body.data.roles).toEqual(['award_secretary', 'system_admin'])
    const updated = await retry('PUT', `/users/${created.body.data.id}`, {
      roles: ['award_secretary'],
    })
    expect(updated.body.data.roles).toEqual(['award_secretary'])
  })
})
