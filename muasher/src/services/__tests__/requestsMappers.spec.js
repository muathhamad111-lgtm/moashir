import { describe, expect, it } from 'vitest'
import { toAssignedRow, toQueueRow, toRequestDetail } from '@/services/mappers/requests'

const LAST_LEVEL = {
  level: 'silver',
  validUntil: '2026-11-01',
  validity: 'expired',
  daysUntilExpiry: null,
}

const ENTITY = {
  id: 6002,
  reference: 'ENT-006002',
  name: { ar: 'جمعية إحسان' },
  status: 'active',
  level: null,
  lastLevel: LAST_LEVEL,
}

describe('request mappers — entity level', () => {
  it('keeps the counted level and the last level of the entity on a queue row', () => {
    expect(toQueueRow({ id: 9101, entity: ENTITY }).entity).toMatchObject({
      level: null,
      lastLevel: LAST_LEVEL,
    })
  })

  it('carries the last level into the request detail and defaults it to null', () => {
    expect(toRequestDetail({ id: 9101, entity: ENTITY }).entity.lastLevel).toEqual(LAST_LEVEL)
    expect(
      toQueueRow({ id: 9102, entity: { ...ENTITY, lastLevel: undefined } }).entity.lastLevel,
    ).toBeNull()
  })
})

describe('request mappers — assigned row progress', () => {
  it('keeps the scoring progress of an assigned request and tolerates its absence', () => {
    expect(
      toAssignedRow({ id: 1, reviewProgress: { scored: 10, required: 26 } }).reviewProgress,
    ).toEqual({
      scored: 10,
      required: 26,
    })
    expect(toAssignedRow({ id: 1 }).reviewProgress).toBeNull()
  })
})

describe('request mappers — payment by audience', () => {
  const FULL = {
    paid: true,
    reference: 'PAY-2026-000010',
    channel: 'bank_transfer',
    status: 'paid',
    isTest: false,
    paidAt: '2026-11-19T09:58:00Z',
    receipt: null,
  }

  it('keeps the payment details of a finance viewer on the request detail', () => {
    const detail = toRequestDetail({ id: 1, payment: FULL })
    expect(detail.payment.paid).toBe(true)
    expect(detail.payment.details.reference).toBe('PAY-2026-000010')
  })

  it('shows only the paid flag when the API sends no details', () => {
    expect(toRequestDetail({ id: 1, payment: { paid: true } }).payment).toEqual({
      paid: true,
      details: null,
    })
  })
})

// Request page v4.1: the record header's entity logo and whether the request has certificates (for its tab).
describe('request mappers — record header', () => {
  it('keeps the entity logo link and the certificates flag', () => {
    const detail = toRequestDetail({
      id: 9104,
      entity: {
        ...ENTITY,
        logo: { id: 'f1', url: 'https://files.test/l.png', uploadedAt: '2026-11-01' },
      },
      hasCertificates: true,
    })
    expect(detail.entity.logo).toEqual({
      id: 'f1',
      url: 'https://files.test/l.png',
      uploadedAt: '2026-11-01',
    })
    expect(detail.hasCertificates).toBe(true)
  })

  it('has no logo and no certificates when the API sends none', () => {
    const detail = toRequestDetail({ id: 9105, entity: ENTITY })
    expect(detail.entity.logo).toBeNull()
    expect(detail.hasCertificates).toBe(false)
    expect(toRequestDetail({ id: 1 }).entity).toBeNull()
  })
})
