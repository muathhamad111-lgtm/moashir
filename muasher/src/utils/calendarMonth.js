const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/

export const DAYS_PER_WEEK = 7
const WEEKS_PER_GRID = 6

function utcDate(year, monthIndex, day) {
  const date = new Date(0)
  date.setUTCFullYear(year, monthIndex, day)
  return date
}

function partsOf(iso) {
  const [year, month, day] = iso.split('-').map(Number)
  return { year, monthIndex: month - 1, day }
}

function isoOfDate(date) {
  const year = String(date.getUTCFullYear()).padStart(4, '0')
  const month = String(date.getUTCMonth() + 1).padStart(2, '0')
  const day = String(date.getUTCDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function isCalendarIso(value) {
  if (typeof value !== 'string' || !ISO_DATE.test(value)) return false
  const { year, monthIndex, day } = partsOf(value)
  return isoOfDate(utcDate(year, monthIndex, day)) === value
}

export function dateOfIso(iso) {
  const { year, monthIndex, day } = partsOf(iso)
  return utcDate(year, monthIndex, day)
}

export function addDays(iso, days) {
  const { year, monthIndex, day } = partsOf(iso)
  return isoOfDate(utcDate(year, monthIndex, day + days))
}

function daysInMonth(year, monthIndex) {
  return utcDate(year, monthIndex + 1, 0).getUTCDate()
}

export function addMonths(iso, months) {
  const { year, monthIndex, day } = partsOf(iso)
  const first = utcDate(year, monthIndex + months, 1)
  const lastDay = daysInMonth(first.getUTCFullYear(), first.getUTCMonth())
  return isoOfDate(utcDate(first.getUTCFullYear(), first.getUTCMonth(), Math.min(day, lastDay)))
}

export function startOfWeek(iso) {
  return addDays(iso, -dateOfIso(iso).getUTCDay())
}

export function endOfWeek(iso) {
  return addDays(startOfWeek(iso), DAYS_PER_WEEK - 1)
}

export function firstOfMonth(iso) {
  return `${iso.slice(0, 8)}01`
}

export function lastOfMonth(iso) {
  const { year, monthIndex } = partsOf(iso)
  return isoOfDate(utcDate(year, monthIndex, daysInMonth(year, monthIndex)))
}

export function isSameMonth(iso, other) {
  return iso.slice(0, 7) === other.slice(0, 7)
}

export function monthGrid(iso) {
  const gridStart = startOfWeek(firstOfMonth(iso))
  return Array.from({ length: WEEKS_PER_GRID }, (_, week) =>
    Array.from({ length: DAYS_PER_WEEK }, (__, weekday) =>
      addDays(gridStart, week * DAYS_PER_WEEK + weekday),
    ),
  )
}

export function isBeforeMin(iso, min) {
  return Boolean(min) && iso < min
}

export function isAfterMax(iso, max) {
  return Boolean(max) && iso > max
}

export function isOutsideRange(iso, { min, max }) {
  return isBeforeMin(iso, min) || isAfterMax(iso, max)
}

export function clampToRange(iso, { min, max }) {
  if (isBeforeMin(iso, min)) return min
  if (isAfterMax(iso, max)) return max
  return iso
}
