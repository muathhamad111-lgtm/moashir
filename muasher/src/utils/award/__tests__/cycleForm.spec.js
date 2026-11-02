import { describe, expect, it } from 'vitest'
import { changedCyclePayload, cycleFormValues, cyclePayload, cycleSchema, lockedCycleFields } from '@/utils/award/cycleForm'
import { isAssignable, isReassignable, isReturnable, isWithdrawable, stagesFor } from '@/utils/assessments/reviewStages'

const t = (key) => key

const cycle = {
  name: { ar: 'دورة الجائزة', en: 'Award cycle' },
  year: 2026,
  status: 'submission-open',
  criteriaVersionId: 1,
  scaleKind: 'of-10',
  submissionStart: '2026-09-30T21:00:00Z',
  submissionEnd: '2026-12-15T20:59:00Z',
  committeeStart: '2027-01-03T21:00:00Z',
  committeeEnd: '2027-02-15T20:59:00Z',
  announcementAt: null,
  conditions: { minEntityAgeYears: 3, disqualificationScore: '60.00' },
  initialReviewSlaDays: 14,
  participationCertificateEnabled: true,
  regions: [{ id: 1 }, { id: 2 }],
}

describe('cycle form values and payloads', () => {
  it('starts a new cycle from the defaults and the published version, and edits use Riyadh date keys', () => {
    const fresh = cycleFormValues(null, { defaults: { minEntityAgeYears: 3, disqualificationScore: '60.00', initialReviewSlaDays: 14 }, published: { versionId: 4, scaleKind: 'of-10' } })
    expect(fresh).toMatchObject({ criteriaVersionId: 4, scaleKind: 'of-10', minEntityAgeYears: '3', disqualificationScore: '60.00', initialReviewSlaDays: '14', regionIds: [] })
    expect(cycleFormValues(cycle, {})).toMatchObject({ submissionStart: '2026-10-01', submissionEnd: '2026-12-15', committeeStart: '2027-01-04', announcementAt: '', regionIds: [1, 2] })
  })

  it('sends full bodies on create and only the changed, unlocked fields on update', () => {
    const values = cycleFormValues(cycle, {})
    const create = cyclePayload({ ...values, year: '' }, { includeParticipation: false })
    expect(create).not.toHaveProperty('participationCertificateEnabled')
    expect(create).not.toHaveProperty('year')
    expect(create).toMatchObject({ submissionStart: '2026-09-30T21:00:00.000Z', submissionEnd: '2026-12-15T20:59:00.000Z', minEntityAgeYears: 3, disqualificationScore: '60.00', criteriaVersionId: 1 })
    const changed = changedCyclePayload({ ...values, criteriaVersionId: '1', submissionStart: '2026-09-01', disqualificationScore: '65' }, values, { includeParticipation: true, locked: lockedCycleFields('submission-open') })
    expect(changed).toEqual({ disqualificationScore: '65.00' })
  })

  it('locks the fields by stage', () => {
    expect([...lockedCycleFields('draft')]).toEqual([])
    expect(lockedCycleFields('submission-open').has('submissionStart')).toBe(true)
    expect(lockedCycleFields('submission-open').has('submissionEnd')).toBe(false)
    expect(lockedCycleFields('submission-closed').has('regionIds')).toBe(true)
    expect(lockedCycleFields('committee-assessment').has('committeeEnd')).toBe(true)
    expect(lockedCycleFields('committee-assessment').has('name')).toBe(false)
    expect(lockedCycleFields('closed').has('name')).toBe(true)
  })

  it('validates the dates in order, the threshold format and the regions', async () => {
    const schema = cycleSchema(t)
    const valid = cycleFormValues(cycle, {})
    await expect(schema.validate(valid)).resolves.toBeTruthy()
    await expect(schema.validate({ ...valid, committeeStart: '2026-12-01' })).rejects.toThrow('cycles.validation.order.committeeStart')
    await expect(schema.validate({ ...valid, disqualificationScore: '60.555' })).rejects.toThrow('cycles.validation.score')
    await expect(schema.validate({ ...valid, regionIds: [] })).rejects.toThrow('cycles.validation.regions')
    await expect(schema.validate({ ...valid, initialReviewSlaDays: '121' })).rejects.toThrow('cycles.validation.between')
  })
})

describe('review stages per path', () => {
  it('maps the queue actions of both reviewed paths', () => {
    expect(stagesFor('advanced').reviewing).toBe('under-initial-assessment')
    expect(stagesFor('accredited').awaiting).toBe('submitted')
    expect(isAssignable({ path: 'advanced', status: 'awaiting-review-assignment' })).toBe(true)
    expect(isAssignable({ path: 'accredited', status: 'awaiting-review-assignment' })).toBe(false)
    expect(isReassignable({ path: 'advanced', status: 'under-initial-assessment' })).toBe(true)
    expect(isReturnable({ path: 'advanced', status: 'awaiting-review-assignment' })).toBe(true)
    expect(isReturnable({ path: 'accredited', status: 'submitted' })).toBe(false)
    expect(isWithdrawable({ path: 'advanced', status: 'qualified-for-committee' })).toBe(true)
    expect(isWithdrawable({ path: 'advanced', status: 'not-qualified' })).toBe(false)
  })
})
