import { describe, expect, it } from 'vitest'
import { ownNotesIndex, prefilledNote } from '@/utils/accredited/completionNotes'

const MAIN = { id: 1 }
const DIRECT = { id: 12, evaluationType: 'direct', points: [] }
const POINTS = {
  id: 11,
  evaluationType: 'guiding-points',
  points: [
    { id: 111, code: 'M1.1.1' },
    { id: 112, code: 'M1.1.2' },
  ],
}
const note = (level, ids, body, isMine = true) => ({
  level,
  mainCriterionId: 1,
  ...ids,
  body,
  isMine,
})
const index = ownNotesIndex([
  note('main', {}, 'Main note'),
  note('sub', { subCriterionId: 12 }, 'Direct sub note'),
  note('sub', { subCriterionId: 11 }, 'Points sub note'),
  note('point', { subCriterionId: 11, guidingPointId: 112 }, 'Second indicator'),
  note('sub', { subCriterionId: 12 }, 'Someone else', false),
])
const fill = (option, selectedKeys = []) => prefilledNote(option, { index, selectedKeys })

describe('completion notes from internal notes', () => {
  it('gives the CV item the sub-criterion note and the main attachments the main note', () => {
    expect(fill({ sub: DIRECT, kind: 'cv' })).toBe('Direct sub note')
    expect(fill({ main: MAIN, sub: null, kind: 'evidence' })).toBe('Main note')
  })

  it("gives the evidence item the indicators' notes, with the sub note unless its CV item is asked for too", () => {
    expect(fill({ sub: POINTS, kind: 'evidence' })).toBe(
      'Points sub note · M1.1.2 — Second indicator',
    )
    expect(fill({ sub: POINTS, kind: 'evidence' }, ['11:cv'])).toBe('M1.1.2 — Second indicator')
    expect(fill({ sub: DIRECT, kind: 'evidence' })).toBe('Direct sub note')
    expect(fill({ sub: DIRECT, kind: 'evidence' }, ['12:cv'])).toBe('')
  })

  it("ignores other people's notes and keeps within the item note limit", () => {
    expect(ownNotesIndex([note('sub', { subCriterionId: 12 }, 'x', false)]).sub.size).toBe(0)
    const long = ownNotesIndex([note('sub', { subCriterionId: 12 }, 'y'.repeat(900))])
    expect(
      prefilledNote({ sub: DIRECT, kind: 'cv' }, { index: long, selectedKeys: [] }),
    ).toHaveLength(500)
    expect(ownNotesIndex(null).main.size).toBe(0)
  })
})
