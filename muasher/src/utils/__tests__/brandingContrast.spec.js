import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'
import { describe, expect, it } from 'vitest'
import { BRAND_SURFACES, DEFAULT_BRAND_COLORS } from '@/constants/branding'
import { deriveBrandScheme, isBrandScheme, normalizeHex } from '@/utils/brandingContrast'
import { contrastRatio } from '@/utils/contrast'

// Produced by App\Services\Settings\BrandColorDeriver; the preview must derive the same schemes.
const VECTORS = JSON.parse(
  readFileSync(
    resolve(process.cwd(), '../backend/tests/Fixtures/branding/scheme-vectors.json'),
    'utf8',
  ),
).vectors

function camelScheme(scheme) {
  return Object.fromEntries(
    Object.entries(scheme).map(([key, value]) => [
      key.replace(/_([a-z])/g, (m, c) => c.toUpperCase()),
      value,
    ]),
  )
}

function readStyle(relative) {
  return readFileSync(resolve(process.cwd(), 'src/styles', relative), 'utf8')
}

describe('brand colour scheme', () => {
  it('normalises six-digit hex codes and refuses anything else', () => {
    expect(normalizeHex(' #D4088C ')).toBe('#d4088c')
    expect(normalizeHex('#fff')).toBeNull()
    expect(normalizeHex('red')).toBeNull()
    expect(normalizeHex('#000000;}body{display:none')).toBeNull()
  })

  it('picks readable text on each fill and a readable text shade of the primary', () => {
    const scheme = deriveBrandScheme({ primary: '#98d8d3', secondary: '#0a2325' })
    expect(scheme.onPrimary).toBe(BRAND_SURFACES.ink)
    expect(scheme.onSecondary).toBe(BRAND_SURFACES.paper)
    expect(scheme.primaryText).not.toBe('#98d8d3')
    expect(contrastRatio(scheme.primaryText, BRAND_SURFACES.content)).toBeGreaterThanOrEqual(4.5)
    expect(contrastRatio(scheme.onPrimary, scheme.primaryHover)).toBeGreaterThanOrEqual(4.5)
    expect(contrastRatio(scheme.primaryText, scheme.primarySoft)).toBeGreaterThanOrEqual(4.5)
  })

  it('keeps a dark primary as its own text colour', () => {
    const scheme = deriveBrandScheme({ primary: '#1c5f9a', secondary: '#12263a' })
    expect(scheme.primaryText).toBe('#1c5f9a')
    expect(scheme.onPrimary).toBe(BRAND_SURFACES.paper)
  })

  it('darkens a saturated primary just enough to read on its soft background too', () => {
    const scheme = deriveBrandScheme({ primary: '#d4088c', secondary: '#4a2a7d' })
    expect(scheme.primaryText).toBe('#c90885')
    expect(contrastRatio(scheme.primaryText, scheme.primarySoft)).toBeGreaterThanOrEqual(4.5)
  })

  it.each(VECTORS.map((vector) => [vector.label, vector]))(
    'derives the same scheme as the server: %s',
    (label, vector) => {
      const overrides = camelScheme(vector.overrides)
      expect(deriveBrandScheme(vector, overrides)).toEqual(camelScheme(vector.scheme))
    },
  )

  it('applies only valid overrides of known derived colours', () => {
    const scheme = deriveBrandScheme(DEFAULT_BRAND_COLORS, {
      onSecondary: '#D9F7F7',
      navbar: '#000000',
      primarySoft: 'white',
    })
    expect(scheme.onSecondary).toBe('#d9f7f7')
    expect(scheme.primarySoft).toBe('#f5fbfb')
    expect(scheme).not.toHaveProperty('navbar')
  })

  it('keeps a low-contrast override exactly as chosen', () => {
    const scheme = deriveBrandScheme(
      { primary: '#ffe066', secondary: '#0a2325' },
      { onPrimary: '#FFFFFF' },
    )
    expect(scheme.onPrimary).toBe('#ffffff')
    expect(contrastRatio(scheme.onPrimary, scheme.primary)).toBeLessThan(4.5)
  })

  it('returns no scheme for invalid colours and validates a full scheme', () => {
    expect(deriveBrandScheme({ primary: 'red', secondary: '#0a2325' })).toBeNull()
    expect(isBrandScheme(deriveBrandScheme(DEFAULT_BRAND_COLORS))).toBe(true)
    expect(isBrandScheme({ primary: '#ffffff' })).toBe(false)
  })

  it('keeps the SCSS default brand tokens in sync with the default scheme', () => {
    const tokens = readStyle('tokens/_tts.scss')
    const scheme = deriveBrandScheme(DEFAULT_BRAND_COLORS)
    expect(tokens).toContain(`$tts-default-primary: ${scheme.primary};`)
    expect(tokens).toContain(`$tts-default-on-primary: ${scheme.onPrimary};`)
    expect(tokens).toContain(`$tts-default-primary-text: ${scheme.primaryText};`)
    expect(tokens).toContain(`$tts-default-secondary: ${scheme.secondary};`)
    expect(tokens).toContain(`$tts-default-on-secondary: ${scheme.onSecondary};`)
  })
})
