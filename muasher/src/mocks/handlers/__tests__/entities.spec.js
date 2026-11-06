import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { entitiesHandlers } from '@/mocks/handlers/entities'
import { entityLifecycleHandlers } from '@/mocks/handlers/entityLifecycle'
import { entityEmailChangesHandlers } from '@/mocks/handlers/entityEmailChanges'
import { privacyRequestsHandlers } from '@/mocks/handlers/privacyRequests'
import { regionsHandlers } from '@/mocks/handlers/regions'
import { authHandlers } from '@/mocks/handlers/auth'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { setEmailChangePolicy } from '@/mocks/stores/entityEmailChanges'
import { DEV_OTP_CODE } from '@/mocks/fixtures/auth'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(
  ...entityEmailChangesHandlers,
  ...entityLifecycleHandlers,
  ...entitiesHandlers,
  ...privacyRequestsHandlers,
  ...regionsHandlers,
  ...authHandlers,
)

const SYSTEM_ADMIN = { id: 2, roles: ['system_admin'] }
const FOLLOWER = { id: 9, roles: ['request_follower'] }
const FINANCE = { id: 31, roles: ['finance_officer'] }
const ASSESSOR = { id: 10, roles: ['assessor'] }
const SUPER_ADMIN = { id: 1, roles: [], superAdmin: true }
const ENTITY = { id: 5001, roles: ['entity'], entityId: 5001 }

