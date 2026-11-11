import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { contactMessagesHandlers } from '@/mocks/handlers/contactMessages'
import { certificatesHandlers } from '@/mocks/handlers/certificates'
import { paymentsHandlers } from '@/mocks/handlers/payments'
import { entitiesHandlers } from '@/mocks/handlers/entities'
import { legalHandlers } from '@/mocks/handlers/legal'
import { assessmentsHandlers } from '@/mocks/handlers/assessments'
import { resetSenderThreads } from '@/mocks/stores/senderThreads'
import { resetContactMessages } from '@/mocks/stores/contactMessages'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { removeEntityLogo, storeEntityLogo } from '@/mocks/stores/entityLogo'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(
  ...legalHandlers,
  ...contactMessagesHandlers,
  ...certificatesHandlers,
  ...paymentsHandlers,
  ...entitiesHandlers,
  ...assessmentsHandlers,
)

const NAMAA = { id: 7601, roles: ['entity'], entityId: 6001 }
const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001 }
const THREAD = '7c2f4e1a-52b0-4c8e-9a31-0000000000a1'
const CLOSED_THREAD = '7c2f4e1a-52b0-4c8e-9a31-0000000000a2'

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
  resetSenderThreads()
  resetContactMessages()
  resetEntitiesMock()
  resetFinanceState()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('sender threads', () => {
  it('reads a thread with its entries, marks it seen and answers it', async () => {
    seedMockSession(NAMAA)
    const thread = await call('GET', `/me/contact-messages/${THREAD}`)
    expect(thread.body.data.entries.map((entry) => entry.author)).toEqual(['sender', 'team'])
    const seen = await call('PATCH', `/me/contact-messages/${THREAD}/seen`)
    expect(seen.body.data.has_unread_reply).toBe(false)
    const reply = await call('POST', `/me/contact-messages/${THREAD}/replies`, {
      body: 'شكراً لكم',
    })
    expect(reply.status).toBe(201)
    expect(reply.body.data.status).toBe('in_progress')
    expect((await call('GET', '/me/contact-messages')).body.meta.unread_replies).toBe(0)
  })

  it('refuses a reply to a closed thread, another sender and a bad status filter', async () => {
    seedMockSession(NAMAA)
    const closed = await call('POST', `/me/contact-messages/${CLOSED_THREAD}/replies`, {
      body: 'رد',
    })
    expect(closed.status).toBe(409)
    expect(closed.body.code).toBe('contact.message_closed')
    expect((await call('GET', '/me/contact-messages?filter[status]=new,archived')).status).toBe(422)
    seedMockSession({ id: 31, roles: ['finance_officer'] })
    expect((await call('GET', `/me/contact-messages/${THREAD}`)).status).toBe(404)
  })

  it('returns the id of a message sent while signed in and lists it', async () => {
    seedMockSession(NAMAA)
    const sent = await call('POST', '/contact-messages', {
      enquiry_type: 'privacy_request',
      message: 'نرجو تزويدنا بنسخة من بيانات حسابنا.',
      privacy_consent: true,
      website: '',
    })
    expect(sent.status).toBe(201)
    expect(sent.body.data.id).toMatch(/^[0-9a-f-]{36}$/)
    const list = await call('GET', '/me/contact-messages?filter[status]=new')
    expect(list.body.data[0].enquiry_type).toBe('privacy_request')
  })
})

describe('certificates, verification and the feedback report', () => {
  it('counts own certificates, refuses a superseded download and verifies a code', async () => {
    seedMockSession(NAMAA)
    const list = await call('GET', '/certificates?filter[status]=revoked,superseded')
    expect(list.body.data.map((item) => item.status)).toEqual(['superseded'])
    expect(list.body.meta.status_counts).toEqual({
      issued: 1,
      suspended: 0,
      revoked: 0,
      superseded: 1,
    })
    const superseded = list.body.data[0]
    const refused = await call('POST', `/certificates/${superseded.id}/download`)
    expect(refused.body.code).toBe('certificate.superseded')
    const verified = await call('GET', '/verify/9f3k-mq2t-7xhd-m4pa-z81c-qw5n-rt6b')
    expect(verified.body.data).toMatchObject({ result: 'valid', certificate: { level: 'silver' } })
    const unknown = await call('GET', '/verify/AAAA-BBBB')
    expect(unknown.body.data).toEqual({ result: 'not_found', certificate: null })
  })

  it('serves the feedback report of an own completed request and refuses others', async () => {
    seedMockSession(NAMAA)
    const report = await call('GET', '/assessments/9104/feedback-report')
    expect(report.body.data.available).toBe(true)
    expect(report.body.data.criteria[0].subs[0]).toHaveProperty('improvement_points')
    const exported = await call('POST', '/assessments/9104/feedback-report/export', {
      locale: 'en',
    })
    expect(exported.body.data.url).toContain('/files/')
    expect((await call('GET', '/assessments/9101/feedback-report')).body.data.available).toBe(false)
    seedMockSession(ALBIRR)
    expect((await call('GET', '/assessments/9104/feedback-report')).status).toBe(404)
  })
})

