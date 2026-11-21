import { formatNumber } from './formatNumber'

const TWO_DECIMALS = new Intl.NumberFormat('en-u-nu-latn', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const COMPACT_DECIMALS = new Intl.NumberFormat('en-u-nu-latn', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 2,
  useGrouping: false,
})

// A score or scale bound without padding zeros: «10», «7.5», «8.25».
export function compactDecimal(value) {
  if (value === null || value === undefined || value === '') return ''
  const numeric = Number(value)
  return Number.isNaN(numeric) ? String(value) : COMPACT_DECIMALS.format(numeric)
}

export function twoDecimals(value) {
  if (value === null || value === undefined || value === '') return ''
  const numeric = Number(value)
  return Number.isNaN(numeric) ? String(value) : TWO_DECIMALS.format(numeric)
}

// Points read the same on every screen: thousands separator and two decimals, «6,112.50».
export function pointsText(value) {
  return twoDecimals(value)
}

export function percentText(n, t, value) {
  return t('public.home.percent', { value: twoDecimals(value) })
}

export function wholePercentText(n, t, value) {
  return t('public.home.percent', { value: formatNumber(n, value, 'integer') })
}

export function thresholdText(n, t, value) {
  return t('public.home.percent', { value: formatNumber(n, Number(value)) })
}

export function rangeValueText(n, t, range) {
  if (range.max === null) return wholePercentText(n, t, range.min)
  return t('public.home.levels.range', {
    min: formatNumber(n, range.min),
    max: t('public.home.percent', { value: formatNumber(n, range.max) }),
  })
}
