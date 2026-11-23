import { describe, expect, it } from 'vitest'
import { BRAND_SURFACES, DEFAULT_BRAND_COLORS } from '@/constants/branding'
import { blend, mix } from '@/utils/brandColorMath'
import { autoRailColors, deriveRail, railGradient, railSuggestions } from '@/utils/brandRail'
import { deriveBrandScheme } from '@/utils/brandingContrast'
import { contrastRatio } from '@/utils/contrast'

// Every colour the rail text can sit on: each stop and the glow at the strength the rail uses.
function railFills(scheme) {
  return [...scheme.railStops, mix(scheme.primary, scheme.railBottom, scheme.railGlow / 100)]
}

function weakest(text, fills) {
  return Math.min(...fills.map((fill) => contrastRatio(text, fill)))
}

describe('OKLab blend', () => {
  it('keeps both ends exactly and matches the server blend', () => {
    expect(blend('#1d2b4f', '#3c6e71', 0)).toBe('#1d2b4f')
    expect(blend('#1d2b4f', '#3c6e71', 1)).toBe('#3c6e71')
    expect(blend('#0a2325', '#98d8d3', 0.26)).toBe('#2b4d4d')
  })

  it('keeps the middle of two saturated colours brighter than an sRGB mix', () => {
    const perceptual = blend('#0000ff', '#ffff00', 0.5)
    const plain = mix('#0000ff', '#ffff00', 0.5)
    expect(contrastRatio(perceptual, '#000000')).toBeGreaterThan(contrastRatio(plain, '#000000'))
  })
})

describe('sidebar rail', () => {
  it('follows the brand colours while no sidebar colour is chosen', () => {
    expect(autoRailColors(DEFAULT_BRAND_COLORS)).toEqual({ top: '#0a2325', bottom: '#2b4d4d' })
    const rail = deriveRail(DEFAULT_BRAND_COLORS)
    expect(rail.railStops).toHaveLength(9)
    expect(rail.railStops[0]).toBe('#0a2325')
    expect(rail.railStops[8]).toBe('#2b4d4d')
    expect(rail.onRail).toBe(BRAND_SURFACES.paper)
    expect(rail.railGlow).toBe(34)
  })

  it('picks dark text on a light pair and white text on a dark pair', () => {
    expect(deriveRail(DEFAULT_BRAND_COLORS, { top: '#f3f6f4', bottom: '#cfe7e4' }).onRail).toBe(
      BRAND_SURFACES.ink,
    )
    expect(deriveRail(DEFAULT_BRAND_COLORS, { top: '#1d2b4f', bottom: '#3c6e71' }).onRail).toBe(
      BRAND_SURFACES.paper,
    )
  })

  it.each([
    ['automatic', {}],
    ['navy to teal', { top: '#1d2b4f', bottom: '#3c6e71' }],
    ['light pair', { top: '#f3f6f4', bottom: '#cfe7e4' }],
  ])('picks default text, muted text and accent that read on every stop: %s', (label, chosen) => {
    const scheme = deriveBrandScheme(DEFAULT_BRAND_COLORS, {}, chosen)
    const fills = railFills(scheme)
    expect(weakest(scheme.onRail, fills)).toBeGreaterThanOrEqual(4.5)
    expect(weakest(scheme.onRailMuted, fills)).toBeGreaterThanOrEqual(4.5)
    expect(weakest(scheme.railAccent, fills)).toBeGreaterThanOrEqual(4.5)
  })

  it('still derives a rail for any two colours, with no readable text guaranteed', () => {
    const scheme = deriveBrandScheme(
      DEFAULT_BRAND_COLORS,
      {},
      { top: '#2b1055', bottom: '#9fb8ec' },
    )
    expect(scheme.onRail).toBe(BRAND_SURFACES.paper)
    expect(scheme.railGlow).toBe(0)
    expect(weakest(scheme.onRail, scheme.railStops)).toBeLessThan(4.5)
  })

  it('treats a malformed sidebar colour as automatic in the preview', () => {
    const scheme = deriveBrandScheme(DEFAULT_BRAND_COLORS, {}, { top: 'navy', bottom: '#12' })
    expect(scheme.railTop).toBe('#0a2325')
    expect(scheme.railBottom).toBe('#2b4d4d')
  })

  it('writes the stops as an even top-to-bottom gradient', () => {
    expect(railGradient(['#000000', '#808080', '#ffffff'])).toBe(
      'linear-gradient(180deg, #000000 0%, #808080 50%, #ffffff 100%)',
    )
  })

  it('offers only suggestions whose text reads, starting with automatic', () => {
    const ideas = railSuggestions(DEFAULT_BRAND_COLORS)
    expect(ideas[0]).toEqual({ id: 'auto', top: null, bottom: null })
    ideas.forEach((idea) => {
      const rail = deriveRail(DEFAULT_BRAND_COLORS, idea)
      expect(weakest(rail.onRail, rail.railStops)).toBeGreaterThanOrEqual(4.5)
    })
  })
})
