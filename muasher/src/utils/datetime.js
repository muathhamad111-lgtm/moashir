import { withLatinNumerals } from '@/utils/numerals'

const RELATIVE_STEPS = [
  { unit: 'year', seconds: 31536000 },
  { unit: 'month', seconds: 2592000 },
  { unit: 'week', seconds: 604800 },
  { unit: 'day', seconds: 86400 },
  { unit: 'hour', seconds: 3600 },
  { unit: 'minute', seconds: 60 },
]

export function formatRelativeTime(value, locale, now = new Date()) {
  const date = value instanceof Date ? value : new Date(value)
  const elapsedSeconds = Math.round((date.getTime() - now.getTime()) / 1000)
  const formatter = new Intl.RelativeTimeFormat(withLatinNumerals(locale), { numeric: 'auto' })
  for (const step of RELATIVE_STEPS) {
    if (Math.abs(elapsedSeconds) >= step.seconds) {
      return formatter.format(Math.trunc(elapsedSeconds / step.seconds), step.unit)
    }
  }
  return formatter.format(elapsedSeconds, 'second')
}

export function formatDate(value, locale, options = { dateStyle: 'medium' }) {
  const date = value instanceof Date ? value : new Date(value)
  return new Intl.DateTimeFormat(withLatinNumerals(locale), options).format(date)
}

export function formatHijriDate(value, locale) {
  const date = value instanceof Date ? value : new Date(value)
  return new Intl.DateTimeFormat(
    `${locale === 'en' ? 'en' : 'ar-SA'}-u-ca-islamic-umalqura-nu-latn`,
    {
      dateStyle: 'long',
      timeZone: 'Asia/Riyadh',
    },
  ).format(date)
}

const RIYADH = 'Asia/Riyadh'

export function formatRiyadhDateTime(value, locale) {
  return value
    ? formatDate(value, locale, { dateStyle: 'medium', timeStyle: 'short', timeZone: RIYADH })
    : ''
}

export function formatRiyadhDate(value, locale) {
  return value ? formatDate(value, locale, { dateStyle: 'medium', timeZone: RIYADH }) : ''
}

export function formatRiyadhLongDate(value, locale) {
  return value ? formatDate(value, locale, { dateStyle: 'long', timeZone: RIYADH }) : ''
}

export function riyadhDateKey(value) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: RIYADH }).format(
    value instanceof Date ? value : new Date(value),
  )
}

export function endOfRiyadhDay(dateKey) {
  return new Date(`${dateKey}T23:59:00+03:00`).toISOString()
}

export function startOfRiyadhDay(dateKey) {
  return new Date(`${dateKey}T00:00:00+03:00`).toISOString()
}

export function isoDate(value) {
  return value ? riyadhDateKey(value) : ''
}
