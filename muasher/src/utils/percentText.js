import { formatNumber } from './formatNumber'

export function percentText(n, t, value) {
  return t('public.home.percent', { value: formatNumber(n, value) })
}

export function rangeValueText(n, t, range) {
  if (range.max === null) return percentText(n, t, range.min)
  return t('public.home.levels.range', {
    min: formatNumber(n, range.min),
    max: percentText(n, t, range.max),
  })
}

const ONE_DECIMAL = 10

export function roundToOneDecimal(value) {
  return Math.round(Number(value) * ONE_DECIMAL) / ONE_DECIMAL
}

export function scorePercentText(n, t, value) {
  return percentText(n, t, roundToOneDecimal(value))
}
