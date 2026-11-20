const PATTERN = /^[12]\d{9}$/
const EASTERN_DIGITS = '٠١٢٣٤٥٦٧٨٩'
const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹'
const DOUBLED_THRESHOLD = 9

function westernDigit(character) {
  const eastern = EASTERN_DIGITS.indexOf(character)
  if (eastern >= 0) return String(eastern)
  const persian = PERSIAN_DIGITS.indexOf(character)
  return persian >= 0 ? String(persian) : character
}

export function normalizeNationalId(value) {
  return [...String(value ?? '')].map(westernDigit).join('').replace(/\s+/g, '')
}

function checksumOf(digits) {
  return [...digits].reduce((sum, digit, position) => {
    const value = Number(digit)
    if (position % 2 !== 0) return sum + value
    const doubled = value * 2
    return sum + (doubled > DOUBLED_THRESHOLD ? doubled - DOUBLED_THRESHOLD : doubled)
  }, 0)
}

export function isValidNationalId(value) {
  const digits = normalizeNationalId(value)
  return PATTERN.test(digits) && checksumOf(digits) % 10 === 0
}
