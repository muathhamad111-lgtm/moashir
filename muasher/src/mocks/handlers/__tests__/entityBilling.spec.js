import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { entitiesHandlers } from '@/mocks/handlers/entities'
import { bankTransfersHandlers } from '@/mocks/handlers/bankTransfers'
import { resetEntitiesMock } from '@/mocks/stores/entityMockReset'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { findEntity } from '@/mocks/stores/entitiesStore'
import { storeVatCertificate } from '@/mocks/stores/entityBilling'
import { MOCK_API_BASE, clearMockSession, seedMockSession, wireFetch } from '@/test-utils/mockApi'

const server = setupServer(...entitiesHandlers, ...bankTransfersHandlers)

const NAMAA = { id: 6001, roles: ['entity'], entityId: 6001 }
const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001 }
const ATAA = { id: 6004, roles: ['entity'], entityId: 6004 }
const FINANCE = { id: 22, roles: ['finance_officer'] }

async function call(method, path, body) {
  const init = { method, headers: { 'accept-language': 'en' } }
  if (body !== undefined) {
    init.headers['content-type'] = 'application/json'
    init.body = JSON.stringify(body)
  }
  const response = await wireFetch(`${MOCK_API_BASE}${path}`, init)
  return { status: response.status, body: await response.json() }
}

async function billingRevision() {
  const own = await call('GET', '/entities/me')
  return own.body.data.profile_sections.find((section) => section.key === 'billing').revision
}

async function billing(overrides = {}) {
  return {
    base_revision: await billingRevision(),
    address_building_number: '4410',
    address_street: 'شارع الأمير نايف',
    address_district: 'حي العزيزية',
    address_city: 'المدينة المنورة',
    address_postal_code: '42311',
    address_additional_number: '7720',
    address_short_code: 'mdaz4410',
    vat_registered: true,
    vat_number: '300060040000003',
    ...overrides,
  }
}

function pdf(name = 'vat-certificate.pdf', type = 'application/pdf') {
  return new File(['%PDF-1.4\n%%EOF\n'], name, { type })
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetEntitiesMock()
  resetFinanceState()
})
afterEach(() => {
  server.resetHandlers()
  clearMockSession()
})
afterAll(() => server.close())

describe('entity billing mock (row 49)', () => {
  it('lists the billing section and keeps Namaa complete with VAT data', async () => {
    seedMockSession(NAMAA)
    const { data } = (await call('GET', '/entities/me')).body
    expect(data.profile_sections.map((section) => section.key)).toContain('billing')
    expect(data).toMatchObject({
      vat_registered: true,
      vat_number: '300060010000003',
      billing_complete: true,
      profile_completion: { percent: 100, counted: 24 },
    })
    expect(data.vat_certificate.name).toMatch(/\.pdf$/)
  })

  it('saves the billing section and asks for the certificate after a yes', async () => {
    seedMockSession(ATAA)
    const before = (await call('GET', '/entities/me')).body.data
    expect(before.billing_complete).toBe(false)
    const saved = await call('PATCH', '/entities/me/profile/billing', await billing())
    expect(saved.status).toBe(200)
    expect(saved.body.data).toMatchObject({
      address_short_code: 'MDAZ4410',
      vat_registered: true,
      billing_complete: false,
    })
    const section = saved.body.data.profile_completion.sections.find(
      (item) => item.key === 'billing',
    )
    expect(section.missing_fields).toEqual(['vat_certificate'])
  })

  it('refuses malformed values and a VAT number after a no', async () => {
    seedMockSession(ATAA)
    const invalid = await call(
      'PATCH',
      '/entities/me/profile/billing',
      await billing({
        address_building_number: '44',
        address_postal_code: 'abcde',
        vat_number: '1',
      }),
    )
    expect(invalid.status).toBe(422)
    expect(Object.keys(invalid.body.errors)).toEqual(
      expect.arrayContaining(['address_building_number', 'address_postal_code', 'vat_number']),
    )
    const declined = await call(
      'PATCH',
      '/entities/me/profile/billing',
      await billing({ vat_registered: false }),
    )
    expect(Object.keys(declined.body.errors)).toEqual(['vat_number'])
  })

  it('uploads, downloads and removes the certificate, and refuses it before a yes', async () => {
    seedMockSession(ALBIRR)
    const entity = findEntity(5001)
    await expect(storeVatCertificate(entity, pdf())).rejects.toMatchObject({
      status: 409,
      code: 'entity.vat_not_registered',
    })
    await call(
      'PATCH',
      '/entities/me/profile/billing',
      await billing({ vat_number: '300050010000003' }),
    )
    await expect(storeVatCertificate(entity, pdf('scan.png', 'image/png'))).rejects.toMatchObject({
      status: 422,
    })
    await storeVatCertificate(entity, pdf())
    const own = (await call('GET', '/entities/me')).body.data
    expect(own.billing_complete).toBe(true)
    expect(own.vat_certificate.name).toBe('vat-certificate.pdf')
    const link = await call('POST', '/entities/me/vat-certificate/download')
    expect(link.body.data.url).toContain('/files/')
    const removed = await call('DELETE', '/entities/me/vat-certificate')
    expect(removed.body.data.vat_certificate).toBeNull()
    const missing = await call('POST', '/entities/me/vat-certificate/download')
    expect(missing.status).toBe(404)
    expect(missing.body.code).toBe('entity.vat_certificate_missing')
  })

  it('drops the certificate when the entity answers no', async () => {
    seedMockSession(NAMAA)
    const saved = await call(
      'PATCH',
      '/entities/me/profile/billing',
      await billing({ vat_registered: false, vat_number: null }),
    )
    expect(saved.body.data).toMatchObject({
      vat_number: null,
      vat_certificate: null,
      billing_complete: true,
    })
  })

  it('shows Finance the payer billing data and the incomplete flag', async () => {
    seedMockSession(FINANCE)
    const queue = (await call('GET', '/finance/bank-transfers')).body.data
    const flags = []
    for (const row of queue) {
      const detail = (await call('GET', `/finance/bank-transfers/${row.id}`)).body.data
      expect(detail).toHaveProperty('billing')
      flags.push(detail.billing_complete)
    }
    expect(flags).toEqual(expect.arrayContaining([true, false]))
  })
})
