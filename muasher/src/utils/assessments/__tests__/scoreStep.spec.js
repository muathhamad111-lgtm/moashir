import { describe, expect, it } from 'vitest'
import {
  canDecreaseScore,
  canIncreaseScore,
  sanitizeScoreText,
  stepScore,
} from '@/utils/assessments/scoreStep'

describe('score stepper rules', () => {
  it('steps by one within 0 and the maximum, keeping typed decimals', () => {
    expect(stepScore('', 1, '10.00')).toBe('1')
    expect(stepScore('', -1, '10.00')).toBe('0')
    expect(stepScore('7.5', 1, '10.00')).toBe('8.5')
    expect(stepScore('9.5', 1, '10.00')).toBe('10')
    expect(stepScore('0.4', -1, '10.00')).toBe('0')
    expect(stepScore('0.1', 1, '10.00')).toBe('1.1')
  })

  it('treats an unreadable value as empty', () => {
    expect(stepScore('abc', 1, '5')).toBe('1')
    expect(canDecreaseScore('abc')).toBe(false)
  })

  it('allows a step only when it changes the score', () => {
    expect(canDecreaseScore('')).toBe(false)
    expect(canDecreaseScore('0')).toBe(false)
    expect(canDecreaseScore('0.25')).toBe(true)
    expect(canIncreaseScore('', '10.00')).toBe(true)
    expect(canIncreaseScore('10', '10.00')).toBe(false)
    expect(canIncreaseScore('9.75', '10.00')).toBe(true)
  })

  it('keeps only a number with at most two decimals while typing', () => {
    expect(sanitizeScoreText('10تال')).toBe('10')
    expect(sanitizeScoreText('٧٫٥')).toBe('7.5')
    expect(sanitizeScoreText('1,5')).toBe('1.5')
    expect(sanitizeScoreText('7.555')).toBe('7.55')
    expect(sanitizeScoreText('1.2.3')).toBe('1.23')
    expect(sanitizeScoreText('10.')).toBe('10.')
    expect(sanitizeScoreText('-3e2')).toBe('32')
    expect(sanitizeScoreText('')).toBe('')
  })
})
