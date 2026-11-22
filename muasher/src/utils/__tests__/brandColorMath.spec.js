import { describe, expect, it } from 'vitest'
import { darken, mix, perceptualDistance } from '@/utils/brandColorMath'

describe('brand colour maths', () => {
  it('darkens a colour toward black by the given share', () => {
    expect(darken('#ffffff', 0.5)).toBe('#808080')
    expect(darken('#98d8d3', 1)).toBe('#000000')
  })

  it('mixes two colours in sRGB like CSS color-mix', () => {
    expect(mix('#98d8d3', '#100f10', 0.88)).toBe('#88c0bc')
    expect(mix('#98d8d3', '#ffffff', 0.1)).toBe('#f5fbfb')
    expect(mix('#000000', '#ffffff', 2)).toBe('#000000')
  })

  it('measures how far apart two colours look', () => {
    expect(perceptualDistance('#98d8d3', '#98d8d3')).toBe(0)
    expect(perceptualDistance('#000000', '#ffffff')).toBeCloseTo(100, 0)
    expect(perceptualDistance('#e5484d', '#f83a3a')).toBeLessThan(15)
    expect(perceptualDistance('#98d8d3', '#f83a3a')).toBeGreaterThan(15)
  })
})
