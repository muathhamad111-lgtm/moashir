import { describe, expect, it } from 'vitest'
import {
  clampRatio,
  valueToRatio,
  ratioToValue,
  ratioToPercent,
  computeResult,
} from '@/utils/assessmentScoring'

describe('assessmentScoring scale helpers', () => {
  it('clamps ratios into 0..1', () => {
    expect(clampRatio(-0.5)).toBe(0)
    expect(clampRatio(0.5)).toBe(0.5)
    expect(clampRatio(1.7)).toBe(1)
    expect(clampRatio(Number.NaN)).toBe(0)
  })

  it('converts an of-10 value to a ratio', () => {
    expect(valueToRatio(10, 'of-10')).toBe(1)
    expect(valueToRatio(7, 'of-10')).toBeCloseTo(0.7)
    expect(valueToRatio(15, 'of-10')).toBe(1)
  })

  it('converts a relative (percentage) value to a ratio', () => {
    expect(valueToRatio(100, 'relative')).toBe(1)
    expect(valueToRatio(45, 'relative')).toBeCloseTo(0.45)
  })

  it('round-trips a ratio back to a scaled value', () => {
    expect(ratioToValue(0.7, 'of-10')).toBeCloseTo(7)
    expect(ratioToValue(0.45, 'relative')).toBeCloseTo(45)
  })

  it('reflects a ratio as a whole percentage', () => {
    expect(ratioToPercent(0.7)).toBe(70)
    expect(ratioToPercent(0.855)).toBe(86)
  })
})

const tree = [
  {
    id: 1,
    weight: 1000,
    subs: [
      { id: 11, weight: 600, evaluationType: 'direct', points: [] },
      {
        id: 12,
        weight: 400,
        evaluationType: 'guiding-points',
        points: [
          { id: 121, weight: 200 },
          { id: 122, weight: 200 },
        ],
      },
    ],
  },
  {
    id: 2,
    weight: 500,
    subs: [{ id: 21, weight: 500, evaluationType: 'direct', points: [] }],
  },
]

describe('computeResult (weighted aggregation reconciling with criteria weights)', () => {
  it('gives a perfect score when everything is at ratio 1', () => {
    const result = computeResult(tree, {
      subs: { 11: 1, 21: 1 },
      points: { 121: 1, 122: 1 },
    })
    expect(result.total).toBe(1500)
    expect(result.maxTotal).toBe(1500)
    expect(result.percent).toBe(100)
  })

  it('gives zero for an empty score set', () => {
    const result = computeResult(tree, { subs: {}, points: {} })
    expect(result.total).toBe(0)
    expect(result.maxTotal).toBe(1500)
    expect(result.percent).toBe(0)
  })

  it('aggregates guiding points into their sub, and subs into their main', () => {
    const result = computeResult(tree, {
      subs: { 11: 0.5, 21: 0.8 },
      points: { 121: 1, 122: 0.5 },
    })
    const sub12 = result.sub.find((entry) => entry.subId === 12)
    expect(sub12.earned).toBe(300)
    expect(sub12.percent).toBe(75)
    const main1 = result.main.find((entry) => entry.criterionId === 1)
    expect(main1.earned).toBe(600)
    expect(main1.percent).toBe(60)
    const main2 = result.main.find((entry) => entry.criterionId === 2)
    expect(main2.earned).toBe(400)
    expect(main2.percent).toBe(80)
    expect(result.total).toBe(1000)
    expect(result.percent).toBe(67)
  })

  it('clamps out-of-range entered ratios', () => {
    const result = computeResult(tree, { subs: { 11: 2, 21: -1 }, points: {} })
    const sub11 = result.sub.find((entry) => entry.subId === 11)
    expect(sub11.earned).toBe(600)
    const sub21 = result.sub.find((entry) => entry.subId === 21)
    expect(sub21.earned).toBe(0)
  })
})
