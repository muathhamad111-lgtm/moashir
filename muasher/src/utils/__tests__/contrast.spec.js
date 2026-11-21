import { describe, expect, it } from 'vitest'
import { contrastRatio, meetsAA, relativeLuminance } from '@/utils/contrast'

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
})
