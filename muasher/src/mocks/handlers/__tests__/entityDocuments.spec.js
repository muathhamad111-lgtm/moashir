import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { entitiesHandlers } from '@/mocks/handlers/entities'
import { entityProfileChangesHandlers } from '@/mocks/handlers/entityProfileChanges'
import { paymentsHandlers } from '@/mocks/handlers/payments'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { decideRequest, pendingRequestOf } from '@/mocks/stores/profileChangeRequests'
import { findEntity } from '@/mocks/stores/entitiesStore'
import { acceptEntityDocument, checkRegistrationDocuments } from '@/mocks/stores/entityDocuments'
import { collector } from '@/mocks/stores/entityValidation'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'
import { parseMultipartRequestsAsText } from '@/test-utils/multipartRequests'
import { approvePendingChange } from './profileChangeSupport'

const server = setupServer(
  ...entityProfileChangesHandlers,
  ...entitiesHandlers,
  ...paymentsHandlers,
)

const SYSTEM_ADMIN = { id: 2, roles: ['system_admin'] }
const FOLLOWER = { id: 9, roles: ['request_follower'] }
const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001 }
const EHSAN = { id: 6002, roles: ['entity'], entityId: 6002 }
const REVIEWER = { id: 2, roles: ['system_admin'], name: { ar: 'منيرة', en: 'Munira' } }

async function call(method, path, body) {
  const init = { method, headers: { 'accept-language': 'en' } }
  if (body instanceof FormData) init.body = body
  else if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  const json = /json/.test(response.headers.get('content-type') ?? '')
  return { status: response.status, body: json ? await response.json() : await response.blob() }
}

function pdfForm(name = 'letter.pdf', type = 'application/pdf') {
  const form = new FormData()
  form.append('file', new File(['%PDF-1.4'], name, { type }), name)
  return form
}

async function contactRevision() {
  const own = await call('GET', '/entities/me')
  return own.body.data.profile_sections.find((section) => section.key === 'contact').revision
}

async function renameAuthorizedPerson(name) {
  return call('PATCH', '/entities/me/profile/contact', {
    base_revision: await contactRevision(),
    authorized_person_name: name,
    phone: null,
    website_url: null,
    marketing_consent: false,
  })
}

beforeAll(() => {
  parseMultipartRequestsAsText()
  server.listen({ onUnhandledRequest: 'error' })
})
beforeEach(() => resetEntitiesMock())
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('entity documents mock', () => {
  it('checks each registration document for presence, PDF type and the 5 MB limit, and keeps its name', async () => {
    const errors = collector()
    checkRegistrationDocuments(errors, {
      licenseCertificate: new File(['png'], 'license.png', { type: 'image/png' }),
      authorizationLetter: new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'letter.pdf', {
        type: 'application/pdf',
      }),
    })
    expect(Object.keys(errors.fields).sort()).toEqual(['authorizationLetter', 'licenseCertificate'])
    const missing = collector()
    checkRegistrationDocuments(missing, {})
    expect(Object.keys(missing.fields).sort()).toEqual([
      'authorizationLetter',
      'licenseCertificate',
    ])
    const stored = await acceptEntityDocument(
      'licenseCertificate',
      new File(['%PDF-1.4'], 'license.pdf', { type: 'application/pdf' }),
    )
    expect(stored).toMatchObject({ name: 'license.pdf', sizeBytes: 8 })
  })

  it('shows the documents to entity managers and serves them as a valid PDF', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const record = await call('GET', '/entities/5001')
    expect(record.body.data.documents.license_certificate).toMatchObject({
      name: 'license-certificate-albirr.pdf',
      mime_type: 'application/pdf',
    })
    const link = await call('POST', '/entities/5001/documents/license_certificate/download')
    expect(link.status).toBe(200)
    const file = await call(
      'GET',
      new URL(link.body.data.url).pathname.replace('/api/v1', '') +
        new URL(link.body.data.url).search,
    )
    const text = await file.body.text()
    expect(text.startsWith('%PDF-1.4')).toBe(true)
    expect(text).toContain('startxref')
    const missing = await call('POST', '/entities/6002/documents/license_certificate/download')
    expect([missing.status, missing.body.code]).toEqual([404, 'entity.document_missing'])
  })

  it('keeps the documents from staff who do not manage entities', async () => {
    seedMockSession(FOLLOWER)
    const record = await call('GET', '/entities/5001')
    expect(record.body.data).not.toHaveProperty('documents')
    const link = await call('POST', '/entities/5001/documents/license_certificate/download')
    expect(link.status).toBe(403)
  })

  it('lets an entity without documents upload them through a profile change', async () => {
    seedMockSession(EHSAN)
    const own = await call('GET', '/entities/me')
    expect(own.body.data.documents.license_certificate).toBeNull()
    const refused = await call(
      'POST',
      '/entities/me/documents/license_certificate',
      pdfForm('a.png', 'image/png'),
    )
    expect(Object.keys(refused.body.errors)).toEqual(['file'])
    const vat = await call('POST', '/entities/me/documents/vat_certificate', pdfForm())
    expect(vat.status).toBe(404)
    const sent = await call(
      'POST',
      '/entities/me/documents/license_certificate',
      pdfForm('license.pdf'),
    )
    expect(sent.status).toBe(202)
    expect(sent.body.data.pending_profile_change.items).toEqual(['license_certificate'])
    approvePendingChange(6002)
    const approved = await call('GET', '/entities/me')
    expect(approved.body.data.documents.license_certificate).toMatchObject({
      mime_type: 'application/pdf',
    })
  })

  it('asks for a new letter with a new authorized person and decides the two together', async () => {
    seedMockSession(ALBIRR)
    const refused = await renameAuthorizedPerson('ريم بنت خالد الحربي')
    expect([refused.status, refused.body.code]).toEqual([
      422,
      'profile.change_authorization_letter_required',
    ])
    expect(Object.keys(refused.body.errors)).toEqual(['authorization_letter'])
    await call('POST', '/entities/me/documents/authorization_letter', pdfForm('letter-reem.pdf'))
    expect((await renameAuthorizedPerson('ريم بنت خالد الحربي')).status).toBe(202)
    const request = pendingRequestOf(5001)
    const decide = (approvedItems) =>
      decideRequest(
        request,
        findEntity(5001),
        { revision: request.revision, approvedItems, reason: 'Letter is not stamped.' },
        REVIEWER,
      )
    expect(() => decide(['authorized_person_name'])).toThrow('profile.change_coupled_items')
    decide(['authorized_person_name', 'authorization_letter'])
    expect(findEntity(5001).authorizationLetter.id).not.toBe('8c2d41a7-3b6e-4f10-a5c2-0000000070a2')
    expect(findEntity(5001).owner.name).toBe('ريم بنت خالد الحربي')
  })
})

