import { describe, expect, it } from 'vitest'
import {
  STATION_STATES,
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
      stationsOf(FORM).map(({ code, complete, total, state }) => [code, complete, total, state]),
    ).toEqual([
      ['M1', 2, 2, STATION_STATES.COMPLETE],
      ['M2', 0, 2, STATION_STATES.PARTIAL],
      ['M3', 0, 1, STATION_STATES.EMPTY],
    ])
    expect(submissionProgressOf(FORM)).toEqual({ total: 5, complete: 2, remaining: 3 })
  })

  it('treats a saved CV as started and reads one sub at a time', () => {
    const form = { ...FORM, evidence: [], cv: { 31: 'نص السيرة' } }
    expect(stationsOf(form)[2].state).toBe(STATION_STATES.PARTIAL)
    expect(isSubComplete(FORM, 11)).toBe(true)
    expect(isSubComplete(FORM, 22)).toBe(false)
  })

  it('handles a form that is not loaded yet', () => {
    expect(stationsOf(null)).toEqual([])
    expect(submissionProgressOf(null)).toEqual({ total: 0, complete: 0, remaining: 0 })
  })
})
