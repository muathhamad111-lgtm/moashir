import { describe, expect, it } from 'vitest'
import {
  SETUP_STEPS,
  attentionStep,
  descriptionParagraphs,
  lockReasons,
  stepSchema,
  stepValues,
} from '@/utils/award/cycleSetup'
import { summarySections } from '@/utils/award/cycleSummary'

const t = (key, values) => (values ? `${key}:${JSON.stringify(values)}` : key)

const CYCLE = {
  id: 32,
  name: { ar: 'الدورة القادمة', en: 'Next cycle' },
  description: null,
  year: 2027,
  status: 'draft',
  canManage: true,
  revealsStaff: true,
  thresholdLocked: false,
  submissionStart: '2027-01-31T21:00:00Z',
  submissionEnd: '2027-03-15T20:59:00Z',
  committeeStart: '2027-03-31T21:00:00Z',
  committeeEnd: '2027-05-15T20:59:00Z',
  announcementAt: null,
  conditions: { minEntityAgeYears: 3, disqualificationScore: '60.00' },
  initialReviewSlaDays: 14,
  participationCertificateEnabled: false,
  entryFee: '750.00',
  regions: [{ id: 1, name: { ar: 'الرياض', en: 'Riyadh' } }],
  members: [{ id: 18, name: 'أمين الجائزة', role: 'award_secretary' }],
  initialAssessors: [],
}

describe('cycle setup steps (R-4)', () => {
  it('runs the seven steps, with the regions inside the conditions (operator 2026-11-23)', () => {
    expect(SETUP_STEPS).toEqual([
      'identity',
      'dates',
      'conditions',
      'deadlines',
      'fee',
      'team',
      'review',
    ])
  })

  it('opens a new cycle at the identity, a cycle without an active initial assessor at the team, and others at the review', () => {
    expect(attentionStep(null)).toBe('identity')
    expect(attentionStep(CYCLE)).toBe('team')
    expect(
      attentionStep({ ...CYCLE, initialAssessors: [{ id: 10, name: 'م', isActive: false }] }),
    ).toBe('team')
    expect(
      attentionStep({ ...CYCLE, initialAssessors: [{ id: 10, name: 'م', isActive: true }] }),
    ).toBe('review')
    expect(attentionStep({ ...CYCLE, revealsStaff: false })).toBe('review')
    expect(attentionStep({ ...CYCLE, status: 'closed' })).toBe('review')
  })

  it('keeps only the fields of a step and validates them alone', async () => {
    const values = { name: { ar: 'د', en: 'C' }, year: '', description: { ar: '', en: '' } }
    expect(stepValues({ ...values, regionIds: [1] }, 'identity')).toEqual(values)
    await expect(stepSchema(t, 'identity').validate(values)).resolves.toBeTruthy()
    await expect(
      stepSchema(t, 'identity').validate({ ...values, name: { ar: '', en: 'C' } }),
    ).rejects.toThrow('validation.required')
    const conditions = {
      regionIds: [],
      minEntityAgeYears: '3',
      disqualificationScore: '60.00',
      participationCertificateEnabled: false,
    }
    expect(stepValues({ ...conditions, initialReviewSlaDays: '14' }, 'conditions')).toEqual(
      conditions,
    )
    await expect(stepSchema(t, 'conditions').validate(conditions)).rejects.toThrow(
      'cycles.validation.regions',
    )
    await expect(
      stepSchema(t, 'conditions').validate({ ...conditions, regionIds: [1] }),
    ).resolves.toBeTruthy()
  })

  it('explains each locked field of a step once, and says read only to a reader', () => {
    const open = { ...CYCLE, status: 'submission-open' }
    const locked = new Set(['submissionStart', 'entryFee'])
    expect(lockReasons('dates', { cycle: open, locked })).toEqual(['draftOnly'])
    expect(lockReasons('fee', { cycle: open, locked })).toEqual([])
    expect(lockReasons('identity', { cycle: open, locked })).toEqual([])
    expect(
      lockReasons('conditions', {
        cycle: { ...open, status: 'submission-closed' },
        locked: new Set(['regionIds', 'minEntityAgeYears', 'disqualificationScore']),
      }),
    ).toEqual(['untilSubmissionsClose'])
    expect(
      lockReasons('identity', {
        cycle: { ...CYCLE, canManage: false },
        locked: new Set(['name', 'year', 'description']),
      }),
    ).toEqual(['readOnly'])
    expect(lockReasons('identity', { cycle: null, locked })).toEqual([])
  })

  it('splits a plain description into paragraphs on empty lines', () => {
    expect(descriptionParagraphs('الفقرة الأولى\nتكملة\n\n  الفقرة الثانية  \n\n\n')).toEqual([
      'الفقرة الأولى\nتكملة',
      'الفقرة الثانية',
    ])
    expect(descriptionParagraphs(null)).toEqual([])
  })
})

describe('cycle setup summary', () => {
  const format = {
    t,
    plain: (value) => (value === null || value === undefined ? '—' : String(value)),
    date: (value) => value ?? '—',
    regionsTotal: 13,
    text: (value) => value?.en ?? '—',
    window: (start, end) => `${start} → ${end}`,
    flag: (value) => (value ? 'on' : 'off'),
    list: (names) => names.join(', ') || '—',
  }

  it('summarises every step and flags the missing initial assessor', () => {
    const sections = summarySections(CYCLE, format)
    expect(sections.map((section) => section.key)).toEqual([
      'identity',
      'dates',
      'conditions',
      'deadlines',
      'fee',
      'team',
    ])
    const conditions = sections.find((section) => section.key === 'conditions')
    expect(conditions.rows[0]).toMatchObject({ label: 'cycles.form.regions', long: true })
    expect(conditions.rows[0].value).toContain('Riyadh')
    expect(conditions.rows[0].value).toContain('"total":13')
    expect(JSON.stringify(sections)).not.toContain('version')
    const team = sections.find((section) => section.key === 'team')
    expect(team.rows.at(-1)).toMatchObject({ missing: true })
    expect(team.rows.find((row) => row.label.includes('award_secretary')).value).toBe(
      'أمين الجائزة',
    )
  })

  it('lists the committee assessors in the team, never as missing, once the roster is read', () => {
    const teamOf = (committee) =>
      summarySections(CYCLE, format, committee).find((section) => section.key === 'team').rows
    const committeeRow = (committee) => teamOf(committee).find((row) => row.key === 'committee')
    expect(committeeRow(['David Nguyen', 'عمر'])).toMatchObject({
      label: 'cycles.staff.committee.label',
      value: 'David Nguyen, عمر',
    })
    expect(committeeRow([])).toMatchObject({ value: 'cycles.staff.committee.none' })
    expect(committeeRow([]).missing).toBeFalsy()
    expect(teamOf([]).filter((row) => row.missing)).toHaveLength(1)
    expect(committeeRow(null)).toBeUndefined()
    expect(committeeRow(undefined)).toBeUndefined()
  })

  it('leaves the team out for a reader who does not see it', () => {
    const keys = summarySections({ ...CYCLE, revealsStaff: false }, format).map((s) => s.key)
    expect(keys).not.toContain('team')
  })
})
