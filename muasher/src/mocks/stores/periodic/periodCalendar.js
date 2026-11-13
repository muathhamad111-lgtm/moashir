import { riyadhDateKey } from '@/utils/datetime'

export const PERIODIC_SETTINGS = Object.freeze({
  rolloutFrom: '2026-04-01',
  cadence: 'quarterly',
  noticeDaysBefore: 14,
})

const QUARTER_NAMES = {
  ar: ['الربع الأول', 'الربع الثاني', 'الربع الثالث', 'الربع الرابع'],
  en: ['Q1', 'Q2', 'Q3', 'Q4'],
}
const MONTHS_PER_QUARTER = 3
const DAY_MS = 24 * 60 * 60 * 1000
const RIYADH_OFFSET = '+03:00'

function pad(value) {
  return String(value).padStart(2, '0')
}

function lastDayOf(year, month) {
  return new Date(Date.UTC(year, month, 0)).getUTCDate()
}

function instantOf(dateKey) {
  return new Date(`${dateKey}T00:00:00${RIYADH_OFFSET}`).toISOString().replace('.000Z', '+00:00')
}

function addDays(dateKey, days) {
  return new Date(Date.parse(`${dateKey}T00:00:00Z`) + days * DAY_MS).toISOString().slice(0, 10)
}

export function quarterPeriod(year, quarter) {
  const startMonth = (quarter - 1) * MONTHS_PER_QUARTER + 1
  const endMonth = startMonth + MONTHS_PER_QUARTER - 1
  const startsOn = `${year}-${pad(startMonth)}-01`
  const endsOn = `${year}-${pad(endMonth)}-${pad(lastDayOf(year, endMonth))}`
  return {
    id: year * 10 + quarter,
    key: `${year}-Q${quarter}`,
    cadence: PERIODIC_SETTINGS.cadence,
    label: {
      ar: `${QUARTER_NAMES.ar[quarter - 1]} ${year}`,
      en: `${QUARTER_NAMES.en[quarter - 1]} ${year}`,
    },
    startsOn,
    endsOn,
    startsAt: instantOf(startsOn),
    endsAt: instantOf(addDays(endsOn, 1)),
    isShortened: false,
  }
}

export function periodOfDate(dateKey) {
  const [year, month] = dateKey.split('-').map(Number)
  return quarterPeriod(year, Math.ceil(month / MONTHS_PER_QUARTER))
}

export function periodOfInstant(instant) {
  return periodOfDate(riyadhDateKey(instant))
}

export function followingPeriod(period) {
  return periodOfDate(addDays(period.endsOn, 1))
}

export function withNotice(period) {
  return { ...period, noticeOn: addDays(period.startsOn, -PERIODIC_SETTINGS.noticeDaysBefore) }
}

export function isLive(now = new Date()) {
  return riyadhDateKey(now) >= PERIODIC_SETTINGS.rolloutFrom
}

export function firstPeriod() {
  return periodOfDate(PERIODIC_SETTINGS.rolloutFrom)
}

export function currentPeriod(now = new Date()) {
  return isLive(now) ? periodOfInstant(now) : null
}

export function nextPeriod(now = new Date()) {
  const current = currentPeriod(now)
  return withNotice(current ? followingPeriod(current) : firstPeriod())
}

export function pastPeriods(now = new Date()) {
  const current = currentPeriod(now)
  if (!current) return []
  const periods = []
  for (let period = firstPeriod(); period.key !== current.key; period = followingPeriod(period))
    periods.push(period)
  return periods
}
