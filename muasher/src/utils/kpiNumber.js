import { formatNumber } from './formatNumber'

const FULL_PERCENT = 100

export function kpiNumeric(value) {
  if (value === null || value === undefined || value === '') return null
  const numeric = Number(value)
  return Number.isNaN(numeric) ? null : numeric
}

export function formatKpiValue(n, value) {
  return kpiNumeric(value) === null ? '' : formatNumber(n, value)
}

export function kpiProgress(value, target) {
  const goal = kpiNumeric(target)
  const current = kpiNumeric(value) ?? 0
  if (!goal) return null
  return Math.max(0, Math.min(FULL_PERCENT, Math.round((current / goal) * FULL_PERCENT)))
}

export function formatCountUp(n, current, end) {
  const shown = Number.isInteger(end) ? Math.round(current) : Math.round(current * 10) / 10
  return formatKpiValue(n, shown)
}
