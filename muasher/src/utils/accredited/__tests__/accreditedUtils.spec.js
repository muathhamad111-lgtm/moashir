import { describe, expect, it } from 'vitest'
import { acceptOf, countsTowardSlot, evidenceOfSlot, exceedsSize, hasAllowedExtension, hasPendingScan, isRequested, slotsOf } from '../evidenceRules'
import { completionPayload, completionSchema, deadlineBounds, itemOptions } from '../completionForm'
import { assessmentSettingsSchema, changedAssessmentSettings, settingsFormValues } from '../assessmentSettingsForm'
import { changedSheetPayload, isEmptyPayload } from '@/utils/assessments/scoreSheet'
import { toCompletionStatus, toRequestStatus, toStatusCounts, toWireRequestStatus } from '@/services/mappers/accreditedStatus'

const t = (key, params) => (params ? `${key}:${JSON.stringify(params)}` : key)

const SUB = {
  id: 101,
  code: 'M1.1',
  hasCv: true,
  attachments: [{ key: 'sub_101_1', name: { ar: 'وثيقة', en: 'Document' }, required: true }],
  points: [{ id: 1001, code: 'M1.1.1', attachments: [{ key: 'point_1001_1', name: { ar: 'نقطة', en: 'Point' }, required: true }] }],
}

describe('evidence rules', () => {
  it('builds the accept list, checks size and extension, and lists sub and point slots', () => {
    expect(acceptOf(['pdf', 'jpg'])).toBe('.pdf,.jpg,.jpeg')
    expect(exceedsSize({ size: 21 * 1024 * 1024 }, 20)).toBe(true)
    expect(exceedsSize({ size: 1024 }, 20)).toBe(false)
    expect(hasAllowedExtension({ name: 'photo.JPEG' }, ['jpg'])).toBe(true)
    expect(hasAllowedExtension({ name: 'notes.txt' }, ['pdf'])).toBe(false)
    expect(slotsOf(SUB).map((slot) => [slot.key, slot.pointCode])).toEqual([
      ['sub_101_1', null],
      ['point_1001_1', 'M1.1.1'],
    ])
  })

  it('counts pending and clean files toward a slot, not refused ones, and reads requested items', () => {
    const evidence = [
      { subCriterionId: 101, attachmentKey: 'sub_101_1', scanStatus: 'infected' },
      { subCriterionId: 101, attachmentKey: null, scanStatus: 'pending' },
    ]
    expect(evidenceOfSlot(evidence, 101, 'sub_101_1').some(countsTowardSlot)).toBe(false)
    expect(hasPendingScan(evidence)).toBe(true)
    const request = { items: [{ subCriterionId: 202, kind: 'cv' }] }
    expect(isRequested(request, 202, 'cv')).toBe(true)
    expect(isRequested(request, 202, 'evidence')).toBe(false)
    expect(isRequested(null, 202, 'cv')).toBe(false)
  })
})

describe('completion request form', () => {
  it('offers CV items only for CV sub-criteria and builds the payload with the end of the Riyadh day', () => {
    const options = itemOptions({ mains: [{ subs: [SUB, { id: 702, code: 'M7.2', hasCv: false, points: [] }] }] })
    expect(options.map((option) => option.key)).toEqual(['101:cv', '101:evidence', '702:evidence'])
    const payload = completionPayload({ notes: '  أكملوا الشواهد  ', items: ['101:cv', '702:evidence'], deadline: '2026-11-20', expiryOutcome: 'decline' }, { '101:cv': 'أضيفوا أمثلة' })
    expect(payload).toEqual({
      notes: 'أكملوا الشواهد',
      items: [{ subCriterionId: 101, kind: 'cv', note: 'أضيفوا أمثلة' }, { subCriterionId: 702, kind: 'evidence' }],
      deadlineAt: '2026-11-20T20:59:00.000Z',
      expiryOutcome: 'decline',
    })
  })

  it('validates notes length, at least one item and a deadline inside the window', async () => {
    const bounds = deadlineBounds(new Date('2026-11-01T09:00:00Z'))
    expect(bounds).toEqual({ min: '2026-11-01', max: '2026-12-30' })
    const schema = completionSchema(t, bounds)
    await expect(schema.validate({ notes: 'قصير', items: [], deadline: '2027-01-15', expiryOutcome: 'decline' }, { abortEarly: false })).rejects.toMatchObject({ errors: expect.arrayContaining([expect.stringContaining('validation.minChars'), 'review.completion.validation.items', expect.stringContaining('review.completion.validation.deadline')]) })
    await expect(schema.validate({ notes: 'يرجى استكمال الشواهد', items: ['101:cv'], deadline: '2026-11-10', expiryOutcome: 'continue_as_submitted' })).resolves.toBeTruthy()
  })
})

describe('assessment settings form', () => {
  const settings = { accreditedReviewSlaDays: 14, uploadMaxFileMb: 20, evidenceMaxFilesPerSub: 10, evidenceAllowedTypes: ['pdf', 'png'] }

  it('sends only the changed keys as numbers and arrays', () => {
    const values = { ...settingsFormValues(settings), accreditedReviewSlaDays: '21', evidenceAllowedTypes: ['png', 'pdf'] }
    expect(changedAssessmentSettings(settings, values)).toEqual({ accreditedReviewSlaDays: 21 })
    expect(changedAssessmentSettings(settings, { ...values, evidenceAllowedTypes: ['pdf'] })).toEqual({ accreditedReviewSlaDays: 21, evidenceAllowedTypes: ['pdf'] })
  })

  it('validates the server limits and at least one type', async () => {
    const schema = assessmentSettingsSchema(t, { min: 1, max: 120 })
    await expect(schema.validate({ accreditedReviewSlaDays: '0', uploadMaxFileMb: '101', evidenceMaxFilesPerSub: '5', evidenceAllowedTypes: [] }, { abortEarly: false })).rejects.toMatchObject({ errors: expect.arrayContaining(['settings.assessment.validation.types']) })
  })
})

describe('partial score sheet and status mapper', () => {
  it('sends only changed items, with null for a cleared score', () => {
    const units = [{ level: 'sub', id: 103 }, { level: 'point', id: 1001 }, { level: 'point', id: 1002 }]
    const scores = { subs: { 103: { rawValue: '7.00' } }, points: { 1001: { rawValue: '8.00' } } }
    const payload = changedSheetPayload({ 'sub-103': '7', 'point-1001': '', 'point-1002': '9.5' }, units, scores)
    expect(payload).toEqual({ subs: {}, points: { 1001: null, 1002: '9.5' } })
    expect(isEmptyPayload(changedSheetPayload({ 'sub-103': '7.00', 'point-1001': '8', 'point-1002': '' }, units, scores))).toBe(true)
  })

  it('keeps the declined terminal state and the expired completion states in one mapper', () => {
    expect(toRequestStatus('declined_incomplete')).toBe('declined')
    expect(toRequestStatus('under_review')).toBe('under-review')
    expect(toWireRequestStatus('declined')).toBe('declined-incomplete')
    expect(toCompletionStatus('expired_declined')).toBe('expired-declined')
    expect(toStatusCounts({ declinedIncomplete: 2, underReview: 1 }, ['declined', 'under-review', 'completed'])).toEqual({ declined: 2, 'under-review': 1, completed: 0 })
  })
})
