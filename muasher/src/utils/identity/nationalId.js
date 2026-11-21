// Operator rule (2026-11-21): 10 digits, starting with 1 or 2, digits only. No check digit.
export const NATIONAL_ID_LENGTH = 10
export const NATIONAL_ID_PROBLEMS = Object.freeze({
  DIGITS: 'digits',
  LENGTH: 'length',
  PREFIX: 'prefix',
})

const PATTERN = /^[12]\d{9}$/
const DIGITS_ONLY = /^\d+$/
const NON_DIGITS = /\D/g
const EASTERN_DIGITS = '٠١٢٣٤٥٦٧٨٩'
const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹'

function westernDigit(character) {
  const eastern = EASTERN_DIGITS.indexOf(character)
  if (eastern >= 0) return String(eastern)
  const persian = PERSIAN_DIGITS.indexOf(character)
  return persian >= 0 ? String(persian) : character
}

export function normalizeNationalId(value) {
  return [...String(value ?? '')].map(westernDigit).join('').replace(/\s+/g, '')
}

// What the field keeps while typing: Western digits only, at most 10.
export function nationalIdInput(value) {
  return normalizeNationalId(value).replace(NON_DIGITS, '').slice(0, NATIONAL_ID_LENGTH)
}

export function nationalIdProblem(value) {
  const digits = normalizeNationalId(value)
  if (!DIGITS_ONLY.test(digits)) return NATIONAL_ID_PROBLEMS.DIGITS
  if (digits.length !== NATIONAL_ID_LENGTH) return NATIONAL_ID_PROBLEMS.LENGTH
  return PATTERN.test(digits) ? null : NATIONAL_ID_PROBLEMS.PREFIX
}

export function isValidNationalId(value) {
  return nationalIdProblem(value) === null
}