async function call(method, path, body) {
  const init = { method, headers: { 'accept-language': 'en' } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const text = await response.text()
  const json =
    /json/.test(response.headers.get('content-type') ?? '') && text ? JSON.parse(text) : text
  return { status: response.status, body: json, headers: response.headers }
}

const as = (descriptor) => seedMockSession(descriptor)

const REASON = 'Formal request received from the entity board.'

function profileBody(overrides = {}) {
  return {
    name: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' },
    authorized_person_name: 'أحمد بن سالم القحطاني',
    phone: null,
    entity_type: 'civil_association',
    development_sector: 'social',
    org_size: 'large',
    founding_year: 2009,
    region_id: 1,
    city_id: 1,
    governance_grade: '88.25',
    employees_count: 185,
    annual_beneficiaries_count: 43000,
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

describe('entities mock — public master data', () => {
  it('serves the 13 regions with their active cities and the current terms in snake_case', async () => {
    const regions = await call('GET', '/regions')
    expect(regions.status).toBe(200)
    expect(regions.body.data).toHaveLength(13)
    expect(regions.body.data[0]).toMatchObject({
      id: 1,
      code: 'riyadh',
      name: { ar: 'منطقة الرياض' },
    })
    expect(regions.body.data[0].cities[0]).toEqual({
      id: 1,
      code: 'riyadh',
      name: { ar: 'الرياض', en: 'Riyadh' },
    })
    const terms = await call('GET', '/terms/current')
    expect(terms.body.data.terms).toMatchObject({
      id: 3,
      kind: 'terms',
      effective_from: '2026-10-01',
    })
    expect(terms.body.data.privacy.id).toBe(4)
  })
})

describe('entities mock — registration', () => {
  function registration(overrides = {}) {
    return {
      ...profileBody({
        governance_grade: null,
        employees_count: null,
        annual_beneficiaries_count: null,
      }),
      name: { ar: 'جمعية الوفاء الخيرية', en: null },
      official_email: 'info@alwafa.example.sa',
      unified_number: '7000000901',
      password: 'Strong-Passphrase-2026',
      password_confirmation: 'Strong-Passphrase-2026',
      terms_version_id: 3,
      privacy_version_id: 4,
      terms_accepted: true,
      marketing_consent: false,
      ...overrides,
    }
  }

  it('creates a pending entity, answers otp_required and activates it on the first verification', async () => {
    const created = await call('POST', '/auth/register', registration())
    expect(created.status).toBe(201)
    expect(created.body.data.step).toBe('otp_required')
    const verified = await call('POST', '/auth/otp/verify', {
      challenge_id: created.body.data.otp.challenge_id,
      code: DEV_OTP_CODE,
    })
    expect(verified.body.data.step).toBe('authenticated')
    expect(verified.body.data.user.entity).toMatchObject({
      status: 'active',
      profile_completed: false,
    })
  })

  it('answers a used e-mail with the identical body and a decoy challenge', async () => {
    const duplicate = await call(
      'POST',
      '/auth/register',
      registration({ official_email: 'info@albirr-riyadh.example.sa' }),
    )
    expect(duplicate.status).toBe(201)
    expect(Object.keys(duplicate.body.data).sort()).toEqual([
      'message',
      'otp',
      'requires_otp',
      'step',
    ])
    const verified = await call('POST', '/auth/otp/verify', {
      challenge_id: duplicate.body.data.otp.challenge_id,
      code: DEV_OTP_CODE,
    })
    expect(verified.status).toBe(422)
  })

  it('rejects a city outside the region, a grade with three decimals and missing terms acceptance', async () => {
    const invalid = await call(
      'POST',
      '/auth/register',
      registration({ city_id: 12, governance_grade: '90.125', terms_accepted: false }),
    )
    expect(invalid.status).toBe(422)
    expect(Object.keys(invalid.body.errors).sort()).toEqual([
      'city_id',
      'governance_grade',
      'terms_accepted',
    ])
  })

  it('requires a unique unified national number of 10 digits starting with 7', async () => {
    const missing = await call('POST', '/auth/register', registration({ unified_number: '' }))
    expect(missing.status).toBe(422)
    expect(Object.keys(missing.body.errors)).toEqual(['unified_number'])
    const malformed = await call(
      'POST',
      '/auth/register',
      registration({ unified_number: '6000000901' }),
    )
    expect(malformed.body.errors.unified_number[0]).toContain('start with 7')
    const taken = await call(
      'POST',
      '/auth/register',
      registration({ unified_number: '7100005001' }),
    )
    expect(taken.status).toBe(422)
    expect(taken.body.errors.unified_number[0]).toContain('already registered for another entity')
  })
})

describe('entities mock — own profile', () => {
  it('returns the own entity and replaces the profile with the marketing consent', async () => {
    as(ENTITY)
    const own = await call('GET', '/entities/me')
    expect(own.body.data).toMatchObject({
      reference: 'ENT-005001',
      governance_grade: '86.50',
      marketing_consent: true,
    })
    const saved = await call('PUT', '/entities/me', profileBody({ marketing_consent: false }))
    expect(saved.status).toBe(200)
    expect(saved.body.data).toMatchObject({ governance_grade: '88.25', marketing_consent: false })
    expect((await call('PUT', '/entities/me', profileBody())).status).toBe(422)
  })

  it('keeps an absent unified number, replaces a valid one and never clears it', async () => {
    as(ENTITY)
    const kept = await call('PUT', '/entities/me', profileBody({ marketing_consent: true }))
    expect(kept.body.data.unified_number).toBe('7100005001')
    const replaced = await call(
      'PUT',
      '/entities/me',
      profileBody({ marketing_consent: true, unified_number: '7000000902' }),
    )
    expect(replaced.body.data.unified_number).toBe('7000000902')
    const cleared = await call(
      'PUT',
      '/entities/me',
      profileBody({ marketing_consent: true, unified_number: null }),
    )
    expect(cleared.status).toBe(422)
    expect(Object.keys(cleared.body.errors)).toEqual(['unified_number'])
  })

  it('denies staff and guests', async () => {
    expect((await call('GET', '/entities/me')).status).toBe(401)
    as(SYSTEM_ADMIN)
    expect((await call('GET', '/entities/me')).status).toBe(403)
  })
})

describe('entities mock — e-mail change', () => {
  async function requestAndConfirm() {
    const requested = await call('POST', '/entities/me/email/change', {
      new_email: 'office@albirr-riyadh.example.sa',
    })
    expect(requested.status).toBe(200)
    return call('POST', '/entities/me/email/confirm', {
      challenge_id: requested.body.data.otp.challenge_id,
      code: DEV_OTP_CODE,
    })
  }

  it('waits for approval, blocks a second request and lets the approver apply it', async () => {
    as(ENTITY)
    const confirmed = await requestAndConfirm()
    expect(confirmed.body.data).toEqual({
      status: 'awaiting_approval',
      policy: 'requires_approval',
      new_email: 'o***@albirr-riyadh.example.sa',
    })
    expect((await call('GET', '/entities/me')).body.data.pending_email_change.status).toBe(
      'awaiting_approval',
    )
    expect(
      (await call('POST', '/entities/me/email/change', { new_email: 'another@albirr.example.sa' }))
        .status,
    ).toBe(409)
    as(SYSTEM_ADMIN)
    const queue = await call('GET', '/admin/entity-email-changes')
    const change = queue.body.data.find((item) => item.entity.id === 5001)
    const approved = await call('POST', `/admin/entity-email-changes/${change.id}/approve`)
    expect(approved.body.data.status).toBe('applied')
  })

  it('applies at once under the immediate_notify policy', async () => {
    setEmailChangePolicy('immediate_notify')
    as(ENTITY)
    expect((await requestAndConfirm()).body.data.status).toBe('applied')
  })

  it('refuses the same address and requires a reason to reject', async () => {
    as(ENTITY)
    const same = await call('POST', '/entities/me/email/change', {
      new_email: 'info@albirr-riyadh.example.sa',
    })
    expect(same.status).toBe(422)
    as(FINANCE)
    expect((await call('GET', '/admin/entity-email-changes')).status).toBe(403)
    as(SYSTEM_ADMIN)
    expect(
      (await call('POST', '/admin/entity-email-changes/301/reject', { reason: 'short' })).status,
    ).toBe(422)
  })
})

describe('entities mock — staff list and record scope', () => {
  it('lists, filters and summarises entities for the System Administrator', async () => {
    as(SYSTEM_ADMIN)
    const list = await call('GET', '/entities?per_page=5&filter[status]=pending_verification')
    expect(list.body.data.map((item) => item.id).sort()).toEqual([7001, 7004])
    expect(list.body.meta.stats).toMatchObject({
      total: 10,
      active: 7,
      pending_verification: 2,
      deactivated: 1,
    })
    expect(list.body.data[0]).toHaveProperty('official_email')
    const searched = await call('GET', `/entities?search=${encodeURIComponent('احسان')}`)
    expect(searched.body.data.map((item) => item.id)).toEqual([6002])
  })

  it('serves the Deleted entities only to holders of entities.delete', async () => {
    as(SYSTEM_ADMIN)
    const deleted = await call('GET', '/entities?filter[trashed]=only')
    expect(deleted.body.data.map((item) => item.id).sort()).toEqual([7002, 7003])
    expect(deleted.body.data[0]).toMatchObject({ is_deleted: true, is_frozen: true })
    as(FOLLOWER)
    expect((await call('GET', '/entities?filter[trashed]=only')).status).toBe(403)
    expect((await call('GET', '/entities/7002')).status).toBe(404)
  })

  it('gives the Finance Officer contact data only and hides every entity from the assessor', async () => {
    as(FINANCE)
    const contact = await call('GET', '/entities/5001')
    expect(Object.keys(contact.body.data).sort()).toEqual(
      [
        'authorized_person_name',
        'id',
        'level',
        'level_certificate_number',
        'name',
        'official_email',
        'phone',
        'phone_verified',
        'reference',
        'status',
      ].sort(),
    )
    as(ASSESSOR)
    expect((await call('GET', '/entities')).body.data).toEqual([])
    expect((await call('GET', '/entities/5001')).status).toBe(404)
  })

  it('exports a CSV only for full-scope holders of entities.export', async () => {
    as(SYSTEM_ADMIN)
    const csv = await call('POST', '/entities/export?filter[status]=active')
    expect(csv.status).toBe(200)
    expect(csv.headers.get('content-disposition')).toMatch(/entities-\d{8}-\d{6}\.csv/)
    expect(csv.headers.get('x-export-row-count')).toBe('7')
    as(FOLLOWER)
    expect((await call('POST', '/entities/export')).status).toBe(403)
  })
})

describe('entities mock — lifecycle actions', () => {
  it('deactivates and reactivates with a reason and refuses illegal transitions', async () => {
    as(SYSTEM_ADMIN)
    expect((await call('POST', '/entities/5001/deactivate', { reason: 'short' })).status).toBe(422)
    const deactivated = await call('POST', '/entities/5001/deactivate', { reason: REASON })
    expect(deactivated.body.data).toMatchObject({
      status: 'deactivated',
      deactivation_reason: REASON,
    })
    expect((await call('POST', '/entities/5001/deactivate', { reason: REASON })).status).toBe(409)
    expect(
      (await call('POST', '/entities/5001/reactivate', { reason: REASON })).body.data.status,
    ).toBe('active')
    as(FOLLOWER)
    expect((await call('POST', '/entities/5001/deactivate', { reason: REASON })).status).toBe(403)
  })

  it('resends the verification of a pending entity with a cooldown', async () => {
    as(SYSTEM_ADMIN)
    const sent = await call('POST', '/entities/7001/verification/resend')
    expect(sent.status).toBe(202)
    expect(sent.body.data.verification_reminded_at).toBeTruthy()
    const again = await call('POST', '/entities/7001/verification/resend')
    expect(again.status).toBe(429)
    expect(again.body.code).toBe('entity.verification_cooldown')
    expect((await call('POST', '/entities/5001/verification/resend')).status).toBe(409)
  })

  it('lists the open requests, refuses a stale acknowledgement and freezes the record on deletion', async () => {
    as(SYSTEM_ADMIN)
    const impact = await call('GET', '/entities/6002/deletion-impact')
    const numbers = impact.body.data.open_requests.map((request) => request.number)
    expect(numbers).toEqual(['ADV-2026-000031', 'BAG-2026-000007'])
    expect(impact.body.data.kept_on_permanent_deletion).toContain('payments_receipts_refunds')
    const unconfirmed = await call('DELETE', '/entities/6002', { reason: REASON })
    expect(unconfirmed.status).toBe(409)
    const stale = await call('DELETE', '/entities/6002', {
      reason: REASON,
      confirm_open_requests: true,
      acknowledged_request_numbers: numbers,
    })
    expect(stale.body.code).toBe('entity.open_requests_confirmation_required')
    const current = stale.body.meta.open_requests.map((request) => request.number)
    expect(current).toContain('ACC-2026-000052')
    const deleted = await call('DELETE', '/entities/6002', {
      reason: REASON,
      confirm_open_requests: true,
      acknowledged_request_numbers: current,
    })
    expect(deleted.status).toBe(204)
    expect((await call('POST', '/entities/6002/deactivate', { reason: REASON })).body.code).toBe(
      'entity.deleted',
    )
    expect(
      (await call('POST', '/entities/6002/restore', { reason: REASON })).body.data.is_deleted,
    ).toBe(false)
  })

  it('keeps permanent deletion to the Super Admin with disclaimer, typed reference and a long reason', async () => {
    const instruction = {
      reason: 'Formal erasure request approved by the data protection officer.',
      confirmation: 'ent-007003',
      disclaimer_acknowledged: true,
    }
    as(SYSTEM_ADMIN)
    expect((await call('DELETE', '/entities/7003/permanent', instruction)).status).toBe(403)
    as(SUPER_ADMIN)
    expect(
      (
        await call('DELETE', '/entities/5001/permanent', {
          ...instruction,
          confirmation: 'ENT-005001',
        })
      ).status,
    ).toBe(409)
    expect(
      (
        await call('DELETE', '/entities/7003/permanent', {
          ...instruction,
          confirmation: 'ENT-000001',
        })
      ).body.code,
    ).toBe('confirmation.mismatch')
    expect(
      (
        await call('DELETE', '/entities/7003/permanent', {
          ...instruction,
          disclaimer_acknowledged: false,
        })
      ).body.code,
    ).toBe('disclaimer.required')
    expect(
      (
        await call('DELETE', '/entities/7003/permanent', {
          ...instruction,
          reason: 'Too short reason',
        })
      ).status,
    ).toBe(422)
    const erased = await call('DELETE', '/entities/7003/permanent', instruction)
    expect(erased.body.data).toMatchObject({ reference: 'ENT-007003', pseudonym: 'ERASED-007003' })
    expect(erased.body.data.kept).toContain('audit_log')
    expect((await call('GET', '/entities/7003')).status).toBe(404)
  })
})

describe('entities mock — privacy requests and cities', () => {
  it('creates, fulfils and refuses a second decision on a data-subject request', async () => {
    as(SYSTEM_ADMIN)
    const created = await call('POST', '/privacy/requests', {
      entity_id: 6005,
      kind: 'deletion',
      details: 'Formal deletion request from the entity.',
    })
    expect(created.status).toBe(201)
    const fulfilled = await call('POST', `/privacy/requests/${created.body.data.id}/fulfil`, {})
    expect(fulfilled.body.data.status).toBe('fulfilled')
    expect(
      (await call('POST', `/privacy/requests/${created.body.data.id}/reject`, { reason: REASON }))
        .status,
    ).toBe(409)
    const exported = await call('POST', '/entities/6005/export', { reason: REASON })
    expect(exported.body.data.entity.reference).toBe('ENT-006005')
    as(FOLLOWER)
    expect((await call('GET', '/privacy/requests')).status).toBe(403)
  })

  it('creates, renames and deactivates a city under entities.manage', async () => {
    as(SYSTEM_ADMIN)
    const created = await call('POST', '/regions/7/cities', { name: { ar: 'البدع', en: 'Al-Bad' } })
    expect(created.body.data).toMatchObject({ region_id: 7, code: 'al_bad', is_active: true })
    const duplicate = await call('POST', '/regions/7/cities', {
      name: { ar: 'ضباء الجديدة', en: 'Duba' },
    })
    expect(duplicate.body.data.code).toBe('duba_2')
    const renamed = await call('PUT', `/cities/${created.body.data.id}`, {
      name: { ar: 'البدع', en: 'Al Bad' },
    })
    expect(renamed.body.data.name.en).toBe('Al Bad')
    const toggled = await call('PATCH', `/cities/${created.body.data.id}/active`, { active: false })
    expect(toggled.body.data.is_active).toBe(false)
    const listed = await call('GET', '/cities?filter[region_id]=7&filter[active]=false')
    expect(listed.body.data.map((city) => city.code)).toEqual(['al_bad'])
    as(FINANCE)
    expect((await call('GET', '/cities')).status).toBe(403)
  })
})