describe('staff edit with a new authorized person (operator 2026-11-25)', () => {
  const REASON = 'Board letter names a new authorized person.'

  async function staffEditBody(overrides = {}) {
    const { data } = (await call('GET', '/entities/5001')).body
    return {
      name: data.name,
      authorized_person_name: data.authorized_person_name,
      phone: data.phone,
      entity_type: data.entity_type,
      development_sector: data.development_sector,
      org_size: data.org_size,
      founding_year: data.founding_year,
      region_id: data.region.id,
      city_id: data.city.id,
      has_governance_grade: data.has_governance_grade,
      governance_grade: data.governance_grade,
      employees_count: data.employees_count,
      annual_beneficiaries_count: data.annual_beneficiaries_count,
      has_strategic_plan: data.has_strategic_plan,
      reason: REASON,
      ...overrides,
    }
  }

  function multipartOf(body, letter) {
    const form = new FormData()
    Object.entries({ ...body, _method: 'PUT' }).forEach(([key, value]) => {
      if (value && typeof value === 'object') {
        Object.entries(value).forEach(([child, nested]) => form.append(`${key}[${child}]`, nested))
      } else if (typeof value === 'boolean') form.append(key, value ? '1' : '0')
      else form.append(key, value ?? '')
    })
    form.append('authorization_letter', letter, letter.name)
    return form
  }

  it('refuses a new authorized person without a new letter', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const before = findEntity(5001).owner.name
    const refused = await call(
      'PUT',
      '/entities/5001',
      await staffEditBody({ authorized_person_name: 'ريم بنت خالد الحربي' }),
    )
    expect([refused.status, refused.body.code]).toEqual([
      422,
      'profile.change_authorization_letter_required',
    ])
    expect(Object.keys(refused.body.errors)).toEqual(['authorization_letter'])
    expect(findEntity(5001).owner.name).toBe(before)
  })

  it('saves the new person with the letter, which replaces the current one', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const previous = findEntity(5001).authorizationLetter.id
    const letter = new File(['%PDF-1.4'], 'letter-reem.pdf', { type: 'application/pdf' })
    const body = await staffEditBody({ authorized_person_name: 'ريم بنت خالد الحربي' })
    const saved = await call('POST', '/entities/5001', multipartOf(body, letter))
    expect(saved.status).toBe(200)
    expect(saved.body.data.authorized_person_name).toBe('ريم بنت خالد الحربي')
    expect(saved.body.data.documents.authorization_letter).toMatchObject({
      mime_type: 'application/pdf',
    })
    expect(findEntity(5001).authorizationLetter.id).not.toBe(previous)
  })

  it('refuses a letter that is not a PDF and a POST that does not name the PUT method', async () => {
    seedMockSession(SYSTEM_ADMIN)
    const image = new File(['png'], 'letter.png', { type: 'image/png' })
    const body = await staffEditBody({ authorized_person_name: 'ريم بنت خالد الحربي' })
    const refused = await call('POST', '/entities/5001', multipartOf(body, image))
    expect([refused.status, Object.keys(refused.body.errors)]).toEqual([
      422,
      ['authorization_letter'],
    ])
    expect((await call('POST', '/entities/5001', pdfForm())).status).toBe(404)
  })
})
