import { describe, expect, it } from 'vitest'
import { contrastRatio, meetsAA, relativeLuminance } from '@/utils/contrast'
import {
  CONTRAST_PAIR_KEYS,
  accentContrastPairs,
  passesAllContrast,
  serverContrastPairs,
} from '@/utils/brandingContrast'

describe('contrast utilities', () => {
  it('computes the WCAG ratio of black on white as 21:1', () => {
    expect(contrastRatio('#000000', '#ffffff')).toBeCloseTo(21, 5)
  })

  it('is symmetric and returns 1 for identical colors', () => {
    expect(contrastRatio('#3de9ad', '#022426')).toBeCloseTo(contrastRatio('#022426', '#3de9ad'), 10)
    expect(contrastRatio('#022426', '#022426')).toBe(1)
  })

  it('rejects malformed colors', () => {
    expect(relativeLuminance('red')).toBeNull()
    expect(contrastRatio('#12', '#ffffff')).toBeNull()
    expect(meetsAA(null)).toBe(false)
  })

  it('applies the 4.5:1 AA threshold for normal text', () => {
    expect(meetsAA(4.5)).toBe(true)
    expect(meetsAA(4.49)).toBe(false)
  })

  it('builds the four server-named accent pairs and flags failures', () => {
    const good = accentContrastPairs({ hex: '#98d8d3', textOnLightHex: '#1d7470' })
    expect(good.map((pair) => pair.key)).toEqual(CONTRAST_PAIR_KEYS)
    expect(passesAllContrast(good)).toBe(true)
    const bad = accentContrastPairs({ hex: '#98d8d3', textOnLightHex: '#98d8d3' })
    expect(bad.find((pair) => pair.key === 'light.accent_text_on_content').passes).toBe(false)
    expect(passesAllContrast(bad)).toBe(false)
    expect(passesAllContrast(accentContrastPairs(null))).toBe(false)
  })

  it('maps the server contrast_fail pairs to failing display rows', () => {
    const rows = serverContrastPairs([
      { pair: 'dark.accent_on_content', foreground: '#111111', background: '#0a2325', ratio: '1.10', minimum: '4.5' },
    ])
    expect(rows).toEqual([
      { key: 'dark.accent_on_content', foreground: '#111111', background: '#0a2325', ratio: 1.1, minimum: 4.5, passes: false },
    ])
  })
})
