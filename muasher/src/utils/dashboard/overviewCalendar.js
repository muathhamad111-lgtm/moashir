import { OVERVIEW_FIRST_MONTH } from '@/constants/dashboardOverview'
import { withLatinNumerals } from '@/utils/numerals'

const MONTHS_PER_QUARTER = 3

function parts(dayKey) {
  const [year, month] = dayKey.split('-').map(Number)
  return { year, month, quarter: Math.ceil(month / MONTHS_PER_QUARTER) }
}

const FIRST = parts(`${OVERVIEW_FIRST_MONTH}-01`)

/**
 * The calendar month, quarter and year of a day key (YYYY-MM-DD).
 */
export function calendarOf(dayKey) {
  return parts(dayKey)
}

// Months are compared as year * 12 + month, so a month is open when it lies between the first
// month the boards cover and the current month.
function monthIndex(year, month) {
  return year * 12 + month
}

export function isMonthOpen(year, month, today) {
  const now = calendarOf(today)
  const index = monthIndex(year, month)
  return index >= monthIndex(FIRST.year, FIRST.month) && index <= monthIndex(now.year, now.month)
}

export function isQuarterOpen(year, quarter, today) {
  const firstMonth = (quarter - 1) * MONTHS_PER_QUARTER + 1
  return [0, 1, 2].some((offset) => isMonthOpen(year, firstMonth + offset, today))
}

export function selectableYears(today) {
  const { year } = calendarOf(today)
  return Array.from({ length: year - FIRST.year + 1 }, (_, index) => FIRST.year + index)
}

export function monthName(month, locale, style = 'long') {
  return new Intl.DateTimeFormat(withLatinNumerals(locale), {
    month: style,
    timeZone: 'UTC',
  }).format(new Date(Date.UTC(2000, month - 1, 15)))
}

export function monthLabel(year, month, locale) {
  return new Intl.DateTimeFormat(withLatinNumerals(locale), {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(new Date(Date.UTC(year, month - 1, 15)))
}

export function quarterMonths(quarter, locale) {
  const first = (quarter - 1) * MONTHS_PER_QUARTER + 1
  return `${monthName(first, locale, 'short')} – ${monthName(first + 2, locale, 'short')}`
}

// A day key (YYYY-MM-DD) as a UTC date, so formatting never shifts the calendar day.
function dayDate(dayKey) {
  return new Date(`${dayKey}T12:00:00Z`)
}

export function rangeLabel(from, to, locale) {
  const format = new Intl.DateTimeFormat(withLatinNumerals(locale), {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
  })
  return from === to ? format.format(dayDate(from)) : format.formatRange(dayDate(from), dayDate(to))
}
