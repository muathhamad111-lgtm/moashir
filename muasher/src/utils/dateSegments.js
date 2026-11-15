import { digitsOnly, toWesternDigits } from '@/utils/numerals'

export const DATE_SEGMENTS = Object.freeze([
  Object.freeze({ key: 'day', length: 2 }),
  Object.freeze({ key: 'month', length: 2 }),
  Object.freeze({ key: 'year', length: 4 }),
])

const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})$/
const TYPED_DATE = /^(\d{1,2})\D+(\d{1,2})\D+(\d{4})$/

export function emptySegments() {
  return { day: '', month: '', year: '' }
}

export function segmentsOf(isoDate) {
  const match = ISO_DATE.exec(String(isoDate ?? ''))
  if (!match) return emptySegments()
  const [, year, month, day] = match
  return { day, month, year }
}

export function segmentsFromText(text) {
  const western = toWesternDigits(text)
  if (ISO_DATE.test(western)) return segmentsOf(western)
  const match = TYPED_DATE.exec(western)
  if (!match) return null
  const [, day, month, year] = match
  return { day: day.padStart(2, '0'), month: month.padStart(2, '0'), year }
}

export function spillDigits(segments, fromIndex, text) {
  const next = { ...segments }
  let rest = digitsOnly(text)
  let index = fromIndex
  for (; index < DATE_SEGMENTS.length; index += 1) {
    const { key, length } = DATE_SEGMENTS[index]
    next[key] = rest.slice(0, length)
    rest = rest.slice(length)
    if (rest === '' || index === DATE_SEGMENTS.length - 1) break
  }
  return { segments: next, lastIndex: index }
}

function isCalendarDay(year, month, day) {
  const date = new Date(Date.UTC(year, month - 1, day))
  return (
    date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day
  )
}

export function isoOf({ day, month, year }) {
  if (year.length !== 4 || !day || !month) return ''
  const [d, m, y] = [Number(day), Number(month), Number(year)]
  if (!isCalendarDay(y, m, d)) return ''
  return `${year}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`
}

export function isBlank(segments) {
  return DATE_SEGMENTS.every(({ key }) => segments[key] === '')
}

export function isIncomplete(segments) {
  return !isBlank(segments) && isoOf(segments) === ''
}
