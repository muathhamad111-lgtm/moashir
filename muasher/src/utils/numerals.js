const EASTERN_DIGITS = /[\u0660-\u0669\u06F0-\u06F9]/g
const ARABIC_DECIMAL_SEPARATOR = /\u066B/g

export function withLatinNumerals(locale) {
  const base = typeof locale === 'string' && locale.length > 0 ? locale : 'en'
  if (base.includes('-u-nu-')) {
    return base
  }
  return `${base}-u-nu-latn`
}

export function toWesternDigits(value) {
  return String(value ?? '')
    .replace(EASTERN_DIGITS, (digit) => String(digit.charCodeAt(0) % 16))
    .replace(ARABIC_DECIMAL_SEPARATOR, '.')
    .trim()
}
