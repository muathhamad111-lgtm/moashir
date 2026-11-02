import { describe, expect, it } from 'vitest'
import { movedIds, weightChanges } from '@/utils/criteriaOrder'

describe('criteriaOrder', () => {
  it('builds the complete permutation after moving one item', () => {
    const items = [{ id: 1 }, { id: 2 }, { id: 3 }]
    expect(movedIds(items, 0, 1)).toEqual([2, 1, 3])
    expect(movedIds(items, 2, -1)).toEqual([1, 3, 2])
    expect(movedIds(items, 0, -1)).toBeNull()
    expect(movedIds(items, 2, 1)).toBeNull()
  })

  it('saves lowered weights before raised ones so the remaining-weight guard never trips midway', () => {
    const originals = [
      { id: 1, weight: '400.00' },
      { id: 2, weight: '350.00' },
      { id: 3, weight: '250.00' },
    ]
    const edited = [
      { id: 1, weight: '450' },
      { id: 2, weight: '300.00' },
      { id: 3, weight: '250.00' },
    ]
    expect(weightChanges(edited, originals)).toEqual([
      { id: 2, weight: '300.00' },
      { id: 1, weight: '450.00' },
    ])
  })

  it('ignores rows whose weight did not change numerically', () => {
    expect(weightChanges([{ id: 1, weight: '400' }], [{ id: 1, weight: '400.00' }])).toEqual([])
  })
})
