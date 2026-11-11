import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { entitiesHandlers } from '@/mocks/handlers/entities'
import { entityEmailChangesHandlers } from '@/mocks/handlers/entityEmailChanges'
import { entityProfileChangesHandlers } from '@/mocks/handlers/entityProfileChanges'
import { regionsHandlers } from '@/mocks/handlers/regions'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(
  ...entityEmailChangesHandlers,
  ...entityProfileChangesHandlers,
  ...entitiesHandlers,
  ...regionsHandlers,
)

const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001 }
const NAMAA = { id: 6001, roles: ['entity'], entityId: 6001 }
const SUSPENDED = { id: 6003, roles: ['entity'], entityId: 6003 }
const PENDING_EMAIL = { id: 6005, roles: ['entity'], entityId: 6005 }

async function call(method, path, body) {
  const init = { method, headers: { 'accept-language': 'en' } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  return { status: response.status, body: await response.json() }
}

async function revisionOf(key) {
  const own = await call('GET', '/entities/me')
  return own.body.data.profile_sections.find((section) => section.key === key).revision
}

function governance(revision, overrides = {}) {
  return {
    base_revision: revision,
    governance_grade: '82.50',
    employees_count: 14,
    annual_beneficiaries_count: 3200,
    has_strategic_plan: true,
    ...overrides,
  }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => resetEntitiesMock())
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('entity profile sections mock (Task-82b contract)', () => {
  it('counts 15 fields and reports the incomplete mock at 60 % by section', async () => {
    seedMockSession(NAMAA)
    const { profile_completion: completion } = (await call('GET', '/entities/me')).body.data
    expect(completion).toMatchObject({ percent: 60, counted: 15, completed_count: 9 })
    expect(completion.sections).toEqual([
      {
        key: 'identity',
        state: 'incomplete',
        missing_count: 3,
        missing_fields: ['unified_number', 'license_number', 'founded_on'],
      },
      { key: 'classification', state: 'complete', missing_count: 0, missing_fields: [] },
      {
        key: 'governance',
        state: 'incomplete',
        missing_count: 3,
        missing_fields: ['governance_grade', 'employees_count', 'annual_beneficiaries_count'],
      },
      { key: 'contact', state: 'complete', missing_count: 0, missing_fields: [] },
    ])
  })

  it('saves one section, reports the changed fields and moves to 80 %', async () => {
    seedMockSession(NAMAA)
    const saved = await call(
      'PATCH',
      '/entities/me/profile/governance',
      governance(await revisionOf('governance')),
    )
    expect(saved.status).toBe(200)
    expect(saved.body.meta).toEqual({
      section: 'governance',
      changed_fields: ['governance_grade', 'employees_count', 'annual_beneficiaries_count'],
    })
    expect(saved.body.data.profile_completion.percent).toBe(80)
  })

  it('answers 409 profile.section_stale with the current revision', async () => {
    seedMockSession(NAMAA)
    const stale = await revisionOf('governance')
    await call('PATCH', '/entities/me/profile/governance', governance(stale))
    const conflict = await call(
      'PATCH',
      '/entities/me/profile/governance',
      governance(stale, { employees_count: 20 }),
    )
    expect(conflict.status).toBe(409)
    expect(conflict.body.code).toBe('profile.section_stale')
    expect(conflict.body.meta.revision).toBe(await revisionOf('governance'))
  })

  it('keeps an unanswered strategic plan as null and counts it as missing', async () => {
    seedMockSession(NAMAA)
    const saved = await call(
      'PATCH',
      '/entities/me/profile/governance',
      governance(await revisionOf('governance'), { has_strategic_plan: null }),
    )
    expect(saved.body.data.has_strategic_plan).toBeNull()
    const section = saved.body.data.profile_completion.sections[2]
    expect(section.missing_fields).toEqual(['has_strategic_plan'])
  })

  it('refuses absent keys, a missing unified number and the official e-mail', async () => {
    seedMockSession(NAMAA)
    const absent = await call('PATCH', '/entities/me/profile/governance', {
      base_revision: await revisionOf('governance'),
    })
    expect(absent.status).toBe(422)
    expect(Object.keys(absent.body.errors)).toContain('governance_grade')
    const identity = await call('PATCH', '/entities/me/profile/identity', {
      base_revision: await revisionOf('identity'),
      name: { ar: 'مؤسسة نماء للتنمية', en: null },
      unified_number: null,
      license_number: null,
      founded_on: null,
    })
    expect(Object.keys(identity.body.errors)).toEqual(['unified_number'])
    const contact = await call('PATCH', '/entities/me/profile/contact', {
      base_revision: await revisionOf('contact'),
      authorized_person_name: 'نورة بنت فهد العتيبي',
      phone: null,
      website_url: null,
      marketing_consent: false,
      official_email: 'office@namaa.example.sa',
    })
    expect(Object.keys(contact.body.errors)).toEqual(['official_email'])
    expect((await call('PATCH', '/entities/me/profile/unknown', {})).status).toBe(404)
  })

  it('denies every own-profile write when the account is not active', async () => {
    seedMockSession(SUSPENDED)
    expect((await call('GET', '/entities/me')).body.data.can_edit).toBe(false)
    expect((await call('PATCH', '/entities/me/profile/governance', governance('x'))).status).toBe(
      403,
    )
    expect((await call('DELETE', '/entities/me/logo')).status).toBe(403)
    expect((await call('POST', '/entities/me/email/cancel', {})).status).toBe(403)
  })

  it('cancels a pending e-mail change once and is idempotent after that', async () => {
    seedMockSession(PENDING_EMAIL)
    const first = await call('POST', '/entities/me/email/cancel', {})
    expect(first.body.meta.changed).toBe(true)
    expect(first.body.data.pending_email_change).toBeNull()
    const again = await call('POST', '/entities/me/email/cancel', {})
    expect(again.status).toBe(200)
    expect(again.body.meta.changed).toBe(false)
  })

  it('filters the history by section and masks the phone', async () => {
    seedMockSession(ALBIRR)
    await call('PATCH', '/entities/me/profile/contact', {
      base_revision: await revisionOf('contact'),
      authorized_person_name: 'أحمد بن سالم القحطاني',
      phone: '0501234567',
      website_url: null,
      marketing_consent: true,
    })
    const contact = await call('GET', '/entities/me/profile-changes?section=contact')
    expect(contact.body.data.every((item) => item.section === 'contact')).toBe(true)
    const phone = contact.body.data.find((item) => item.field === 'phone')
    expect(phone.new_value).toBe('+9665*****567')
    expect(phone.changed_by).toBe('entity')
    const governanceOnly = await call('GET', '/entities/me/profile-changes?section=governance')
    expect(governanceOnly.body.data.map((item) => item.section)).not.toContain('contact')
    expect((await call('GET', '/entities/me/profile-changes?section=other')).status).toBe(422)
  })
})
