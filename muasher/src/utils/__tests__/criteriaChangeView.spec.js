import { describe, expect, it } from 'vitest'
import {
  CHANGE_TAGS,
  countByTile,
  filterChanges,
  groupByMain,
  isTextOnly,
  mainCodeOf,
  tagsOf,
  tileOf,
} from '@/utils/criteriaChangeView'

function change(overrides = {}) {
  return {
    nodeType: 'sub',
    code: 'M1.1',
    change: 'modified',
    fields: ['weight'],
    before: {},
    after: {},
    scoreAffecting: true,
    label: null,
    ...overrides,
  }
}

describe('criteriaChangeView', () => {
  it('finds the main criterion of any item, and none for the version itself', () => {
    expect(mainCodeOf(change({ code: 'M2.3.1', nodeType: 'point' }))).toBe('M2')
    expect(mainCodeOf(change({ code: 'M10', nodeType: 'main' }))).toBe('M10')
    expect(mainCodeOf(change({ code: 'v2', nodeType: 'version' }))).toBe('')
  })

  it('tags what a change does to the scores of open requests', () => {
    expect(tagsOf(change({ change: 'added' }))).toEqual([CHANGE_TAGS.ADDED])
    expect(tagsOf(change({ change: 'removed' }))).toEqual([CHANGE_TAGS.REMOVED])
    expect(tagsOf(change())).toEqual([CHANGE_TAGS.NEEDS_NEW_SCORE])
    expect(tagsOf(change({ fields: ['name'], scoreAffecting: false }))).toEqual([
      CHANGE_TAGS.KEEPS_SCORE,
      CHANGE_TAGS.TEXT_ONLY,
    ])
    expect(tagsOf(change({ fields: ['has_cv'], scoreAffecting: false }))).toEqual([
      CHANGE_TAGS.KEEPS_SCORE,
    ])
  })

  it('counts a text correction and a names-only change as text', () => {
    expect(isTextOnly(change({ change: 'text_corrected', fields: [] }))).toBe(true)
    expect(isTextOnly(change({ fields: ['name', 'description'] }))).toBe(true)
    expect(isTextOnly(change({ fields: ['name', 'weight'] }))).toBe(false)
    expect(isTextOnly(change({ fields: [] }))).toBe(false)
  })

  it('groups the changes by main criterion in code order, named by the change or the tree', () => {
    const groups = groupByMain(
      [
        change({ code: 'M10.1' }),
        change({ code: 'M2.1.2', nodeType: 'point' }),
        change({ code: 'M2', nodeType: 'main', label: { ar: 'التخطيط', en: 'Planning' } }),
        change({ code: 'M2.1.1', nodeType: 'point' }),
        change({ code: 'v3', nodeType: 'version', fields: ['label'] }),
      ],
      new Map([['M10', { ar: 'النتائج', en: 'Results' }]]),
    )
    expect(groups.map((group) => group.code)).toEqual(['', 'M2', 'M10'])
    expect(groups[1].label).toEqual({ ar: 'التخطيط', en: 'Planning' })
    expect(groups[1].changes.map((item) => item.code)).toEqual(['M2', 'M2.1.1', 'M2.1.2'])
    expect(groups[2].label).toEqual({ ar: 'النتائج', en: 'Results' })
  })

  it('sorts each change into one tile; a removed item needs new scores too', () => {
    expect(tileOf(change({ change: 'added' }))).toBe('added')
    expect(tileOf(change({ change: 'removed' }))).toBe('needsNewScore')
    expect(tileOf(change())).toBe('needsNewScore')
    expect(tileOf(change({ fields: ['name'], scoreAffecting: false }))).toBe('keepsScore')
    expect(
      countByTile([change(), change({ change: 'added' }), change({ change: 'removed' })]),
    ).toEqual({ keepsScore: 0, needsNewScore: 2, added: 1 })
  })

  it('filters by tile and by a search on the code or the name', () => {
    const changes = [
      change({ code: 'M1.1', label: { en: 'Planning' } }),
      change({ code: 'M1.2', change: 'added', label: { en: 'Risk management' } }),
    ]
    const textOf = (item) => `${item.code} ${item.label.en}`
    expect(filterChanges(changes, { tile: 'added', textOf }).map((item) => item.code)).toEqual([
      'M1.2',
    ])
    expect(filterChanges(changes, { query: ' plan ', textOf }).map((item) => item.code)).toEqual([
      'M1.1',
    ])
    expect(filterChanges(changes, { query: 'm1.2', textOf })).toHaveLength(1)
    expect(filterChanges(changes, { textOf })).toHaveLength(2)
  })
})
