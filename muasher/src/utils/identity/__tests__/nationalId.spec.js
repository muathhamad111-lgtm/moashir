import { describe, expect, it } from 'vitest'
import { isValidNationalId, normalizeNationalId } from '@/utils/identity/nationalId'

const CITIZEN = '1000000008'
const RESIDENT = '2000000006'

describe('national ID rule', () => {
  it('accepts citizen and resident numbers with a valid check digit', () => {
    expect(isValidNationalId(CITIZEN)).toBe(true)
    expect(isValidNationalId(RESIDENT)).toBe(true)
  })

  it.each([
    ['a bad check digit', '1000000009'],
    ['nine digits', '100000000'],
    ['a first digit other than 1 or 2', '3000000001'],
    ['letters', '10000a0008'],
    ['nothing', ''],
  ])('rejects %s', (_label, value) => {
    expect(isValidNationalId(value)).toBe(false)
  })

  it('reads Eastern Arabic digits and ignores spaces', () => {
    expect(normalizeNationalId(' ١٠٠٠ ٠٠٠ ٠٠٨ ')).toBe(CITIZEN)
    expect(isValidNationalId('١٠٠٠٠٠٠٠٠٨')).toBe(true)
  })
})
