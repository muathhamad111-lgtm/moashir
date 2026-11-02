const MINOR_PER_UNIT = 100
const RATE_SCALE = 10000
const HALF_RATE_SCALE = RATE_SCALE / 2

function decimalParts(value) {
  const [whole, fraction = ''] = String(value).split('.')
  return { whole: Number(whole), fraction: Number(fraction.padEnd(2, '0').slice(0, 2)) }
}

export function toMinor(value) {
  const { whole, fraction } = decimalParts(value)
  return whole * MINOR_PER_UNIT + fraction
}

export function fromMinor(minor) {
  const whole = Math.floor(minor / MINOR_PER_UNIT)
  const fraction = String(minor % MINOR_PER_UNIT).padStart(2, '0')
  return `${whole}.${fraction}`
}

export function vatOf(priceBeforeVat, vatRate) {
  const rateBasisPoints = toMinor(vatRate)
  return fromMinor(Math.floor((toMinor(priceBeforeVat) * rateBasisPoints + HALF_RATE_SCALE) / RATE_SCALE))
}

export function quoteOf(priceBeforeVat, vatRate, currency = 'SAR') {
  const vatAmount = vatOf(priceBeforeVat, vatRate)
  return {
    priceBeforeVat,
    vatRate,
    vatAmount,
    total: fromMinor(toMinor(priceBeforeVat) + toMinor(vatAmount)),
    currency,
  }
}

export function sumOf(values) {
  return fromMinor(values.reduce((total, value) => total + toMinor(value), 0))
}

export function normalizedRate(value) {
  return fromMinor(toMinor(value))
}
