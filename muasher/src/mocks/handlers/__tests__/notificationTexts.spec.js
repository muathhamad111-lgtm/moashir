import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { notificationTypesHandlers } from '@/mocks/handlers/notificationTypes'
import { resetNotificationTextsStore } from '@/mocks/stores/notificationTexts'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...notificationTypesHandlers)
const ADMIN = { id: 2, roles: ['system_admin'] }
const ASSESSOR = { id: 21, roles: ['assessor'] }

async function call(method, path, body) {
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, {
    method,
    headers: { 'content-type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  })
  const text = await response.text()
  return { status: response.status, body: text ? JSON.parse(text) : null }
}

const REGISTERED = {
  parts: {
    title: { ar: 'سجّلت :entity', en: ':entity registered' },
    body: { ar: 'نرحب بـ :entity', en: 'Welcome :entity' },
  },
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => resetNotificationTextsStore())
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('notification texts mock', () => {
  it('serves a type with its texts, saves an edit and resets it', async () => {
    seedMockSession(ADMIN)
    const shown = await call('GET', '/settings/notifications/types/REG-01')
    expect(shown.body.data.texts.map((text) => text.variant)).toEqual(['entity', 'staff'])
    const saved = await call(
      'PUT',
      '/settings/notifications/texts/entities.REG-01.entity',
      REGISTERED,
    )
    expect(saved.status).toBe(200)
    expect(saved.body.data.texts[0].is_customized).toBe(true)
    expect(saved.body.data.texts[0].parts.title.current.ar).toBe('سجّلت :entity')
    expect(
      (await call('DELETE', '/settings/notifications/texts/entities.REG-01.entity')).status,
    ).toBe(204)
    const again = await call('GET', '/settings/notifications/types/REG-01')
    expect(again.body.data.texts[0].is_customized).toBe(false)
  })

  it('refuses unknown variables and removed required ones like the API', async () => {
    seedMockSession(ADMIN)
    const unknown = await call('PUT', '/settings/notifications/texts/entities.REG-01.entity', {
      parts: { ...REGISTERED.parts, title: { ar: 'مرحبًا :secret', en: 'Hi' } },
    })
    expect(unknown.status).toBe(422)
    expect(Object.keys(unknown.body.errors)).toContain('parts.title.ar')
    const removed = await call('PUT', '/settings/notifications/texts/staff_invited', {
      parts: { expiry: { ar: 'صالحة لمدة قصيرة', en: 'Valid for :duration' } },
    })
    expect(removed.status).toBe(422)
    expect(Object.keys(removed.body.errors)).toEqual(['parts.expiry.ar'])
  })

  it('switches channels like the API and reports them in the catalogue', async () => {
    seedMockSession(ADMIN)
    const off = await call('PATCH', '/settings/notifications/types/REG-01/channels', {
      channels: { email: false },
    })
    expect(off.status).toBe(200)
    expect(off.body.data.channel_settings.find((row) => row.channel === 'email').is_enabled).toBe(
      false,
    )
    const list = await call('GET', '/settings/notifications/types')
    expect(list.body.data.find((row) => row.code === 'REG-01').enabled_channels).toEqual(['in_app'])
    const mandatory = await call('PATCH', '/settings/notifications/types/USR-01/channels', {
      channels: { email: false },
    })
    expect(mandatory.status).toBe(422)
  })

  it('accepts an SMS text and an SMS switch for a type that is not mandatory', async () => {
    seedMockSession(ADMIN)
    const sms = { ar: 'سجّلت :entity', en: ':entity registered' }
    const saved = await call('PUT', '/settings/notifications/texts/entities.REG-01.entity', {
      parts: { ...REGISTERED.parts, sms },
    })
    expect(saved.body.data.texts[0].sms.current).toEqual(sms)
    const on = await call('PATCH', '/settings/notifications/types/REG-01/channels', {
      channels: { sms: true },
    })
    expect(on.body.data.channel_settings.find((row) => row.channel === 'sms').is_enabled).toBe(true)
    const refused = await call('PUT', '/settings/notifications/texts/staff_invited', {
      parts: { sms },
    })
    expect(refused.status).toBe(422)
  })

  it('answers 404 for unknown types or keys and 403 without the permission', async () => {
    seedMockSession(ADMIN)
    expect((await call('GET', '/settings/notifications/types/XYZ-99')).status).toBe(404)
    expect((await call('PUT', '/settings/notifications/texts/nope', REGISTERED)).status).toBe(404)
    seedMockSession(ASSESSOR)
    expect((await call('GET', '/settings/notifications/types/REG-01')).status).toBe(403)
  })
})
