import { describe, expect, it } from 'vitest'
import { barLayout, columnPath, labelEvery, niceScale, plotFrame, valueY } from '../scale'
import { gaugeArc, ringSlices } from '../arcs'
import { deltaOf } from '../delta'
import { bucketLabel, longDayLabel } from '../bucketLabels'

describe('chart scales', () => {
  it('rounds the axis to clean ticks that cover the largest value', () => {
    expect(niceScale(37)).toEqual({ max: 40, ticks: [0, 10, 20, 30, 40] })
    expect(niceScale(28750)).toEqual({ max: 30000, ticks: [0, 10000, 20000, 30000] })
    expect(niceScale(0).ticks).toEqual([0, 1, 2, 3, 4])
  })

  it('thins time labels so they never collide, and keeps the plot inside its frame', () => {
    expect(labelEvery(31, 720)).toBe(4)
    expect(labelEvery(3, 720)).toBe(1)
    const frame = plotFrame(600, 240)
    expect(frame.innerWidth).toBe(600 - frame.left - frame.right)
    expect(valueY(10, 40, 200)).toBe(150)
    expect(valueY(null, 40, 200)).toBe(200)
  })

  it('caps columns at 24px with a 2px gap and rounds only their end', () => {
    expect(barLayout(200, 2)).toEqual({ width: 24, gap: 2, group: 50 })
    expect(barLayout(20, 2).width).toBeLessThan(24)
    expect(columnPath(10, 50, 24, 50)).toBe('')
    expect(columnPath(10, 20, 24, 100)).toMatch(
      /^M10,100 V24 Q10,20 14,20 H30 Q34,20 34,24 V100 Z$/,
    )
  })
})

describe('chart arcs', () => {
  it('splits a ring by share and draws nothing for a zero value', () => {
    const slices = ringSlices([3, 0, 1])
    expect(slices.map((slice) => slice.share)).toEqual([0.75, 0, 0.25])
    expect(slices[1].from).toBe(slices[1].to)
    expect(ringSlices([0, 0]).every((slice) => slice.share === 0)).toBe(true)
  })

  it('fills a gauge from the left end, clamped to the half ring', () => {
    const arc = { cx: 100, cy: 100, radius: 80 }
    expect(gaugeArc(arc, 0)).toBe('M20,100 A80,80 0 0 1 20,100')
    expect(gaugeArc(arc, 2)).toMatch(/A80,80 0 0 1 180,100/)
  })
})

describe('period deltas', () => {
  it('compares counts by their relative change and percents in points', () => {
    expect(deltaOf(40, 33)).toMatchObject({ direction: 'up', points: null })
    expect(deltaOf(40, 33).ratio).toBeCloseTo(21.21, 1)
    expect(deltaOf('79.00', '76.00', { inPoints: true })).toEqual({
      direction: 'up',
      points: 3,
      ratio: null,
    })
    expect(deltaOf(5, 5)).toMatchObject({ direction: 'flat' })
    expect(deltaOf(3, 0)).toEqual({ direction: 'up', points: null, ratio: null })
    expect(deltaOf(null, 3)).toBeNull()
    expect(deltaOf('28750.00', '29900.00').direction).toBe('down')
  })
})

describe('bucket labels', () => {
  it('names days, weeks and months in the active language with Western digits', () => {
    expect(bucketLabel('2026-11-17', 'day', 'en')).toBe('Nov 17')
    expect(bucketLabel('2026-11-01', 'month', 'en')).toBe('Nov 2026')
    expect(bucketLabel('2026-11-17', 'day', 'ar')).toMatch(/17/)
    expect(longDayLabel('2026-11-01', 'en')).toBe('November 1, 2026')
    expect(bucketLabel('', 'day', 'en')).toBe('')
  })
})
