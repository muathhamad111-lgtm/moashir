import { describe, expect, it } from 'vitest'
import {
  canCompletePayment,
  canDownloadReceipt,
  paymentReferenceOf,
  paymentYearOf,
} from '@/utils/payments/myPaymentRow'

const NOW = new Date('2026-11-04T10:00:00Z').getTime()

describe('my payment rows', () => {
  it('shows the request number for paid rows and the PAY reference otherwise', () => {
    const request = { number: 'ACC-2026-000014' }
    expect(paymentReferenceOf({ status: 'paid', reference: 'PAY-2026-000021', request })).toBe(
      'ACC-2026-000014',
    )
    expect(paymentReferenceOf({ status: 'cancelled', reference: 'PAY-2026-000022', request })).toBe(
      'PAY-2026-000022',
    )
    expect(
      paymentReferenceOf({ status: 'paid', reference: 'PAY-2026-000023', request: null }),
    ).toBe('PAY-2026-000023')
  })

  it('uses the payment year in Riyadh and the right action per status', () => {
    expect(
      paymentYearOf({ paidAt: '2025-12-31T22:30:00Z', createdAt: '2025-12-31T20:00:00Z' }),
    ).toBe('2026')
    expect(canDownloadReceipt({ status: 'paid', receipt: { number: 'RCP-2026-000014' } })).toBe(
      true,
    )
    expect(canDownloadReceipt({ status: 'cancelled', receipt: null })).toBe(false)
    expect(canCompletePayment({ status: 'created', expiresAt: '2026-11-04T10:20:00Z' }, NOW)).toBe(
      true,
    )
    expect(canCompletePayment({ status: 'created', expiresAt: '2026-11-04T09:20:00Z' }, NOW)).toBe(
      false,
    )
    expect(canCompletePayment({ status: 'pending', expiresAt: '2026-11-04T10:20:00Z' }, NOW)).toBe(
      false,
    )
  })
})
