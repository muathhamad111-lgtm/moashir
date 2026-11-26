import { describe, expect, it } from 'vitest'
import {
  STATION_STATES,
  hasEntityInput,
  isSubComplete,
  stationsOf,
  submissionProgressOf,
} from '@/utils/accredited/submissionProgress'

const FORM = {
  criteria: {
    mains: [
      {
        id: 1,
        code: 'M1',
        name: { ar: 'القيادة', en: 'Leadership' },
        subs: [{ id: 11 }, { id: 12 }],
      },
      {
        id: 2,
        code: 'M2',
        name: { ar: 'التخطيط', en: 'Planning' },
        subs: [{ id: 21 }, { id: 22 }],
      },
      { id: 3, code: 'M3', name: { ar: 'الموارد', en: 'Resources' }, subs: [{ id: 31 }] },
    ],
  },
  missing: {
    missingCv: [{ subCriterionId: 21 }, { subCriterionId: 31 }],
    missingAttachments: [{ subCriterionId: 22, attachmentKey: 'plan' }],
  },
  evidence: [{ id: 'e1', subCriterionId: 22, scanStatus: 'clean' }],
  cv: {},
}

describe('submission progress', () => {
  it('counts complete sub-criteria per station with the same gaps as submit', () => {
    expect(
      stationsOf(FORM).map(({ id, complete, total, state }) => [id, complete, total, state]),
    ).toEqual([
      [1, 2, 2, STATION_STATES.COMPLETE],
      [2, 0, 2, STATION_STATES.PARTIAL],
      [3, 0, 1, STATION_STATES.EMPTY],
    ])
    expect(submissionProgressOf(FORM)).toEqual({ total: 5, complete: 2, remaining: 3 })
  })

  it("keeps a station open until its main criterion's required attachments are uploaded", () => {
    const plan = { key: 'plan', required: true }
    const mains = FORM.criteria.mains.map((main) =>
      main.id === 1 ? { ...main, attachments: [plan] } : main,
    )
    const gap = { mainCriterionId: 1, attachmentKey: 'plan' }
    const missing = { ...FORM.missing, missingMainAttachments: [gap] }
    const form = { ...FORM, criteria: { mains }, missing }
    expect(stationsOf(form)[0]).toMatchObject({
      complete: 2,
      total: 2,
      units: { complete: 2, total: 3 },
      state: STATION_STATES.PARTIAL,
    })
    expect(submissionProgressOf(form)).toEqual({ total: 6, complete: 2, remaining: 4 })
    const done = { ...form, missing: { ...FORM.missing, missingMainAttachments: [] } }
    expect(stationsOf(done)[0].state).toBe(STATION_STATES.COMPLETE)
    expect(submissionProgressOf(done)).toEqual({ total: 6, complete: 3, remaining: 3 })
  })

  it('treats a saved CV as started and reads one sub at a time', () => {
    const form = { ...FORM, evidence: [], cv: { 31: 'نص السيرة' } }
    expect(stationsOf(form)[2].state).toBe(STATION_STATES.PARTIAL)
    expect(isSubComplete(FORM, 11)).toBe(true)
    expect(isSubComplete(FORM, 22)).toBe(false)
  })

  it('keeps optional-only sub-criteria open in a draft until their station is passed', () => {
    const draft = { ...FORM, assessment: { status: 'draft' }, evidence: [], passedStations: [] }
    expect(stationsOf(draft)[0]).toMatchObject({ complete: 0, state: STATION_STATES.EMPTY })
    expect(isSubComplete(draft, 11)).toBe(false)
    const passed = { ...draft, passedStations: [1] }
    expect(stationsOf(passed)[0]).toMatchObject({ complete: 2, state: STATION_STATES.COMPLETE })
    expect(isSubComplete(passed, 11)).toBe(true)
    expect(isSubComplete({ ...passed, passedStations: [2] }, 22)).toBe(false)
  })

  it('counts an optional-only sub with its own evidence as complete before the station is passed', () => {
    const draft = {
      ...FORM,
      assessment: { status: 'draft' },
      evidence: [{ id: 'e2', subCriterionId: 11, scanStatus: 'clean' }],
      passedStations: [],
    }
    expect(stationsOf(draft)[0]).toMatchObject({ complete: 1, state: STATION_STATES.PARTIAL })
  })

  it('handles a form that is not loaded yet', () => {
    expect(stationsOf(null)).toEqual([])
    expect(submissionProgressOf(null)).toEqual({ total: 0, complete: 0, remaining: 0 })
  })

  it('sees no entity input on a fresh draft, even with every item missing', () => {
    expect(hasEntityInput({ ...FORM, evidence: [], cv: { 21: '', 31: '   ' } })).toBe(false)
    expect(hasEntityInput(null)).toBe(false)
  })

  it('sees entity input once a CV text is saved or any file is uploaded', () => {
    expect(hasEntityInput({ ...FORM, evidence: [], cv: { 21: 'نبذة' } })).toBe(true)
    expect(hasEntityInput({ ...FORM, evidence: [{ id: 'm1', mainCriterionId: 1 }] })).toBe(true)
  })
})
