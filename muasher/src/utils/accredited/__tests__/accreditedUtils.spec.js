import { describe, expect, it } from 'vitest'
import {
  acceptOf,
  countsTowardSlot,
  evidenceOfSlot,
  exceedsSize,
  hasAllowedExtension,
  hasPendingScan,
  isRequested,
  slotGroupsOf,
  slotMaxFilesOf,
  slotTypesOf,
  slotsOf,
} from '../evidenceRules'
import { completionPayload, completionSchema, deadlineBounds, itemOptions } from '../completionForm'
import {
  assessmentSettingsSchema,
  changedAssessmentSettings,
  settingsFormValues,
} from '../assessmentSettingsForm'
import { changedSheetPayload, isEmptyPayload } from '@/utils/assessments/scoreSheet'
import {
  toCompletionStatus,
  toRequestStatus,
  toStatusCounts,
  toWireRequestStatus,
} from '@/services/mappers/accreditedStatus'

const t = (key, params) => (params ? `${key}:${JSON.stringify(params)}` : key)

const SUB = {
  id: 101,
  code: 'M1.1',
  hasCv: true,
  attachments: [{ key: 'sub_101_1', name: { ar: 'وثيقة', en: 'Document' }, required: true }],
  points: [
    {
      id: 1001,
      code: 'M1.1.1',
      attachments: [{ key: 'point_1001_1', name: { ar: 'نقطة', en: 'Point' }, required: true }],
    },
  ],
}

describe('evidence rules', () => {
  it('builds the accept list, checks size and extension, and lists sub and point slots', () => {
    expect(acceptOf(['pdf', 'jpg'])).toBe('.pdf,.jpg,.jpeg')
    expect(exceedsSize({ size: 21 * 1024 * 1024 }, 20)).toBe(true)
    expect(exceedsSize({ size: 1024 }, 20)).toBe(false)
    expect(hasAllowedExtension({ name: 'photo.JPEG' }, ['jpg'])).toBe(true)
    expect(hasAllowedExtension({ name: 'notes.txt' }, ['pdf'])).toBe(false)
    expect(slotsOf(SUB).map((slot) => slot.key)).toEqual(['sub_101_1', 'point_1001_1'])
  })

  it('counts pending and clean files toward a slot, not refused ones, and reads requested items', () => {
    const evidence = [
      { subCriterionId: 101, attachmentKey: 'sub_101_1', scanStatus: 'infected' },
      { subCriterionId: 101, attachmentKey: null, scanStatus: 'pending' },
    ]
    const sub = (id) => ({ level: 'sub', id })
    expect(evidenceOfSlot(evidence, sub(101), 'sub_101_1').some(countsTowardSlot)).toBe(false)
    expect(hasPendingScan(evidence)).toBe(true)
    const request = {
      items: [
        { subCriterionId: 202, kind: 'cv' },
        { mainCriterionId: 2, subCriterionId: null, kind: 'evidence' },
      ],
    }
    expect(isRequested(request, sub(202), 'cv')).toBe(true)
    expect(isRequested(request, sub(202), 'evidence')).toBe(false)
    expect(isRequested(request, { level: 'main', id: 2 }, 'evidence')).toBe(true)
    expect(isRequested(request, sub(2), 'evidence')).toBe(false)
    expect(isRequested(null, sub(202), 'cv')).toBe(false)
  })

  it('keeps a main criterion file apart from a sub-criterion with the same id', () => {
    const evidence = [
      { level: 'main', mainCriterionId: 1, subCriterionId: null, attachmentKey: 'plan' },
      { level: 'sub', mainCriterionId: null, subCriterionId: 1, attachmentKey: 'plan' },
    ]
    expect(evidenceOfSlot(evidence, { level: 'main', id: 1 }, 'plan')).toEqual([evidence[0]])
    expect(evidenceOfSlot(evidence, { level: 'sub', id: 1 }, 'plan')).toEqual([evidence[1]])
  })

  it('takes the slot types and file count within the path settings, as the server does', () => {
    const limits = { allowedTypes: ['pdf', 'docx'], maxFilesPerSub: 10 }
    expect(slotTypesOf({ allowedTypes: ['pdf', 'png'] }, limits)).toEqual(['pdf'])
    expect(slotTypesOf({ allowedTypes: ['png'] }, limits)).toEqual(['pdf', 'docx'])
    expect(slotTypesOf(null, limits)).toEqual(['pdf', 'docx'])
    expect(slotMaxFilesOf({ maxFiles: 3 }, limits)).toBe(3)
    expect(slotMaxFilesOf({ maxFiles: 30 }, limits)).toBe(10)
    expect(slotMaxFilesOf({ maxFiles: null }, limits)).toBe(10)
  })

  it('groups a sub-criterion slots: its own first, then each indicator with attachments', () => {
    const groups = slotGroupsOf({
      attachments: [{ key: 'own' }],
      points: [
        { id: 1, attachments: [] },
        { id: 2, attachments: [{ key: 'survey' }] },
      ],
    })
    expect(groups.map((group) => [group.key, group.slots.map((slot) => slot.key)])).toEqual([
      ['own', ['own']],
      ['point-2', ['survey']],
    ])
  })
})

