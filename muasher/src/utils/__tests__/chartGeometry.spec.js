import { describe, expect, it } from 'vitest'
import { axisPercent, deltaOf, scaleY, spanStart, timelineX } from '@/utils/chartGeometry'
import { criteriaComparison, rankedCriteria, resultSeries } from '@/utils/accreditedPerformance'
import { isTopLevel, nextLevelOf, remainingTo } from '@/utils/levelProgress'
import { splitLabel } from '@/utils/chartLabels'
import { roundToOneDecimal, scorePercentText } from '@/utils/percentText'

const RANGES = [
  { level: 'bronze', min: 60 },
  { level: 'silver', min: 70 },
  { level: 'gold', min: 80 },
  { level: 'diamond', min: 90 },
]

describe('chart geometry', () => {
  it('mirrors positions on the axis in RTL', () => {
    expect(axisPercent(82, false)).toBe(82)
    expect(axisPercent(82, true)).toBe(18)
    expect(spanStart(60, 70, true)).toBe(30)
    expect(axisPercent(140, false)).toBe(100)
  })

  it('places the oldest point at the start side', () => {
    expect(timelineX(0, 3, false)).toBe(0)
    expect(timelineX(0, 3, true)).toBe(100)
    expect(timelineX(0, 1, false)).toBe(50)
  })

  it('scales values from the bottom of the plot', () => {
    expect(scaleY(100, { top: 0, height: 100 })).toBe(0)
    expect(scaleY(70, { top: 0, height: 100, min: 40 })).toBe(50)
  })

  it('rounds deltas to one decimal and needs both values', () => {
    expect(deltaOf('82.00', '74.00')).toBe(8)
    expect(deltaOf(76.2, 78)).toBe(-1.8)
    expect(deltaOf(80, null)).toBeNull()
  })

  it('wraps long criterion names on two lines', () => {
    expect(splitLabel('القيادة')).toEqual(['القيادة'])
    expect(splitLabel('إدارة الموارد البشرية والمتطوعين')).toHaveLength(2)
  })
})

describe('accredited performance', () => {
  it('orders the series by completion date and skips missing results', () => {
    const series = resultSeries([
      { id: 2, result: { percent: '82.00' }, completedAt: '2026-10-28T08:00:00Z' },
      { id: 1, result: { percent: '74.00' }, completedAt: '2025-11-02T08:00:00Z' },
      { id: 3, result: null, completedAt: '2026-11-01T08:00:00Z' },
    ])
    expect(series.map((entry) => entry.id)).toEqual([1, 2])
  })

  it('compares criteria by id and ranks them high to low', () => {
    const compared = criteriaComparison(
      [
        { criterionId: 1, percent: '70' },
        { criterionId: 2, percent: '90' },
        { criterionId: 5, percent: '60' },
      ],
      [
        { criterionId: 1, percent: '72' },
        { criterionId: 2, percent: '85' },
      ],
    )
    expect(compared.map((item) => item.delta)).toEqual([-2, 5, null])
    expect(rankedCriteria(compared).map((item) => item.key)).toEqual(['2', '1', '5'])
  })
})

describe('level progress', () => {
  it('finds the next threshold and the remaining percent', () => {
    expect(nextLevelOf(RANGES, 82).level).toBe('diamond')
    expect(remainingTo(nextLevelOf(RANGES, 82), 82)).toBe(8)
    expect(nextLevelOf(RANGES, null)).toBeNull()
  })

  it('knows the top level', () => {
    expect(isTopLevel(RANGES, 93)).toBe(true)
    expect(isTopLevel(RANGES, 82)).toBe(false)
  })
})

describe('score percent text', () => {
  const t = (key, { value }) => `${value}%`
  const n = (value) => String(value)

  it('keeps one decimal when the score is not whole', () => {
    expect(roundToOneDecimal('74.50')).toBe(74.5)
    expect(roundToOneDecimal('57.03')).toBe(57)
    expect(scorePercentText(n, t, '74.5427')).toBe('74.5%')
    expect(scorePercentText(n, t, '82.00')).toBe('82%')
  })
})
