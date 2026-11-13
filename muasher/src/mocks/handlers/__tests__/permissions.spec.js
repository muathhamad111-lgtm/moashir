import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { permissionsHandlers } from '@/mocks/handlers/permissions'
import { PERMISSION_KEYS } from '@/constants/permissionKeys'
import { MOCK_API_BASE, clearMockSession, mockFetch, seedMockSession } from '@/test-utils/mockApi'

const server = setupServer(...permissionsHandlers)

async function call(method, path, body) {
  const init = { method }
  if (body !== undefined) {
    init.headers = { 'content-type': 'application/json' }
    init.body = JSON.stringify(body)
  }
  const res = await mockFetch(`${MOCK_API_BASE}${path}`, init)
  return { ok: res.ok, status: res.status, body: await res.json() }
}

async function retry(method, path, body, tries = 25) {
  let last
  for (let i = 0; i < tries; i += 1) {
    last = await call(method, path, body)
    if (last.status < 500) return last
  }
  throw new Error(`step failed: ${method} ${path} (status ${last?.status})`)
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => seedMockSession({ id: 1, roles: ['system_admin'] }))
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('permissions mock — code-defined catalog + role matrix', () => {
  it('returns the code-defined catalog, the roles and the matrix', async () => {
    const { body } = await retry('GET', '/permissions')
    expect(body.data.permissions.map((item) => item.key)).toEqual(PERMISSION_KEYS)
    expect(body.data.roles.map((role) => role.key)).not.toContain('super_admin')
    expect(body.data.matrix.system_admin).toContain('users.manage')
    expect(body.data.matrix.system_admin).not.toContain('award.committee.score')
  })

  it('filters the catalog by module', async () => {
    const { body } = await retry('GET', '/permissions?module=finance')
    expect(body.data.permissions.every((item) => item.module === 'finance')).toBe(true)
  })

  it('exposes no create or delete endpoint for permission keys', () => {
    const routes = permissionsHandlers.map(
      (handler) => `${handler.info.method} ${handler.info.path}`,
    )
    expect(routes.some((route) => route.startsWith('POST /api/v1/permissions'))).toBe(false)
    expect(routes.some((route) => route.startsWith('DELETE'))).toBe(false)
  })

  it('updates the bilingual labels and validates them', async () => {
    const bad = await retry('PUT', '/permissions/users.view', { label: { ar: 'ع', en: '' } })
    expect(bad.status).toBe(422)
    expect(Object.keys(bad.body.errors)).toEqual(expect.arrayContaining(['label.ar', 'label.en']))
    const ok = await retry('PUT', '/permissions/users.view', {
      label: { ar: 'عرض المستخدمين', en: 'View staff users' },
      description: { ar: 'وصف', en: 'Read staff users.' },
    })
    expect(ok.body.data.label.en).toBe('View staff users')
    expect(
      (await retry('PUT', '/permissions/unknown.key', { label: { ar: 'عرض', en: 'View' } })).status,
    ).toBe(404)
  })

  it('replaces a role row, rejecting unknown and locked keys and the entity role', async () => {
    const ok = await retry('PUT', '/roles/request_follower/permissions', {
      permissionKeys: ['dashboard.view', 'requests.queue.view'],
    })
    expect(ok.body.data.permissionKeys).toEqual(['dashboard.view', 'requests.queue.view'])
    const unknown = await retry('PUT', '/roles/request_follower/permissions', {
      permissionKeys: ['committeeManagement.manage'],
    })
    expect(unknown.body.code).toBe('permission.unknown')
    const locked = await retry('PUT', '/roles/request_follower/permissions', {
      permissionKeys: ['settings.branding.manage'],
    })
    expect(locked.body.code).toBe('permission.locked')
    const entity = await retry('PUT', '/roles/entity/permissions', { permissionKeys: [] })
    expect(entity.body.code).toBe('role.not_editable')
  })

  it('denies the matrix to a session without roles.matrix.view', async () => {
    seedMockSession({ id: 9, roles: ['request_follower'] })
    expect((await retry('GET', '/permissions')).status).toBe(403)
    clearMockSession()
    expect((await retry('GET', '/permissions')).status).toBe(401)
  })
})
