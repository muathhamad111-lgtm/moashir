import { describe, expect, it } from 'vitest'
import {
  changedCyclePayload,
  cycleFormValues,
  cyclePayload,
  cycleSchema,
  lockedCycleFields,
} from '@/utils/award/cycleForm'
import {
  isAssignable,
  isReassignable,
  isReturnable,
  isWithdrawable,
  stagesFor,
} from '@/utils/assessments/reviewStages'

const t = (key) => key

const cycle = {
  name: { ar: 'دورة الجائزة', en: 'Award cycle' },
  year: 2026,
  status: 'submission-open',
  description: { ar: 'تعريف الدورة', en: 'Cycle description' },
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
  it('starts a new cycle from the defaults without a criteria version of its own, and edits use Riyadh date keys', () => {
    const fresh = cycleFormValues(null, {
      defaults: { minEntityAgeYears: 3, disqualificationScore: '60.00', initialReviewSlaDays: 14 },
    })
    expect(fresh).not.toHaveProperty('criteriaVersionId')
    expect(fresh).toMatchObject({
      description: { ar: '', en: '' },
      minEntityAgeYears: '3',
      disqualificationScore: '60.00',
      initialReviewSlaDays: '14',
      regionIds: [],
    })
    expect(cycleFormValues(cycle, {})).toMatchObject({
      description: { ar: 'تعريف الدورة', en: 'Cycle description' },
      submissionStart: '2026-10-01',
      submissionEnd: '2026-12-15',
      committeeStart: '2027-01-04',
      announcementAt: '',
      regionIds: [1, 2],
    })
  })

  it('sends full bodies on create and only the changed, unlocked fields on update', () => {
    const values = cycleFormValues(cycle, {})
    const create = cyclePayload({ ...values, year: '' }, { includeParticipation: false })
    expect(create).not.toHaveProperty('participationCertificateEnabled')
    expect(create).not.toHaveProperty('year')
    expect(create).toMatchObject({
      submissionStart: '2026-09-30T21:00:00.000Z',
      submissionEnd: '2026-12-15T20:59:00.000Z',
      minEntityAgeYears: 3,
      disqualificationScore: '60.00',
      description: { ar: 'تعريف الدورة', en: 'Cycle description' },
    })
    expect(create).not.toHaveProperty('criteriaVersionId')
    const changed = changedCyclePayload(
      {
        ...values,
        submissionStart: '2026-09-01',
        disqualificationScore: '65',
      },
      values,
      { includeParticipation: true, locked: lockedCycleFields('submission-open') },
    )
    expect(changed).toEqual({ disqualificationScore: '65.00' })
  })

  it('sends a blank description as cleared on update and leaves it out on create', () => {
    const values = cycleFormValues(cycle, {})
    const blank = { ...values, description: { ar: '  ', en: '' } }
    expect(cyclePayload(blank, { includeParticipation: false })).not.toHaveProperty('description')
    expect(
      changedCyclePayload(blank, values, {
        includeParticipation: false,
        locked: lockedCycleFields('submission-open'),
      }),
    ).toEqual({ description: null })
    expect(
      changedCyclePayload({ ...values, description: { ar: ' نص جديد ', en: 'New text' } }, values, {
        includeParticipation: false,
        locked: lockedCycleFields('closed'),
      }),
    ).toEqual({})
  })

  it('reads a fee of 0 as free and a positive fee as paid, and sends 0 for a free cycle', async () => {
    expect(cycleFormValues({ ...cycle, entryFee: '0.00' }, {})).toMatchObject({
      entryFeePaid: false,
      entryFee: '',
    })
    const paid = cycleFormValues({ ...cycle, entryFee: '750.00' }, {})
    expect(paid).toMatchObject({ entryFeePaid: true, entryFee: '750.00' })
    const options = { includeParticipation: false, locked: lockedCycleFields('draft') }
    expect(changedCyclePayload({ ...paid, entryFeePaid: false }, paid, options)).toEqual({
      entryFee: '0.00',
    })
    expect(changedCyclePayload({ ...paid, entryFee: '800' }, paid, options)).toEqual({
      entryFee: '800.00',
    })
    expect(changedCyclePayload({ ...paid, entryFee: '0' }, paid, options)).not.toHaveProperty(
      'entryFeePaid',
    )
    const schema = cycleSchema(t)
    const valid = cycleFormValues(cycle, {})
    await expect(schema.validate({ ...valid, entryFeePaid: true, entryFee: '0' })).rejects.toThrow(
      'cycles.validation.entryFee',
    )
    await expect(schema.validate({ ...valid, entryFeePaid: true, entryFee: '' })).rejects.toThrow(
      'cycles.validation.entryFee',
    )
    await expect(
      schema.validate({ ...valid, entryFeePaid: false, entryFee: '' }),
    ).resolves.toBeTruthy()
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
    await expect(schema.validate({ ...valid, committeeStart: '2026-12-01' })).rejects.toThrow(
      'cycles.validation.order.committeeStart',
    )
    await expect(schema.validate({ ...valid, disqualificationScore: '60.555' })).rejects.toThrow(
      'cycles.validation.score',
    )
    await expect(schema.validate({ ...valid, regionIds: [] })).rejects.toThrow(
      'cycles.validation.regions',
    )
    await expect(schema.validate({ ...valid, initialReviewSlaDays: '121' })).rejects.toThrow(
      'cycles.validation.between',
    )
    await expect(schema.validate({ ...valid, description: { ar: 'نص', en: '' } })).rejects.toThrow(
      'cycles.validation.descriptionPair',
    )
    await expect(
      schema.validate({ ...valid, description: { ar: 'ت'.repeat(2001), en: 'Text' } }),
    ).rejects.toThrow('entities.validation.maxChars')
    await expect(
      schema.validate({ ...valid, description: { ar: '', en: '' } }),
    ).resolves.toBeTruthy()
  })
})

