import { describe, expect, it } from 'vitest'
import { currentSegmentKey, levelAxis, splitSegment, trackSegments } from '@/utils/levelTrackAxis'
import { cubicBezier } from '@/utils/cubicBezier'

const RANGES = [
  { level: 'bronze', min: 60 },
  { level: 'silver', min: 70 },
  { level: 'gold', min: 80 },
  { level: 'diamond', min: 90 },
]

describe('levelAxis', () => {
  it('gives 12% to the stub below the first threshold and 88% to the levels', () => {
    const axis = levelAxis(RANGES)
    expect(axis(0)).toBe(0)
    expect(axis(30)).toBe(6)
    expect(axis(60)).toBe(12)
    expect(axis(80)).toBe(56)
    expect(axis(100)).toBe(100)
    expect(axis(120)).toBe(100)
  })

  it('falls back to a linear axis without thresholds', () => {
    expect(levelAxis([])(42)).toBe(42)
  })
})

describe('track segments', () => {
  const segments = trackSegments(RANGES)

  it('adds the stub and one segment per level up to 100', () => {
    expect(segments.map((segment) => [segment.key, segment.from, segment.to])).toEqual([
      ['below', 0, 60],
      ['bronze', 60, 70],
      ['silver', 70, 80],
      ['gold', 80, 90],
      ['diamond', 90, 100],
    ])
  })

  it('splits the current segment into reached and remaining parts', () => {
    expect(splitSegment(segments[2], 74.5, false)).toEqual({
      reached: [70, 74.5],
      ahead: null,
      remaining: [74.5, 80],
    })
    expect(splitSegment(segments[1], 74.5, false).reached).toEqual([60, 70])
    expect(splitSegment(segments[3], 74.5, false).ahead).toEqual([80, 90])
  })

  it('leaves no remaining zone on the last segment', () => {
    expect(splitSegment(segments[4], 93.1, true)).toEqual({
      reached: [90, 93.1],
      ahead: [93.1, 100],
      remaining: null,
    })
  })

  it('names the segment holding the score', () => {
    expect(currentSegmentKey(segments, '74.50')).toBe('silver')
    expect(currentSegmentKey(segments, 54)).toBe('below')
    expect(currentSegmentKey(segments, null)).toBeNull()
  })
})

describe('cubicBezier', () => {
  it('follows the brand curve front-loaded with no overshoot', () => {
    const ease = cubicBezier([0.2, 0.8, 0.2, 1])
    expect(ease(0)).toBe(0)
    expect(ease(1)).toBe(1)
    expect(ease(0.5)).toBeGreaterThan(0.85)
    expect(ease(0.9)).toBeLessThanOrEqual(1)
  })
})
