import { describe, expect, it, beforeAll, afterAll, afterEach, beforeEach } from 'vitest'
import { setupServer } from 'msw/node'
import { brandingHandlers } from '@/mocks/handlers/branding'
import { profileHandlers } from '@/mocks/handlers/profile'
import { permissionsHandlers } from '@/mocks/handlers/permissions'
import { storeBrandingLogo } from '@/mocks/stores/brandingStore'
import { MOCK_API_BASE, clearMockSession, mockFetch, seedMockSession } from '@/test-utils/mockApi'

const server = setupServer(...brandingHandlers, ...profileHandlers, ...permissionsHandlers)
const BASE = MOCK_API_BASE

function signInAs(roles) {
  seedMockSession({ id: 1, roles })
}

async function call(method, path, body) {
  const init = { method }
  if (body !== undefined) {
    init.headers = { 'content-type': 'application/json' }
    init.body = JSON.stringify(body)
  }
  const res = await mockFetch(`${BASE}${path}`, init)
  return { ok: res.ok, status: res.status, body: await res.json() }
}

async function retry(method, path, body, accept = (res) => res.ok, tries = 25) {
  let last
  for (let i = 0; i < tries; i += 1) {
    last = await call(method, path, body)
    if (accept(last)) return last
  }
  throw new Error(`step failed: ${method} ${path} (status ${last?.status})`)
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => clearMockSession())
afterEach(() => server.resetHandlers())
afterAll(() => {
  server.close()
  clearMockSession()
})

describe('branding mock (settings.branding.manage — hard-locked)', () => {
  it('serves the same theme to every user but the palette only to the System Administrator', async () => {
    signInAs(['content_officer'])
    const staff = await call('GET', '/settings/branding')
    expect(staff.body.data.accentToken).toBeTruthy()
    expect(staff.body.data.palette).toEqual([])
    signInAs(['system_admin'])
    const admin = await call('GET', '/settings/branding')
    expect(admin.body.data.palette).toHaveLength(9)
  })

  it('rejects updates from any other role with 403', async () => {
    signInAs(['content_officer', 'finance_officer'])
    const res = await call('PUT', '/settings/branding', { accentToken: 'teal' })
    expect(res.status).toBe(403)
  })

  it('accepts palette tokens only (no free hex)', async () => {
    signInAs(['system_admin'])
    const bad = await call('PUT', '/settings/branding', { accentToken: '#ff0000' })
    expect(bad.status).toBe(422)
    expect(bad.body.code).toBe('token.unknown')
    const good = await call('PUT', '/settings/branding', { accentToken: 'lavender' })
    expect(good.body.data.accentToken).toBe('lavender')
    const reset = await call('POST', '/settings/branding/reset')
    expect(reset.body.data.accentToken).toBe('brand_default')
  })

  it('stores one logo per background variant; only the global reset returns both to the brand default', async () => {
    signInAs(['system_admin'])
    storeBrandingLogo('dark', 'data:image/svg+xml;base64,PHN2Zy8+')
    storeBrandingLogo('light', 'data:image/png;base64,TElHSFQ=')
    const theme = await call('GET', '/settings/branding')
    expect(theme.body.data.logoDark.url).toMatch(/^data:image\/svg\+xml/)
    expect(theme.body.data.logoLight.url).toMatch(/^data:image\/png/)
    const reset = await call('POST', '/settings/branding/reset')
    expect(reset.body.data.logoDark).toBeNull()
    expect(reset.body.data.logoLight).toBeNull()
  })

  it('serves no per-logo restore endpoint', () => {
    const routes = brandingHandlers.map((handler) => `${handler.info.method} ${handler.info.path}`)
    expect(routes).toEqual([
      'GET /api/v1/settings/branding',
      'PUT /api/v1/settings/branding',
      'POST /api/v1/settings/branding/logo',
      'POST /api/v1/settings/branding/reset',
    ])
  })

  it('rejects the global reset from non-administrators with 403', async () => {
    signInAs(['content_officer', 'finance_officer'])
    const denied = await call('POST', '/settings/branding/reset')
    expect(denied.status).toBe(403)
    expect(denied.body.code).toBe('forbidden')
  })
})

describe('profile permissions mock', () => {
  it('returns the session roles with bilingual labels and their permissions', async () => {
    signInAs(['request_follower', 'content_officer'])
    const { body } = await call('GET', '/me/profile/permissions')
    expect(body.data.roles.map((role) => role.key)).toEqual(['request_follower', 'content_officer'])
    expect(body.data.roles[0].label.en).toBe('Assessment Requests Follower')
    const keys = body.data.permissions.map((permission) => permission.key)
    expect(keys).toContain('content.kpis.manage')
    expect(keys).toContain('requests.queue.view')
    expect(keys).not.toContain('settings.branding.manage')
  })

  it('answers 401 without a session', async () => {
    const res = await call('GET', '/me/profile/permissions')
    expect(res.status).toBe(401)
  })
})

describe('permissions mock — locked key', () => {
  it('refuses to grant settings.branding.manage to another role', async () => {
    signInAs(['system_admin'])
    const { body } = await retry('GET', '/permissions')
    const locked = body.data.permissions.find((permission) => permission.key === 'settings.branding.manage')
    expect(locked.lockedToRoles).toEqual(['system_admin'])
    const res = await retry(
      'PUT',
      '/roles/content_officer/permissions',
      { permissionKeys: ['settings.branding.manage'] },
      (response) => response.status < 500,
    )
    expect(res.status).toBe(422)
    expect(res.body.code).toBe('permission.locked')
  })
})
