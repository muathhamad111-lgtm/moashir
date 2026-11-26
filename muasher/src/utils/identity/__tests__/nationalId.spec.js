import { describe, expect, it } from 'vitest'
import {
  isValidNationalId,
  nationalIdInput,
  nationalIdProblem,
  normalizeNationalId,
} from '@/utils/identity/nationalId'

const CITIZEN = '1000000008'
const RESIDENT = '2000000006'

describe('national ID rule', () => {
  it('accepts any 10 digits starting with 1 or 2, with no check digit', () => {
    expect(isValidNationalId(CITIZEN)).toBe(true)
    expect(isValidNationalId(RESIDENT)).toBe(true)
    expect(isValidNationalId('1000000009')).toBe(true)
  })

  it.each([
    ['letters', '10000a0008', 'digits'],
    ['symbols', '1000-00008', 'digits'],
    ['nothing', '', 'digits'],
    ['nine digits', '100000000', 'length'],
    ['eleven digits', '10000000081', 'length'],
    ['a first digit other than 1 or 2', '3000000001', 'prefix'],
  ])('names the rule broken by %s', (_label, value, problem) => {
    expect(nationalIdProblem(value)).toBe(problem)
    expect(isValidNationalId(value)).toBe(false)
  })

  it('reads Eastern Arabic digits and ignores spaces', () => {
    expect(normalizeNationalId(' ١٠٠٠ ٠٠٠ ٠٠٨ ')).toBe(CITIZEN)
    expect(isValidNationalId('١٠٠٠٠٠٠٠٠٨')).toBe(true)
  })

  it('keeps only digits while typing, at most ten', () => {
    expect(nationalIdInput('١٠ab-0000000812')).toBe('1000000008')
    expect(nationalIdInput('x')).toBe('')
  })
})
