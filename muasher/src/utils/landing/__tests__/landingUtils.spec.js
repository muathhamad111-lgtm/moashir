import { describe, expect, it } from 'vitest'
import { clamp, easeInOut, easeInOutQuad, easeOut, segment } from '../easing'
import { landingTiers, PARTICIPATION, tierOfScore, tierSample } from '../tiers'
import { dotOffset, journeyPath } from '../journeyPath'
import { lensArcs, lensRotation, lensTicks } from '../lensRing'
import { QR_SIZE, qrPatternPath } from '../qrPattern'
import { keyWordSet, manifestoWords } from '../manifesto'
import { anchorOf, linkKind } from '../links'
import { cellHash, copyShield, paletteFor, sparkCells } from '../dotField'

const RANGES = [
  { level: 'bronze', label: { ar: 'برونزي', en: 'Bronze' }, min: 60, max: 69 },
  { level: 'silver', label: { ar: 'فضي', en: 'Silver' }, min: 70, max: 79 },
  { level: 'gold', label: { ar: 'ذهبي', en: 'Gold' }, min: 80, max: 89 },
  { level: 'diamond', label: { ar: 'ماسي', en: 'Diamond' }, min: 90, max: null },
]

describe('easing', () => {
  it('clamps and segments progress', () => {
    expect(clamp(-1)).toBe(0)
    expect(clamp(2)).toBe(1)
    expect(clamp(5, 0, 10)).toBe(5)
    expect(segment(0.5, 0, 1)).toBe(0.5)
    expect(segment(0.05, 0.1, 0.2)).toBe(0)
    expect(segment(0.3, 0.1, 0.2)).toBe(1)
  })

  it('keeps every curve inside 0..1 with fixed ends', () => {
    for (const curve of [easeOut, easeInOut, easeInOutQuad]) {
      expect(curve(0)).toBe(0)
      expect(curve(1)).toBe(1)
      expect(curve(0.5)).toBeGreaterThan(0)
      expect(curve(0.5)).toBeLessThan(1)
    }
  })
})

describe('tiers', () => {
  const tiers = landingTiers(RANGES)

  it('adds a participation tier below the first level and keeps the configured thresholds', () => {
    expect(tiers.map((tier) => tier.key)).toEqual([
      PARTICIPATION,
      'bronze',
      'silver',
      'gold',
      'diamond',
    ])
    expect(tiers[0]).toMatchObject({ min: 0, max: 60 })
    expect(tiers[3]).toMatchObject({ min: 80, max: 90 })
    expect(tiers[4].max).toBeNull()
    tiers.forEach((tier) => expect(tier.seal).toBeTruthy())
  })

  it('follows thresholds changed in the certificate settings', () => {
    const moved = landingTiers(
      RANGES.map((range) => (range.level === 'gold' ? { ...range, min: 85 } : range)),
    )
    expect(tierOfScore(moved, 84).key).toBe('silver')
    expect(tierOfScore(moved, 85).key).toBe('gold')
  })

  it('maps a score to its tier at every boundary', () => {
    expect(tierOfScore(tiers, 0).key).toBe(PARTICIPATION)
    expect(tierOfScore(tiers, 59).key).toBe(PARTICIPATION)
    expect(tierOfScore(tiers, 60).key).toBe('bronze')
    expect(tierOfScore(tiers, 89).key).toBe('gold')
    expect(tierOfScore(tiers, 100).key).toBe('diamond')
  })

  it('picks a sample score inside each tier', () => {
    tiers.forEach((tier) => expect(tierOfScore(tiers, tierSample(tier)).key).toBe(tier.key))
  })

  it('returns nothing without configured levels', () => {
    expect(landingTiers([])).toEqual([])
    expect(landingTiers(null)).toEqual([])
    expect(tierOfScore([], 50)).toBeNull()
  })
})