describe('payments tabs and assessment counts', () => {
  it('filters own payments by a status list and counts every status', async () => {
    seedMockSession(ALBIRR)
    const paid = await call('GET', '/payments/mine?filter[status]=paid')
    expect(paid.body.data.every((payment) => payment.status === 'paid')).toBe(true)
    const accredited = paid.body.data.find((payment) => payment.reference === 'PAY-2026-000001')
    expect(accredited.request).toMatchObject({ number: 'ACC-2026-000001', path: 'accredited' })
    expect(paid.body.data.find((payment) => payment.purpose === 'bag').request).toBeNull()
    const all = await call('GET', '/payments/mine?per_page=1')
    const total = Object.values(all.body.meta.status_counts).reduce((sum, count) => sum + count, 0)
    expect(total).toBe(all.body.meta.pagination.total)
    expect((await call('GET', '/payments/mine?filter[status]=paid,archived')).status).toBe(422)
  })

  it('returns open and result counts with the list filters', async () => {
    seedMockSession(NAMAA)
    const open = await call('GET', '/assessments?filter[is_open]=true')
    expect(open.body.data.every((item) => item.is_open)).toBe(true)
    expect(open.body.meta.counts.open).toBe(open.body.data.length)
    const results = await call('GET', '/assessments?filter[has_result]=true')
    expect(results.body.meta.counts.with_result).toBe(results.body.data.length)
    expect((await call('GET', '/assessments?filter[is_open]=maybe')).status).toBe(422)
  })
})

describe('entity registry details and logo', () => {
  it('stores the registry details by section and refuses http websites', async () => {
    seedMockSession(NAMAA)
    const me = (await call('GET', '/entities/me')).body.data
    const revision = (key) => me.profile_sections.find((section) => section.key === key).revision
    const saved = await call('PATCH', '/entities/me/profile/identity', {
      base_revision: revision('identity'),
      name: me.name,
      unified_number: '7000000777',
      license_number: '000123',
      founded_on: '2012-03-01',
    })
    expect(saved.body.data).toMatchObject({ license_number: '000123', founding_year: 2012 })
    expect(saved.body.data.profile_completion.percent).toBe(80)
    const refused = await call('PATCH', '/entities/me/profile/contact', {
      base_revision: revision('contact'),
      authorized_person_name: me.authorized_person_name,
      phone: null,
      website_url: 'http://namaa.example.sa',
      marketing_consent: false,
    })
    expect(refused.status).toBe(422)
    expect(refused.body.errors).toHaveProperty('website_url')
  })

  it('keeps a clean PNG logo behind a signed link and refuses other types', async () => {
    const entity = { id: 6001, logo: null }
    await storeEntityLogo(
      NAMAA,
      entity,
      new File([new Uint8Array([0x89, 0x50])], 'logo.png', { type: 'image/png' }),
    )
    expect(entity.logo.url).toContain('/files/')
    await expect(
      storeEntityLogo(NAMAA, entity, new File(['<svg/>'], 'logo.svg', { type: 'image/svg+xml' })),
    ).rejects.toMatchObject({ status: 422 })
    expect(removeEntityLogo(entity).logo).toBeNull()
  })
})

describe('legal texts', () => {
  it('serves the current versions and the accessibility page as typed blocks', async () => {
    const privacy = await call('GET', '/content/legal/privacy')
    expect(privacy.body.data).toMatchObject({ kind: 'privacy', version: '2026-11-draft' })
    expect(privacy.body.data.body.ar[0].type).toBe('paragraph')
    expect((await call('GET', '/content/legal/cookies')).status).toBe(404)
    const page = await call('GET', '/content/pages/accessibility')
    expect(page.body.data.sections[1].blocks.en[0].type).toBe('list')
  })
})