describe('completion request form', () => {
  it('offers a main criterion its own attachments only and sends it as a main criterion item', () => {
    const options = itemOptions({
      mains: [{ id: 3, attachments: [{ key: 'plan' }], subs: [{ id: 702, hasCv: false }] }],
    })
    expect(options.map((option) => option.key)).toEqual(['main-3:evidence', '702:evidence'])
    const payload = completionPayload(
      { notes: 'x', items: ['main-3:evidence'], deadline: '2026-11-20', expiryOutcome: 'decline' },
      {},
    )
    expect(payload.items).toEqual([{ mainCriterionId: 3, kind: 'evidence' }])
  })

  it('offers CV items only for CV sub-criteria and builds the payload with the end of the Riyadh day', () => {
    const options = itemOptions({
      mains: [{ subs: [SUB, { id: 702, code: 'M7.2', hasCv: false, points: [] }] }],
    })
    expect(options.map((option) => option.key)).toEqual(['101:cv', '101:evidence', '702:evidence'])
    const payload = completionPayload(
      {
        notes: '  أكمل الشواهد  ',
        items: ['101:cv', '702:evidence'],
        deadline: '2026-11-20',
        expiryOutcome: 'decline',
      },
      { '101:cv': 'أضف أمثلة' },
    )
    expect(payload).toEqual({
      notes: 'أكمل الشواهد',
      items: [
        { subCriterionId: 101, kind: 'cv', note: 'أضف أمثلة' },
        { subCriterionId: 702, kind: 'evidence' },
      ],
      deadlineAt: '2026-11-20T20:59:00.000Z',
      expiryOutcome: 'decline',
    })
  })

  it('requires notes of any length, at least one item and a deadline inside the window', async () => {
    const bounds = deadlineBounds(new Date('2026-11-01T09:00:00Z'))
    expect(bounds).toEqual({ min: '2026-11-01', max: '2026-12-30' })
    const schema = completionSchema(t, bounds)
    await expect(
      schema.validate(
        { notes: '  ', items: [], deadline: '2027-01-15', expiryOutcome: 'decline' },
        { abortEarly: false },
      ),
    ).rejects.toMatchObject({
      errors: expect.arrayContaining([
        'validation.required',
        'review.completion.validation.items',
        expect.stringContaining('review.completion.validation.deadline'),
      ]),
    })
    await expect(
      schema.validate({
        notes: 'يرجى استكمال الشواهد',
        items: ['101:cv'],
        deadline: '2026-11-10',
        expiryOutcome: 'continue_as_submitted',
      }),
    ).resolves.toBeTruthy()
    await expect(
      schema.validate({
        notes: 'اهلا',
        items: ['101:cv'],
        deadline: '2026-11-10',
        expiryOutcome: 'decline',
      }),
    ).resolves.toBeTruthy()
  })
})

describe('assessment settings form', () => {
  const settings = {
    generalUploadMaxFileMb: 20,
    advancedMinEntityAgeYears: 3,
    advancedDisqualificationScore: '60.00',
    committeeEntityDataVisibility: 'name_only',
    scaleKind: 'of-10',
  }

  it('sends the chosen assessment scale only when it changes', () => {
    const values = settingsFormValues(settings)
    expect(changedAssessmentSettings(settings, values)).toEqual({})
    expect(changedAssessmentSettings(settings, { ...values, scaleKind: 'relative' })).toEqual({
      scaleKind: 'relative',
    })
  })

  it('sends only the changed general keys and never the legacy path aliases', () => {
    const values = { ...settingsFormValues(settings), generalUploadMaxFileMb: '٣٠' }
    expect(changedAssessmentSettings(settings, values)).toEqual({ generalUploadMaxFileMb: 30 })
    expect(Object.keys(settingsFormValues(settings)).sort()).toEqual([
      'advancedDisqualificationScore',
      'advancedMinEntityAgeYears',
      'committeeEntityDataVisibility',
      'generalUploadMaxFileMb',
      'scaleKind',
    ])
  })

  it('validates the general upload bounds', async () => {
    const schema = assessmentSettingsSchema(t)
    await expect(
      schema.validate(
        { ...settingsFormValues(settings), generalUploadMaxFileMb: '101' },
        { abortEarly: false },
      ),
    ).rejects.toMatchObject({
      errors: [expect.stringContaining('settings.assessment.validation.range')],
    })
  })
})

describe('partial score sheet and status mapper', () => {
  it('sends only changed items, with null for a cleared score', () => {
    const units = [
      { level: 'sub', id: 103 },
      { level: 'point', id: 1001 },
      { level: 'point', id: 1002 },
    ]
    const scores = { subs: { 103: { rawValue: '7.00' } }, points: { 1001: { rawValue: '8.00' } } }
    const payload = changedSheetPayload(
      { 'sub-103': '7', 'point-1001': '', 'point-1002': '9.5' },
      units,
      scores,
    )
    expect(payload).toEqual({ subs: {}, points: { 1001: null, 1002: '9.5' } })
    expect(
      isEmptyPayload(
        changedSheetPayload(
          { 'sub-103': '7.00', 'point-1001': '8', 'point-1002': '' },
          units,
          scores,
        ),
      ),
    ).toBe(true)
  })

  it('keeps the declined terminal state and the expired completion states in one mapper', () => {
    expect(toRequestStatus('declined_incomplete')).toBe('declined')
    expect(toRequestStatus('under_review')).toBe('under-review')
    expect(toWireRequestStatus('declined')).toBe('declined-incomplete')
    expect(toCompletionStatus('expired_declined')).toBe('expired-declined')
    expect(
      toStatusCounts({ declinedIncomplete: 2, underReview: 1 }, [
        'declined',
        'under-review',
        'completed',
      ]),
    ).toEqual({ declined: 2, 'under-review': 1, completed: 0 })
  })
})
