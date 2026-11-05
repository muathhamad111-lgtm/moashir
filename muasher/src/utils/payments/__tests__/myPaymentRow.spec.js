import { describe, expect, it } from 'vitest'
import {
  canCompletePayment,
  canDownloadReceipt,
  isBankTransfer,
  newRequestRouteOf,
  paymentReferenceOf,
  paymentYearOf,
  showsTransferState,
} from '@/utils/payments/myPaymentRow'
import { ROUTE_NAMES } from '@/constants/routeNames'

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

  it('treats bank transfers apart: no card completion, details while under review, a new request after a rejection', () => {
    const underReview = { channel: 'bank_transfer', status: 'under-review', purpose: 'bag' }
    const rejected = { channel: 'bank_transfer', status: 'rejected', purpose: 'accredited' }
    expect(isBankTransfer(underReview)).toBe(true)
    expect(showsTransferState(underReview)).toBe(true)
    expect(newRequestRouteOf(underReview)).toBeNull()
    expect(newRequestRouteOf(rejected)).toEqual({ name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_PAY })
    expect(newRequestRouteOf({ ...rejected, purpose: 'bag' })).toEqual({
      name: ROUTE_NAMES.BAG_PAY,
    })
    expect(
      canCompletePayment(
        { channel: 'bank_transfer', status: 'created', expiresAt: '2026-11-04T10:20:00Z' },
        NOW,
      ),
    ).toBe(false)
  })
})
