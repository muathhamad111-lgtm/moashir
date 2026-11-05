import { describe, expect, it } from 'vitest'
import { ISSUING_STAGES, issuingRequestsOf } from '@/utils/certificates/issuingRequests'
import { normalizeVerificationCode } from '@/utils/certificates/verificationCode'

describe('issuing requests', () => {
  it('lists the open accredited request and completed requests without a certificate', () => {
    const items = issuingRequestsOf({
      active: {
        id: 14,
        number: 'ACC-2026-000014',
        status: 'draft',
        createdAt: '2026-11-01T08:00:00Z',
      },
      completed: [
        {
          id: 9,
          number: 'ACC-2025-000009',
          status: 'completed',
          completedAt: '2025-11-20T08:00:00Z',
        },
        {
          id: 7,
          number: 'ACC-2025-000007',
          status: 'completed',
          completedAt: '2025-06-01T08:00:00Z',
        },
      ],
      certifiedRequestIds: [9],
    })
    expect(items.map((item) => [item.id, item.stage])).toEqual([
      [14, ISSUING_STAGES.REVIEW],
      [7, ISSUING_STAGES.ISSUING],
    ])
  })

  it('ignores a completed active request and returns nothing without requests', () => {
    expect(issuingRequestsOf({ active: { id: 3, status: 'completed' } })).toEqual([])
    expect(issuingRequestsOf({})).toEqual([])
  })
})

describe('verification code', () => {
  it('uppercases the code and removes spaces but keeps the dashes', () => {
    expect(normalizeVerificationCode(' 9f3k-mq2t 7xhd ')).toBe('9F3K-MQ2T7XHD')
    expect(normalizeVerificationCode(null)).toBe('')
  })
})
