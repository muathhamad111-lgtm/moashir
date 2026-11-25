import {
  COMPARISON_BASES,
  DEFAULT_OVERVIEW_PERIOD,
  OVERVIEW_PERIODS,
  OVERVIEW_PRESETS,
} from '@/constants/dashboardOverview'

// Mirrors the API's ReportingPeriod: a half-open window [start, end) of UTC calendar days, and the
// window it is compared with.
const DAY_MS = 86400000
const MONTHS = Object.freeze({
  [OVERVIEW_PERIODS.MONTH]: 1,
  [OVERVIEW_PERIODS.QUARTER]: 3,
  [OVERVIEW_PERIODS.YEAR]: 12,
})

function dayOf(key) {
  return new Date(`${key}T00:00:00Z`)
}

function addDays(date, days) {
  return new Date(date.getTime() + days * DAY_MS)
}

function addMonths(date, months) {
  return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + months, 1))
}

// The same day of the month `months` earlier, kept inside that month (31 Mar → 28/29 Feb).
function sameDayMonthsBefore(date, months) {
  const year = date.getUTCFullYear()
  const month = date.getUTCMonth() - months
  const lastDay = new Date(Date.UTC(year, month + 1, 0)).getUTCDate()
  return new Date(Date.UTC(year, month, Math.min(date.getUTCDate(), lastDay)))
}

function numberOf(value, fallback) {
  const number = Number.parseInt(value ?? '', 10)
  return Number.isInteger(number) ? number : fallback
}

function startOf(preset, query, today) {
  const year = numberOf(query.year, today.getUTCFullYear())
  if (preset === OVERVIEW_PERIODS.YEAR) return new Date(Date.UTC(year, 0, 1))
  if (preset === OVERVIEW_PERIODS.QUARTER) {
    const quarter = numberOf(query.quarter, Math.floor(today.getUTCMonth() / 3) + 1)
    return new Date(Date.UTC(year, (quarter - 1) * 3, 1))
  }
  return new Date(Date.UTC(year, numberOf(query.month, today.getUTCMonth() + 1) - 1, 1))
}

export function reportingWindow(query, today) {
  const preset = OVERVIEW_PRESETS.includes(query.period) ? query.period : DEFAULT_OVERVIEW_PERIOD
  if (preset === OVERVIEW_PERIODS.CUSTOM)
    return { preset, start: dayOf(query.from), end: addDays(dayOf(query.to), 1), running: false }
  const start = startOf(preset, query, today)
  const fullEnd = addMonths(start, MONTHS[preset])
  const tomorrow = addDays(today, 1)
  const running = tomorrow < fullEnd
  return { preset, start, end: running ? tomorrow : fullEnd, running }
}

/**
 * A finished month, quarter or year is compared with the previous full one; a running one with
 * the same span of the previous one; a custom range with the same number of days before it.
 */
export function comparisonWindow({ preset, start, end, running }) {
  if (preset === OVERVIEW_PERIODS.CUSTOM) {
    const days = Math.round((end - start) / DAY_MS)
    return { start: addDays(start, -days), end: start, basis: COMPARISON_BASES.PRECEDING }
  }
  const months = MONTHS[preset]
  const previousStart = addMonths(start, -months)
  if (!running) return { start: previousStart, end: start, basis: COMPARISON_BASES.FULL }
  const lastDay = sameDayMonthsBefore(addDays(end, -1), months)
  const previousEnd = addDays(lastDay, 1) < start ? addDays(lastDay, 1) : start
  return { start: previousStart, end: previousEnd, basis: COMPARISON_BASES.ELAPSED }
}
