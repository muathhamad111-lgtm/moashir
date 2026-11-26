import { describe, expect, it } from 'vitest'
import {
  isAchieved,
  isNotAchieved,
  noteKey,
  noteUnit,
  notesByUnit,
} from '@/utils/assessments/reviewNotes'

const MAIN = { id: 11 }
const SUB = { id: 21 }
const POINT = { id: 31 }

describe('review notes helpers', () => {
  it('names the unit of a main criterion, a sub-criterion and an indicator', () => {
    expect(noteKey(noteUnit({ main: MAIN }))).toBe('main-11-0-0')
    expect(noteKey(noteUnit({ main: MAIN, sub: SUB }))).toBe('sub-11-21-0')
    expect(noteUnit({ main: MAIN, sub: SUB, point: POINT })).toEqual({
      level: 'point',
      mainCriterionId: 11,
      subCriterionId: 21,
      guidingPointId: 31,
    })
  })

  it("splits a unit's notes into the viewer's own and the others'", () => {
    const unit = noteUnit({ main: MAIN, sub: SUB })
    const byUnit = notesByUnit([
      { ...unit, id: 1, isMine: false, body: 'Follower' },
      { ...unit, id: 2, isMine: true, body: 'Mine' },
    ])
    const entry = byUnit.get(noteKey(unit))
    expect(entry.mine.body).toBe('Mine')
    expect(entry.others.map((note) => note.body)).toEqual(['Follower'])
  })

  it('reads «not achieved» only on an achievement item with a zero value', () => {
    expect(isNotAchieved('achievement', '0')).toBe(true)
    expect(isNotAchieved('achievement', '٠')).toBe(true)
    expect(isNotAchieved('achievement', '1')).toBe(false)
    expect(isNotAchieved('achievement', '')).toBe(false)
    expect(isNotAchieved('score', '0')).toBe(false)
    expect(isAchieved('achievement', '1')).toBe(true)
    expect(isAchieved('achievement', '')).toBe(false)
  })
})
