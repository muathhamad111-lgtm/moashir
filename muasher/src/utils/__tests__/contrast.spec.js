import { describe, expect, it } from 'vitest'
import { contrastRatio, meetsAA, relativeLuminance } from '@/utils/contrast'
import { accentContrastPairs, passesAllContrast } from '@/utils/brandingContrast'

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

  it('builds the three accent pairs and flags failures', () => {
    const good = accentContrastPairs({ hex: '#3de9ad', textOnLightHex: '#0a7d57' })
    expect(good.map((pair) => pair.key)).toEqual(['fill', 'dark', 'light'])
    expect(passesAllContrast(good)).toBe(true)
    const bad = accentContrastPairs({ hex: '#3de9ad', textOnLightHex: '#3de9ad' })
    expect(bad.find((pair) => pair.key === 'light').passes).toBe(false)
    expect(passesAllContrast(bad)).toBe(false)
    expect(passesAllContrast(accentContrastPairs(null))).toBe(false)
  })
})
