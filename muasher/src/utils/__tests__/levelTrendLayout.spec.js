import { describe, expect, it } from 'vitest'
import { LEVEL_TREND_SIZE, levelTrendLayout, trendScaleMin } from '@/utils/levelTrendLayout'

const RANGES = [
  { level: 'bronze', min: 60 },
  { level: 'silver', min: 70 },
  { level: 'gold', min: 80 },
  { level: 'diamond', min: 90 },
]
const SERIES = [
  { id: 'a', percent: '57.00', completedAt: '2025-10-15T08:00:00Z' },
  { id: 'b', percent: '77.20', completedAt: '2026-09-20T08:00:00Z' },
]

describe('trendScaleMin', () => {
  it('starts the scale one step below the lowest of the results and the first threshold', () => {
    expect(trendScaleMin(['57.00', '77.20'], 60)).toBe(50)
    expect(trendScaleMin(['81', '86'], 60)).toBe(50)
    expect(trendScaleMin(['12'], 60)).toBe(0)
  })
})

describe('levelTrendLayout', () => {
  const ltr = levelTrendLayout({ series: SERIES, ranges: RANGES, width: 900, isRtl: false })
  const rtl = levelTrendLayout({ series: SERIES, ranges: RANGES, width: 900, isRtl: true })

  it('draws at the measured width with a fixed height', () => {
    expect(ltr.width).toBe(900)
    expect(ltr.height).toBe(LEVEL_TREND_SIZE.height)
  })

  it('keeps every point inside the plot, away from its edges', () => {
    for (const layout of [ltr, rtl]) {
      for (const point of layout.points) {
        expect(point.x).toBeGreaterThanOrEqual(layout.plot.x + LEVEL_TREND_SIZE.inset)
        expect(point.x).toBeLessThanOrEqual(
          layout.plot.x + layout.plot.width - LEVEL_TREND_SIZE.inset,
        )
        expect(point.y).toBeLessThanOrEqual(layout.floorY)
      }
    }
  })

  it('runs time from the reading start: oldest at the left in LTR and at the right in RTL', () => {
    expect(ltr.points[0].x).toBeLessThan(ltr.points[1].x)
    expect(rtl.points[0].x).toBeGreaterThan(rtl.points[1].x)
    expect(rtl.points[1].isLatest).toBe(true)
  })

  it('puts the band labels in the column at the reading start', () => {
    expect(ltr.labelX).toBeLessThan(ltr.plot.x)
    expect(rtl.labelX).toBeGreaterThan(rtl.plot.x + rtl.plot.width)
  })

  it('stacks the bands from the first threshold up to 100 without gaps', () => {
    const [bronze, silver] = ltr.bands
    expect(silver.y + silver.height).toBeCloseTo(bronze.y)
    expect(ltr.bands.at(-1).y).toBe(ltr.plot.top)
  })

  it('moves a value label under its point when the point is at the top', () => {
    const top = levelTrendLayout({
      series: [{ id: 'x', percent: 100, completedAt: '2026-01-01T00:00:00Z' }],
      ranges: RANGES,
      width: 600,
      isRtl: false,
    }).points[0]
    expect(top.valueY).toBeGreaterThan(top.y)
  })
})
