import { describe, expect, it } from 'vitest'
import { completeMessageKey } from '@/utils/review/completeMessage'

const base = { hasPendingChanges: false, offersCertificate: true, issuanceMode: 'automatic' }

describe('assessor completion message', () => {
  it('promises an automatic certificate only in automatic mode', () => {
    expect(completeMessageKey(base)).toBe('review.confirmComplete.certificateMessage')
    expect(completeMessageKey({ ...base, issuanceMode: 'manual' })).toBe(
      'review.confirmComplete.manualCertificateMessage',
    )
  })

  it('stays neutral when the mode is unknown or the path has no certificate', () => {
    expect(completeMessageKey({ ...base, issuanceMode: null })).toBe(
      'review.confirmComplete.message',
    )
    expect(completeMessageKey({ ...base, offersCertificate: false })).toBe(
      'review.confirmComplete.message',
    )
  })

  it('asks to save pending changes first', () => {
    expect(completeMessageKey({ ...base, hasPendingChanges: true })).toBe(
      'review.confirmComplete.pendingMessage',
    )
  })
})
