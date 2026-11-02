import { describe, expect, it } from 'vitest'
import {
  compareWeights,
  equalShares,
  formatWeight,
  fromHundredths,
  isAcceptableWeight,
  normalizeWeight,
  percentOf,
  subtractWeight,
  sumWeights,
  toHundredths,
  weightBudget,
} from '@/utils/weight'

describe('weight — two-decimal strings without float drift', () => {
  it('converts decimal strings and numbers to hundredths and back', () => {
    expect(toHundredths('133.34')).toBe(13334)
    expect(toHundredths('400')).toBe(40000)
    expect(toHundredths(0.1)).toBe(10)
    expect(toHundredths('-50.5')).toBe(-5050)
    expect(fromHundredths(13334)).toBe('133.34')
    expect(fromHundredths(-5)).toBe('-0.05')
    expect(normalizeWeight(250)).toBe('250.00')
  })

  it('treats malformed input as zero instead of guessing', () => {
    expect(toHundredths('12.345')).toBe(0)
    expect(toHundredths('abc')).toBe(0)
    expect(toHundredths(null)).toBe(0)
  })

  it('sums the classic 0.1 + 0.2 exactly', () => {
    expect(sumWeights(['0.10', '0.20'])).toBe('0.30')
    expect(sumWeights(['133.34', '133.33', '133.33'])).toBe('400.00')
  })

  it('accepts only positive weights with at most two decimals within the backend limit', () => {
    expect(isAcceptableWeight('0.01')).toBe(true)
    expect(isAcceptableWeight('100000')).toBe(true)
    expect(isAcceptableWeight('100000.01')).toBe(false)
    expect(isAcceptableWeight('0')).toBe(false)
    expect(isAcceptableWeight('1.234')).toBe(false)
    expect(isAcceptableWeight('')).toBe(false)
  })

  it('splits equally with the remainder going to the first items (like the backend)', () => {
    expect(equalShares('400.00', 3)).toEqual(['133.34', '133.33', '133.33'])
    expect(equalShares('1000', 0)).toEqual([])
  })

  it('reports the remaining budget and its state', () => {
    expect(weightBudget('1000.00', '750.00')).toEqual({
      capacity: '1000.00',
      allocated: '750.00',
      remaining: '250.00',
      state: 'under',
    })
    expect(weightBudget('400', '400.00').state).toBe('balanced')
    expect(weightBudget('400', '450').state).toBe('over')
    expect(weightBudget('400', '450').remaining).toBe('-50.00')
  })

  it('compares, subtracts and derives percentages', () => {
    expect(compareWeights('10.00', '9.99')).toBe(1)
    expect(compareWeights('10', '10.00')).toBe(0)
    expect(subtractWeight('400.00', '133.34')).toBe('266.66')
    expect(percentOf('250', '1000')).toBe(25)
    expect(percentOf('1', '0')).toBe(0)
  })

  it('formats for display with Western digits in both locales', () => {
    expect(formatWeight('1000', 'en')).toBe('1,000.00')
    expect(formatWeight('133.34', 'ar')).toBe('133.34')
    expect(formatWeight('133.34', 'ar')).not.toMatch(/[\u0660-\u0669]/)
  })
})
