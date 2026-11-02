const DECIMAL_WEIGHT = /^-?\d+(?:\.\d{1,2})?$/
const HUNDREDTHS = 100

export const MAX_WEIGHT_HUNDREDTHS = 100000 * HUNDREDTHS

export function isDecimalWeight(value) {
  if (value === null || value === undefined) return false
  return DECIMAL_WEIGHT.test(String(value).trim())
}

export function toHundredths(value) {
  if (!isDecimalWeight(value)) return 0
  const text = String(value).trim()
  const negative = text.startsWith('-')
  const [whole, fraction = ''] = text.replace('-', '').split('.')
  const amount = Number(whole) * HUNDREDTHS + Number(fraction.padEnd(2, '0'))
  return negative ? -amount : amount
}

export function fromHundredths(hundredths) {
  const sign = hundredths < 0 ? '-' : ''
  const absolute = Math.abs(hundredths)
  const fraction = String(absolute % HUNDREDTHS).padStart(2, '0')
  return `${sign}${Math.floor(absolute / HUNDREDTHS)}.${fraction}`
}

export function normalizeWeight(value) {
  return fromHundredths(toHundredths(value))
}

export function isAcceptableWeight(value) {
  if (!isDecimalWeight(value)) return false
  const hundredths = toHundredths(value)
  return hundredths > 0 && hundredths <= MAX_WEIGHT_HUNDREDTHS
}

export function sumWeights(values) {
  return fromHundredths(values.reduce((total, value) => total + toHundredths(value), 0))
}

export function subtractWeight(from, amount) {
  return fromHundredths(toHundredths(from) - toHundredths(amount))
}

export function compareWeights(left, right) {
  return Math.sign(toHundredths(left) - toHundredths(right))
}

export function weightBudget(capacity, allocated) {
  const remaining = toHundredths(capacity) - toHundredths(allocated)
  let state = 'balanced'
  if (remaining > 0) state = 'under'
  if (remaining < 0) state = 'over'
  return {
    capacity: normalizeWeight(capacity),
    allocated: normalizeWeight(allocated),
    remaining: fromHundredths(remaining),
    state,
  }
}

export function equalShares(total, count) {
  if (!count) return []
  const hundredths = toHundredths(total)
  const base = Math.floor(hundredths / count)
  const remainder = hundredths - base * count
  return Array.from({ length: count }, (_, index) =>
    fromHundredths(base + (index < remainder ? 1 : 0)),
  )
}

export function percentOf(part, whole) {
  const wholeHundredths = toHundredths(whole)
  if (!wholeHundredths) return 0
  return Math.round((toHundredths(part) * 100) / wholeHundredths)
}

export function formatWeight(value, locale = 'en') {
  return new Intl.NumberFormat(locale, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
    numberingSystem: 'latn',
  }).format(toHundredths(value) / HUNDREDTHS)
}