describe('review stages per path', () => {
  it('maps the queue actions of both reviewed paths', () => {
    expect(stagesFor('advanced').reviewing).toBe('under-initial-assessment')
    expect(stagesFor('accredited').awaiting).toBe('submitted')
    expect(isAssignable({ path: 'advanced', status: 'awaiting-review-assignment' })).toBe(true)
    expect(isAssignable({ path: 'accredited', status: 'awaiting-review-assignment' })).toBe(false)
    const withAssessor = { assessor: { id: 10 } }
    expect(
      isReassignable({ path: 'advanced', status: 'under-initial-assessment', ...withAssessor }),
    ).toBe(true)
    // Operator 2026-11-23: also while the entity completes it, when there is an assessor to hand over.
    expect(isReassignable({ path: 'advanced', status: 'needs-completion', ...withAssessor })).toBe(
      true,
    )
    expect(
      isReassignable({
        path: 'advanced',
        status: 'needs-completion',
        assignmentHistory: [{ status: 'active' }],
      }),
    ).toBe(true)
    expect(isReassignable({ path: 'advanced', status: 'needs-completion' })).toBe(false)
    expect(isReturnable({ path: 'advanced', status: 'awaiting-review-assignment' })).toBe(true)
    // After assignment too, back to the same assessor (operator 2026-11-22).
    expect(isReturnable({ path: 'advanced', status: 'assigned' })).toBe(true)
    expect(isReturnable({ path: 'advanced', status: 'under-initial-assessment' })).toBe(true)
    expect(isReturnable({ path: 'advanced', status: 'awaiting-filtering' })).toBe(false)
    expect(isReturnable({ path: 'accredited', status: 'submitted' })).toBe(false)
    expect(isWithdrawable({ path: 'advanced', status: 'qualified-for-committee' })).toBe(true)
    expect(isWithdrawable({ path: 'advanced', status: 'not-qualified' })).toBe(false)
  })
})
