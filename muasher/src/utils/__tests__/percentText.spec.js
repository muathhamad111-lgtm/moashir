import { describe, expect, it } from 'vitest'
import { compactDecimal, pointsText } from '@/utils/percentText'

describe('compactDecimal', () => {
  it('drops padding zeros from scores and scale bounds and keeps Western digits', () => {
    expect(compactDecimal('10.00')).toBe('10')
    expect(compactDecimal('7.50')).toBe('7.5')
    expect(compactDecimal('8.25')).toBe('8.25')
    expect(compactDecimal('1000.00')).toBe('1000')
  })

  it('returns an empty text for nothing and the raw text for a non-number', () => {
    expect(compactDecimal(null)).toBe('')
    expect(compactDecimal('')).toBe('')
    expect(compactDecimal('abc')).toBe('abc')
  })
})

describe('pointsText', () => {
  it('writes points with a thousands separator and two decimals in Western digits', () => {
    expect(pointsText('6112.5')).toBe('6,112.50')
    expect(pointsText('8200.00')).toBe('8,200.00')
    expect(pointsText(742)).toBe('742.00')
    expect(pointsText('514.5')).toBe('514.50')
    expect(pointsText(0)).toBe('0.00')
  })
})
