import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'
import { describe, expect, it } from 'vitest'
import { ACCENT_TOKENS, CONTRAST_SURFACES, DEFAULT_ACCENT_TOKEN } from '@/constants/branding'
import { brandingPaletteFixture } from '@/mocks/fixtures/branding'
import { accentContrastPairs, passesAllContrast } from '@/utils/brandingContrast'

function readStyle(relative) {
  return readFileSync(resolve(process.cwd(), 'src/styles', relative), 'utf8')
}

function scssPalette() {
  const source = readStyle('tokens/_palette.scss')
  const entries = [...source.matchAll(/'([a-z_]+)': \(\s*'fill': (#[0-9a-f]{6}),\s*'text-on-light': (#[0-9a-f]{6}),/g)]
  return entries.map(([, token, fill, textOnLight]) => ({ token, fill, textOnLight }))
}

describe('controlled accent palette', () => {
  it('has the brand default plus exactly 8 alternatives', () => {
    expect(ACCENT_TOKENS[0]).toBe(DEFAULT_ACCENT_TOKEN)
    expect(ACCENT_TOKENS).toHaveLength(9)
  })

  it('keeps the SCSS tokens, the constants and the API fixture in sync', () => {
    const tokens = scssPalette()
    expect(tokens.map((entry) => entry.token)).toEqual(ACCENT_TOKENS)
    expect(brandingPaletteFixture.map((entry) => entry.token)).toEqual(ACCENT_TOKENS)
    tokens.forEach((entry, index) => {
      expect(brandingPaletteFixture[index].hex).toBe(entry.fill)
      expect(brandingPaletteFixture[index].textOnLightHex).toBe(entry.textOnLight)
    })
  })

  it('passes WCAG 2.1 AA for every color in dark and light mode', () => {
    const failing = brandingPaletteFixture
      .filter((entry) => !passesAllContrast(accentContrastPairs({ hex: entry.hex, textOnLightHex: entry.textOnLightHex })))
      .map((entry) => entry.token)
    expect(failing).toEqual([])
  })

  it('matches the contrast surfaces to the brand color tokens', () => {
    const colors = readStyle('tokens/_colors.scss')
    expect(colors).toContain(`$color-dark: ${CONTRAST_SURFACES.darkContent};`)
    expect(colors).toContain(`$color-gray-50: ${CONTRAST_SURFACES.lightContent};`)
  })

  it('gives every palette entry a bilingual label', () => {
    brandingPaletteFixture.forEach((entry) => {
      expect(entry.label.ar).toBeTruthy()
      expect(entry.label.en).toBeTruthy()
    })
  })
})
