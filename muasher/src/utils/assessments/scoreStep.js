import { compactDecimal } from '@/utils/percentText'
import { westernizeDigits } from '@/utils/numerals'

const HUNDREDTHS = 100
const MAX_DECIMALS = 2

function parsed(value) {
  const text = String(value ?? '').trim()
  if (text === '') return null
  const numeric = Number(text)
  return Number.isFinite(numeric) ? numeric : null
}

export function canDecreaseScore(value) {
  const current = parsed(value)
  return current !== null && current > 0
}

export function canIncreaseScore(value, maximum) {
  const current = parsed(value)
  return current === null || current < Number(maximum)
}

export function stepScore(value, delta, maximum) {
  const current = parsed(value) ?? 0
  const next = Math.min(Number(maximum), Math.max(0, current + delta))
  return compactDecimal(Math.round(next * HUNDREDTHS) / HUNDREDTHS)
}

export function sanitizeScoreText(value) {
  const western = westernizeDigits(value)
    .replaceAll(',', '.')
    .replace(/[^0-9.]/g, '')
  const [whole, ...fraction] = western.split('.')
  if (!fraction.length) return whole
  return `${whole}.${fraction.join('').slice(0, MAX_DECIMALS)}`
}
