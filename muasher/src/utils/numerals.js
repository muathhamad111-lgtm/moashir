const EASTERN_DIGITS = /[\u0660-\u0669\u06F0-\u06F9]/g
const ARABIC_DECIMAL_SEPARATOR = /\u066B/g

export function withLatinNumerals(locale) {
  const base = typeof locale === 'string' && locale.length > 0 ? locale : 'en'
  if (base.includes('-u-nu-')) {
    return base
  }
  return `${base}-u-nu-latn`
}

export function westernizeDigits(value) {
  return String(value ?? '')
    .replace(EASTERN_DIGITS, (digit) => String(digit.charCodeAt(0) % 16))
    .replace(ARABIC_DECIMAL_SEPARATOR, '.')
}

export function toWesternDigits(value) {
  return westernizeDigits(value).trim()
}

export function westernizeInputDigits(input) {
  const western = westernizeDigits(input.value)
  if (western === input.value) return western
  const { selectionStart, selectionEnd } = input
  input.value = western
  if (selectionStart !== null) input.setSelectionRange(selectionStart, selectionEnd)
  return western
}

export function digitsOnly(value) {
  return toWesternDigits(value).replace(/\D/g, '')
}

export function limitDecimalInput(value, { integerDigits, decimals }) {
  const [whole = '', ...fraction] = toWesternDigits(value)
    .replace(/[^\d.]/g, '')
    .split('.')
  const integer = whole.slice(0, integerDigits)
  if (fraction.length === 0 || decimals === 0) return integer
  return `${integer}.${fraction.join('').slice(0, decimals)}`
}
