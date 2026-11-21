import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'
import { describe, expect, it } from 'vitest'
import { BRAND_SURFACES, DEFAULT_BRAND_COLORS } from '@/constants/branding'
import {
  brandContrastPairs,
  darken,
  deriveBrandScheme,
  isBrandScheme,
  normalizeHex,
  passesAllContrast,
  serverContrastPairs,
} from '@/utils/brandingContrast'
import { contrastRatio } from '@/utils/contrast'

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

  it('darkens a colour toward black by the given share', () => {
    expect(darken('#ffffff', 0.5)).toBe('#808080')
    expect(darken('#98d8d3', 1)).toBe('#000000')
  })

  it('picks readable text on each fill and a readable text shade of the primary', () => {
    const scheme = deriveBrandScheme({ primary: '#98d8d3', secondary: '#0a2325' })
    expect(scheme.onPrimary).toBe(BRAND_SURFACES.ink)
    expect(scheme.onSecondary).toBe(BRAND_SURFACES.paper)
    expect(scheme.primaryText).not.toBe('#98d8d3')
    expect(contrastRatio(scheme.primaryText, BRAND_SURFACES.content)).toBeGreaterThanOrEqual(4.5)
    expect(passesAllContrast(brandContrastPairs(scheme))).toBe(true)
  })

  it('keeps a dark primary as its own text colour', () => {
    const scheme = deriveBrandScheme({ primary: '#d4088c', secondary: '#4a2a7d' })
    expect(scheme.primaryText).toBe('#d4088c')
    expect(scheme.onPrimary).toBe(BRAND_SURFACES.paper)
  })

  it('flags a mid-tone on which neither white nor dark text reaches AA', () => {
    const pairs = brandContrastPairs(
      deriveBrandScheme({ primary: '#777777', secondary: '#777777' }),
    )
    expect(pairs.filter((pair) => !pair.passes).map((pair) => pair.key)).toEqual([
      'primary.text_on_fill',
      'secondary.text_on_fill',
    ])
  })

  it('returns no scheme for invalid colours and validates a full scheme', () => {
    expect(deriveBrandScheme({ primary: 'red', secondary: '#0a2325' })).toBeNull()
    expect(isBrandScheme(deriveBrandScheme(DEFAULT_BRAND_COLORS))).toBe(true)
    expect(isBrandScheme({ primary: '#ffffff' })).toBe(false)
  })

  it('maps the server rejection pairs', () => {
    expect(
      serverContrastPairs([
        {
          pair: 'primary.text_on_fill',
          foreground: '#ffffff',
          background: '#777777',
          ratio: '4.48',
          minimum: '4.5',
        },
      ]),
    ).toEqual([
      {
        key: 'primary.text_on_fill',
        foreground: '#ffffff',
        background: '#777777',
        ratio: 4.48,
        minimum: 4.5,
        passes: false,
      },
    ])
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
