import { describe, expect, it } from 'vitest'
import {
  evidenceRowsOf,
  isUnitScored,
  scoredCount,
  subEvidenceOf,
  subUnits,
  unitOf,
} from '@/utils/assessments/reviewRows'

const SUB_WITH_POINTS = {
  id: 21,
  evaluationType: 'guiding-points',
  attachments: [{ key: 'plan', name: { en: 'Plan' }, required: true }],
  points: [
    { id: 31, attachments: [{ key: 'minutes', name: { en: 'Minutes' }, required: true }] },
    { id: 32, attachments: [] },
  ],
}

const file = (id, attachmentKey) => ({ id, attachmentKey, fileName: `${id}.pdf` })

describe('reviewRows', () => {
  it('scores a sub-criterion through its indicators, or directly when it has none', () => {
    expect(subUnits(SUB_WITH_POINTS)).toEqual([
      { level: 'point', id: 31 },
      { level: 'point', id: 32 },
    ])
    expect(subUnits({ id: 22, evaluationType: 'direct', points: [] })).toEqual([
      { level: 'sub', id: 22 },
    ])
    // A directly evaluated sub-criterion stays one unit even with descriptive indicators.
    expect(subUnits({ ...SUB_WITH_POINTS, id: 23, evaluationType: 'direct' })).toEqual([
      { level: 'sub', id: 23 },
    ])
  })

  it('counts the units that hold a value, saved or not', () => {
    const units = subUnits(SUB_WITH_POINTS)
    const values = { 'point-31': '8.5', 'point-32': '' }
    expect(isUnitScored(values, unitOf('point', { id: 31 }))).toBe(true)
    expect(scoredCount(values, units)).toBe(1)
    expect(scoredCount({}, units)).toBe(0)
  })

  it('keeps a requirement row when it has files or is required, and marks a late one', () => {
    const rows = evidenceRowsOf(
      [
        { key: 'plan', name: { en: 'Plan' }, required: true },
        { key: 'extra', name: { en: 'Extra' }, required: false },
        { key: 'cv', name: { en: 'CV' }, required: false },
      ],
      [file(1, 'plan'), file(2, 'cv')],
      ['plan'],
    )
    expect(rows.map((row) => [row.key, row.files.length, row.late])).toEqual([
      ['plan', 1, true],
      ['cv', 1, false],
    ])
  })

  it("puts each indicator's evidence under it and keeps unmatched files visible", () => {
    const evidence = subEvidenceOf(SUB_WITH_POINTS, [
      file(1, 'plan'),
      file(2, 'minutes'),
      file(3, 'unknown'),
    ])
    expect(evidence.own.map((row) => row.key)).toEqual(['plan'])
    expect(evidence.byPoint.get(31).map((row) => row.files.length)).toEqual([1])
    expect(evidence.byPoint.has(32)).toBe(false)
    expect(evidence.others.map((row) => row.id)).toEqual([3])
  })
})
