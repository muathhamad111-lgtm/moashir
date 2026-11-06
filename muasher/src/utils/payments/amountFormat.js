import { withLatinNumerals } from '@/utils/numerals'

const DECIMAL = /^-?\d+(\.\d+)?$/

export function formatAmount(value, locale) {
  const text = String(value ?? '')
  if (!DECIMAL.test(text)) return ''
  return new Intl.NumberFormat(withLatinNumerals(locale), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(text)
}

export function formatRate(value, locale) {
  const text = String(value ?? '')
  if (!DECIMAL.test(text)) return ''
  return new Intl.NumberFormat(withLatinNumerals(locale), {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(text)
}

export function formatFileSize(bytes, locale) {
  const kilobytes = Math.max(1, Math.round(Number(bytes ?? 0) / 1024))
  return new Intl.NumberFormat(withLatinNumerals(locale), { maximumFractionDigits: 0 }).format(
    kilobytes,
  )
}