describe('journey path', () => {
  const frame = { left: 0, width: 1000 }

  it('places the dot after the text in RTL and before it in LTR', () => {
    const text = { left: 600, right: 900 }
    expect(dotOffset(text, frame, true)).toBe(938)
    expect(dotOffset(text, frame, false)).toBe(562)
    expect(dotOffset({ left: 0, right: 990 }, frame, true)).toBe(986)
    expect(dotOffset({ left: 10, right: 50 }, frame, false)).toBe(14)
  })

  it('draws a straight line when every row shares the same column', () => {
    const rows = [
      { x: 40, top: 0, bottom: 200, middle: 100 },
      { x: 40, top: 300, bottom: 500, middle: 400 },
    ]
    const path = journeyPath(rows)
    expect(path.d).toBe('M 40 6 L 40 494')
    expect(path.length).toBe(488)
    expect(path.stops).toEqual([94, 394])
  })

  it('turns with arcs between zig-zag rows and orders the stops along the line', () => {
    const rows = [
      { x: 900, top: 0, bottom: 200, middle: 100 },
      { x: 300, top: 300, bottom: 500, middle: 400 },
      { x: 900, top: 600, bottom: 800, middle: 700 },
    ]
    const path = journeyPath(rows)
    expect(path.d.match(/A /g)).toHaveLength(4)
    expect(path.stops).toHaveLength(3)
    expect(path.stops[0]).toBeLessThan(path.stops[1])
    expect(path.stops[1]).toBeLessThan(path.stops[2])
    expect(path.length).toBeGreaterThan(path.stops[2])
  })

  it('returns an empty path without rows', () => {
    expect(journeyPath([])).toEqual({ d: '', length: 1, stops: [] })
  })
})

describe('lens ring', () => {
  it('draws 120 ticks with a major tick every six', () => {
    const ticks = lensTicks()
    expect(ticks).toHaveLength(120)
    expect(ticks.filter((tick) => tick.major)).toHaveLength(20)
  })

  it('splits the ring into one arc per lens and turns the active one to the top', () => {
    expect(lensArcs(5)).toHaveLength(5)
    expect(lensArcs(0)).toEqual([])
    expect(lensRotation(0, 5)).toBe(-36)
    expect(lensRotation(2, 5)).toBe(-180)
    expect(lensRotation(0, 0)).toBe(0)
  })
})

describe('qr pattern', () => {
  it('is deterministic and keeps the three finder squares', () => {
    const path = qrPatternPath()
    expect(path).toBe(qrPatternPath())
    expect(QR_SIZE).toBe(29)
    expect(path).toContain('M0 0h1v1h-1z')
    expect(path).toContain(`M${QR_SIZE - 1} 0h1v1h-1z`)
    expect(path).toContain(`M0 ${QR_SIZE - 1}h1v1h-1z`)
  })
})

describe('manifesto', () => {
  it('reads key words separated by Arabic or Latin commas', () => {
    expect([...keyWordSet('نضج، وتميز، أثر')]).toEqual(['نضج', 'وتميز', 'أثر'])
    expect([...keyWordSet('maturity, excellence')]).toEqual(['maturity', 'excellence'])
    expect(keyWordSet('').size).toBe(0)
  })

  it('marks key words while ignoring punctuation', () => {
    const words = manifestoWords('أثر أعمق ومستدام.', 'أعمق، ومستدام')
    expect(words.map((entry) => entry.isKey)).toEqual([false, true, true])
    expect(words[2].word).toBe('ومستدام.')
  })
})

describe('links', () => {
  it('classifies every allowed link form', () => {
    expect(linkKind('#paths')).toBe('anchor')
    expect(linkKind('/#contact')).toBe('anchor')
    expect(linkKind('/register')).toBe('route')
    expect(linkKind('https://mytts.sa')).toBe('external')
    expect(linkKind('mailto:team@example.sa')).toBe('mail')
    expect(linkKind('javascript:alert(1)')).toBe('none')
    expect(anchorOf('/#levels')).toBe('levels')
    expect(anchorOf('/kpis')).toBe('')
  })
})

describe('dot field', () => {
  it('hashes cells into 0..1 and seeds a sparse set of sparks', () => {
    const value = cellHash(3, 7)
    expect(value).toBeGreaterThanOrEqual(0)
    expect(value).toBeLessThan(1)
    const sparks = sparkCells(800, 600)
    expect(sparks.length).toBeGreaterThan(0)
    expect(sparks.length).toBeLessThan((800 / 16) * (600 / 16) * 0.1)
  })

  it('dims dots behind the copy only while the copy is visible', () => {
    const box = { x0: 100, x1: 300, y0: 100, y1: 200 }
    expect(copyShield(box, 1)(200, 150)).toBeCloseTo(0.15)
    expect(copyShield(box, 1)(800, 800)).toBe(1)
    expect(copyShield(box, 0)(200, 150)).toBe(1)
  })

  it('uses a distinct palette per theme', () => {
    expect(paletteFor(true)).not.toEqual(paletteFor(false))
  })
})
