import { describe, expect, it } from 'vitest'
import { isReviewFinished } from '@/utils/assessments/reviewStages'

// The review page shows the final result once the request left the assessor's stages.
describe('finished review', () => {
  it('is finished once the request left the assessor stages', () => {
    expect(isReviewFinished({ path: 'accredited', status: 'completed' })).toBe(true)
    expect(isReviewFinished({ path: 'accredited', status: 'refunded' })).toBe(true)
    expect(isReviewFinished({ path: 'advanced', status: 'awaiting-filtering' })).toBe(true)
  })

  it('is still open while the request is assigned, under review or with the entity', () => {
    expect(isReviewFinished({ path: 'accredited', status: 'assigned' })).toBe(false)
    expect(isReviewFinished({ path: 'accredited', status: 'under-review' })).toBe(false)
    expect(isReviewFinished({ path: 'accredited', status: 'needs-completion' })).toBe(false)
    expect(isReviewFinished({ path: 'advanced', status: 'under-initial-assessment' })).toBe(false)
    expect(isReviewFinished(null)).toBe(false)
  })
})
