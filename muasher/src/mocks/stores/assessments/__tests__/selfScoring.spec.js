import { describe, expect, it } from 'vitest'
import {
  acceptsRawHundredths,
  computeSelfResult,
  displayEarned,
  displayPercent,
  parseRawHundredths,
  rawPercent,
  scaleResource,
  unitKey,
} from '@/mocks/stores/assessments/selfScoring'

const tree = {
  totalWeight: '100.00',
  mains: [
    {
      id: 1,
      code: 'M1',
      weight: '100.00',
      subs: [
        {
          id: 11,
          code: 'M1.1',
          weight: '60.00',
          evaluationType: 'guiding-points',
          points: [
            { id: 111, code: 'M1.1.1', weight: '20.00' },
            { id: 112, code: 'M1.1.2', weight: '20.00' },
          ],
        },
        { id: 12, code: 'M1.2', weight: '40.00', evaluationType: 'direct', points: [] },
      ],
    },
  ],
}

describe('mock scoring engine (mirror of the backend fixed-point engine)', () => {
  it('parses raw input with at most two significant decimals', () => {
    expect(parseRawHundredths('7.5')).toBe(750)
    expect(parseRawHundredths('7.500')).toBe(750)
    expect(parseRawHundredths('7.505')).toBeNull()
    expect(parseRawHundredths('abc')).toBeNull()
  })

  it('normalizes raw values to percents for both scales', () => {
    expect(displayPercent(rawPercent(750, 'of-10'))).toBe('75.00')
    expect(displayPercent(rawPercent(3333, 'relative'))).toBe('33.33')
  })

  it('rounds the display half up once, as the API shows it', () => {
    expect(displayPercent(874450)).toBe('87.45')
    expect(displayPercent(874449)).toBe('87.44')
    expect(displayEarned(4_544_500_000)).toBe('45.45')
  })

  it('normalizes guiding points by their own weights and sums subs into mains', () => {
    const sheet = new Map([
      [unitKey('point', 111), 800],
      [unitKey('point', 112), 600],
      [unitKey('sub', 12), 500],
    ])
    const result = computeSelfResult(tree, sheet, 'of-10')
    expect(displayEarned(result.mains[0].subs[0].earned)).toBe('42.00')
    expect(displayEarned(result.mains[0].subs[1].earned)).toBe('20.00')
    expect(displayEarned(result.total)).toBe('62.00')
    expect(displayPercent(result.percent)).toBe('62.00')
  })

  it('counts unscored items as zero', () => {
    const result = computeSelfResult(tree, new Map(), 'of-10')
    expect(result.total).toBe(0)
    expect(result.mains[0].subs[1].raw).toBeNull()
  })
})

const typedTree = {
  ...tree,
  mains: [
    {
      ...tree.mains[0],
      subs: [tree.mains[0].subs[0], { ...tree.mains[0].subs[1], scoringType: 'achievement' }],
    },
  ],
}

describe('mock scoring engine without a scale stamp (mirror of ScoringType per unit)', () => {
  const achievement = { level: 'sub', id: 12, scoringType: 'achievement' }
  const score = { level: 'point', id: 111, scoringType: 'score' }

  it('serializes no scale for a request without a stamp, as the API does', () => {
    expect(scaleResource(null)).toBeNull()
    expect(scaleResource('of-10')).toEqual({ kind: 'of_10', maximum: '10.00' })
  })

  it('accepts only 0 or 1 for an achievement item and up to 10 for a score item', () => {
    expect(acceptsRawHundredths(100, achievement, null)).toBe(true)
    expect(acceptsRawHundredths(0, achievement, null)).toBe(true)
    expect(acceptsRawHundredths(50, achievement, null)).toBe(false)
    expect(acceptsRawHundredths(800, achievement, null)).toBe(false)
    expect(acceptsRawHundredths(1000, score, null)).toBe(true)
    expect(acceptsRawHundredths(1001, score, null)).toBe(false)
  })

  it('lets the stamp type every item, so an achievement node takes a grade under of-10', () => {
    expect(acceptsRawHundredths(800, achievement, 'of-10')).toBe(true)
    expect(acceptsRawHundredths(1001, achievement, 'of-10')).toBe(false)
  })

  it('weighs an achieved item as a full score', () => {
    const sheet = new Map([
      [unitKey('point', 111), 800],
      [unitKey('point', 112), 600],
      [unitKey('sub', 12), 100],
    ])
    const result = computeSelfResult(typedTree, sheet, null)
    expect(result.mains[0].subs[1].scoringType).toBe('achievement')
    expect(displayEarned(result.mains[0].subs[1].earned)).toBe('40.00')
    expect(displayEarned(result.total)).toBe('82.00')
  })
})
