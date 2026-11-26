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

  it('switches channels per audience like the API and reports them in the catalogue', async () => {
    seedMockSession(ADMIN)
    const off = await call('PATCH', '/settings/notifications/types/REG-01/channels', {
      audience: 'management',
      channels: { email: false },
    })
    expect(off.status).toBe(200)
    const email = off.body.data.channel_settings.filter((row) => row.channel === 'email')
    expect(email.map((row) => [row.audience, row.is_enabled])).toEqual([
      ['beneficiary', true],
      ['management', false],
    ])
    const row = (await call('GET', '/settings/notifications/types')).body.data.find(
      (type) => type.code === 'REG-01',
    )
    expect(row.channel_states.management.email).toBe(false)
    expect(row.channel_states.beneficiary.email).toBe(true)
    expect(row.is_core).toBe(false)
    const core = await call('PATCH', '/settings/notifications/types/USR-01/channels', {
      channels: { email: false },
    })
    expect(core.status).toBe(422)
    const unreached = await call('PATCH', '/settings/notifications/types/STD-01/channels', {
      audience: 'beneficiary',
      channels: { email: true },
    })
    expect(Object.keys(unreached.body.errors)).toEqual(['audience'])
  })

  it('switches many types at once and leaves the core ones alone', async () => {
    seedMockSession(ADMIN)
    const bulk = await call('PATCH', '/settings/notifications/channels', {
      scope: 'codes',
      codes: ['REG-01', 'USR-01'],
      channels: ['email'],
      enabled: false,
    })
    expect(bulk.status).toBe(200)
    expect(bulk.body.meta.updated).toBe(1)
    const rows = Object.fromEntries(bulk.body.data.map((type) => [type.code, type]))
    expect(rows['REG-01'].channel_states.beneficiary.email).toBe(false)
    expect(rows['USR-01'].channel_states.beneficiary.email).toBe(true)
    expect((await call('PATCH', '/settings/notifications/channels', { scope: 'x' })).status).toBe(
      422,
    )
  })

  it('gives texts their audience and parts their channel, and locks the core button', async () => {
    seedMockSession(ADMIN)
    const shown = (await call('GET', '/settings/notifications/types/REG-01')).body.data
    expect(shown.texts.map((text) => text.audience)).toEqual(['beneficiary', 'management'])
    expect(shown.texts[0].parts.email_subject.channel).toBe('email')
    expect(shown.texts[0].parts.title.channel).toBe('in_app')
    const invitation = (await call('GET', '/settings/notifications/types/USR-01')).body.data
    expect(invitation.is_core).toBe(true)
    expect(invitation.texts[0].parts.action.is_fixed).toBe(true)
    const button = await call('PUT', '/settings/notifications/texts/staff_invited', {
      parts: { action: { ar: 'اضغط', en: 'Click' } },
    })
    expect(Object.keys(button.body.errors)).toContain('parts.action')
  })

  it('refuses a text message longer than three messages', async () => {
    seedMockSession(ADMIN)
    const long = await call('PUT', '/settings/notifications/texts/entities.REG-01.entity', {
      parts: { sms: { ar: 'ن'.repeat(202), en: 'Short' } },
    })
    expect(long.status).toBe(422)
    expect(Object.keys(long.body.errors)).toEqual(['parts.sms.ar'])
  })

  it('answers 404 for unknown types or keys and 403 without the permission', async () => {
    seedMockSession(ADMIN)
    expect((await call('GET', '/settings/notifications/types/XYZ-99')).status).toBe(404)
    expect((await call('PUT', '/settings/notifications/texts/nope', REGISTERED)).status).toBe(404)
    seedMockSession(ASSESSOR)
    expect((await call('GET', '/settings/notifications/types/REG-01')).status).toBe(403)
  })
})
