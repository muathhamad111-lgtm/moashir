import { describe, expect, it } from 'vitest'
import { levelOfScore, levelRanges } from '@/utils/levelRanges'

const LEVELS = [
  { level: 'gold', label: { en: 'Gold' }, minPercent: '80.00' },
  { level: 'bronze', label: { en: 'Bronze' }, minPercent: '60.00' },
  { level: 'diamond', label: { en: 'Diamond' }, minPercent: '92.25' },
  { level: 'silver', label: { en: 'Silver' }, minPercent: '65.50' },
]

describe('levelRanges', () => {
  it('orders the levels and ends each range 0.01 below the next minimum', () => {
    expect(levelRanges(LEVELS)).toEqual([
      { level: 'bronze', label: { en: 'Bronze' }, min: 60, max: 65.49 },
      { level: 'silver', label: { en: 'Silver' }, min: 65.5, max: 79.99 },
      { level: 'gold', label: { en: 'Gold' }, min: 80, max: 92.24 },
      { level: 'diamond', label: { en: 'Diamond' }, min: 92.25, max: null },
    ])
  })

  it('ignores malformed entries', () => {
    expect(levelRanges([null, { level: 'gold' }, { minPercent: '10.00' }])).toEqual([])
    expect(levelRanges(undefined)).toEqual([])
  })
})

describe('levelOfScore', () => {
  const ranges = levelRanges(LEVELS)

  it('finds the level that contains the score', () => {
    expect(levelOfScore(ranges, 78).level).toBe('silver')
    expect(levelOfScore(ranges, 80).level).toBe('gold')
    expect(levelOfScore(ranges, 99).level).toBe('diamond')
  })

  it('returns null below the first level or for a non-numeric score', () => {
    expect(levelOfScore(ranges, 59.99)).toBeNull()
    expect(levelOfScore(ranges, 'n/a')).toBeNull()
  })
})
