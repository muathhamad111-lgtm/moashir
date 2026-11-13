import { describe, expect, it } from 'vitest'
import { fillStartOf, levelBarRows } from '@/utils/dashboard/levelBars'

describe('levelBarRows', () => {
  it('keeps the fixed order none → in progress → participation → bronze … diamond', () => {
    const rows = levelBarRows([
      { key: 'diamond', count: 1 },
      { key: 'none', count: 4 },
      { key: 'gold', count: 2 },
    ])
    expect(rows.map((row) => row.key)).toEqual([
      'none',
      'in_progress',
      'participation',
      'bronze',
      'silver',
      'gold',
      'diamond',
    ])
    expect(rows.map((row) => row.tier)).toEqual([
      'none',
      'progress',
      'participation',
      'bronze',
      'silver',
      'gold',
      'diamond',
    ])
  })

  it('keeps zero counts and scales against the largest bucket', () => {
    const rows = levelBarRows([
      { key: 'none', count: 4 },
      { key: 'gold', count: 2 },
    ])
    expect(rows.find((row) => row.key === 'bronze')).toMatchObject({ count: 0, ratio: 0 })
    expect(rows.find((row) => row.key === 'none').ratio).toBe(100)
    expect(rows.find((row) => row.key === 'gold').ratio).toBe(50)
  })

  it('lets every bucket open the filtered list except in progress', () => {
    const filterable = levelBarRows([]).filter((row) => row.filterable)
    expect(filterable.map((row) => row.key)).not.toContain('in_progress')
    expect(filterable).toHaveLength(6)
  })

  it('renders an all-zero chart for empty or malformed data', () => {
    expect(levelBarRows(null).every((row) => row.count === 0 && row.ratio === 0)).toBe(true)
  })
})

describe('fillStartOf', () => {
  it('starts the fill at the inline-start edge in both directions', () => {
    expect(fillStartOf(50, false)).toBe('0')
    expect(fillStartOf(50, true)).toBe('50%')
    expect(fillStartOf(100, true)).toBe('0%')
    expect(fillStartOf(0, true)).toBe('100%')
  })
})
