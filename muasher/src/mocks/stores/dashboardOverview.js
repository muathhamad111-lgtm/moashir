import {
  DEFAULT_OVERVIEW_PERIOD,
  OVERVIEW_PERIODS,
  OVERVIEW_PRESETS,
} from '@/constants/dashboardOverview'
import { OVERVIEW_SNAPSHOT } from '../fixtures/overviewSnapshot'

const DAY_MS = 86400000
const MAX_DAYS = 1096
const DAILY_UP_TO = 31
const WEEKLY_UP_TO = 92
const PAYMENT_TOTAL = 1150
const DAY_KEY = /^\d{4}-\d{2}-\d{2}$/
const DAILY_CEILING = Object.freeze({ entities: 4, requests: 3, completed: 2 })

function keyOf(date) {
  return date.toISOString().slice(0, 10)
}

function dayOf(key) {
  return new Date(`${key}T00:00:00Z`)
}

export function riyadhToday() {
  return dayOf(new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(new Date()))
}

function addDays(date, days) {
  return new Date(date.getTime() + days * DAY_MS)
}

function daysBetween(start, end) {
  return Math.round((end - start) / DAY_MS)
}

// A stable, gently changing number per day and series (a slow wave plus a little noise),
// so a period always shows the same, realistic-looking activity.
function daily(key, series) {
  let hash = 7
  for (const char of `${key}:${series}`) hash = (hash * 31 + char.charCodeAt(0)) % 9973
  const day = Math.round(dayOf(key).getTime() / DAY_MS)
  const ceiling = DAILY_CEILING[series]
  const wave = (Math.sin(day / 9 + ceiling) + 1) / 2
  const noise = ((hash % 5) - 2) / 10
  return Math.max(0, Math.round(ceiling * (0.25 + 0.6 * wave + noise)))
}

function presetStart(preset, today) {
  const year = today.getUTCFullYear()
  const month = today.getUTCMonth()
  if (preset === OVERVIEW_PERIODS.QUARTER) return new Date(Date.UTC(year, month - (month % 3), 1))
  if (preset === OVERVIEW_PERIODS.YEAR) return new Date(Date.UTC(year, 0, 1))
  return new Date(Date.UTC(year, month, 1))
}

export function overviewErrors({ period, from, to }) {
  const errors = {}
  const today = keyOf(riyadhToday())
  if (period && !OVERVIEW_PRESETS.includes(period))
    errors.period = ['The selected period is invalid.']
  if (period !== OVERVIEW_PERIODS.CUSTOM) return errors
  if (!DAY_KEY.test(from ?? '')) errors.from = ['The start date is required.']
  else if (from > today) errors.from = ['The start date must be today or earlier.']
  if (!DAY_KEY.test(to ?? '')) errors.to = ['The end date is required.']
  else if (to > today) errors.to = ['The end date must be today or earlier.']
  else if (DAY_KEY.test(from ?? '') && to < from)
    errors.to = ['The end date must be on or after the start date.']
  else if (DAY_KEY.test(from ?? '') && daysBetween(dayOf(from), dayOf(to)) + 1 > MAX_DAYS)
    errors.to = [`A custom period cannot be longer than ${MAX_DAYS} days.`]
  return errors
}

function windowOf({ period, from, to }) {
  const preset = OVERVIEW_PRESETS.includes(period) ? period : DEFAULT_OVERVIEW_PERIOD
  if (preset === OVERVIEW_PERIODS.CUSTOM)
    return { preset, start: dayOf(from), end: addDays(dayOf(to), 1) }
  const today = riyadhToday()
  return { preset, start: presetStart(preset, today), end: addDays(today, 1) }
}

function bucketOf(days) {
  if (days <= DAILY_UP_TO) return 'day'
  return days <= WEEKLY_UP_TO ? 'week' : 'month'
}

function bucketStart(date, bucket) {
  if (bucket === 'week') return addDays(date, -((date.getUTCDay() + 6) % 7))
  if (bucket === 'month') return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1))
  return date
}

function nextBucket(date, bucket) {
  if (bucket === 'week') return addDays(date, 7)
  if (bucket === 'month')
    return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 1))
  return addDays(date, 1)
}

function totals(start, end, series) {
  let sum = 0
  for (let day = start; day < end; day = addDays(day, 1)) sum += daily(keyOf(day), series)
  return sum
}

function figures(start, end) {
  const requests = totals(start, end, 'requests')
  const completed = totals(start, end, 'completed')
  const accredited = Math.ceil(requests * 0.6)
  return {
    newEntities: totals(start, end, 'entities'),
    requests,
    completed,
    accredited,
    collected: (requests * PAYMENT_TOTAL).toFixed(2),
    average: completed ? (70 + (completed % 10)).toFixed(2) : null,
  }
}

function trendsOf(start, end, bucket) {
  const buckets = []
  const series = { entities: [], requests: [], completed: [], collected: [] }
  for (let cursor = bucketStart(start, bucket); cursor < end; cursor = nextBucket(cursor, bucket)) {
    const from = cursor < start ? start : cursor
    const until = nextBucket(cursor, bucket) < end ? nextBucket(cursor, bucket) : end
    buckets.push(keyOf(cursor))
    for (const key of ['entities', 'requests', 'completed'])
      series[key].push(totals(from, until, key))
    series.collected.push((totals(from, until, 'requests') * PAYMENT_TOTAL).toFixed(2))
  }
  return { bucket, buckets, series }
}

function card(key, value, previous, format = 'count') {
  return { key, value, previous, format }
}

export function overviewFor(query, levels) {
  const { preset, start, end } = windowOf(query)
  const days = daysBetween(start, end)
  const previousStart = addDays(start, -days)
  const now = figures(start, end)
  const before = figures(previousStart, start)
  return {
    period: { from: keyOf(start), to: keyOf(addDays(end, -1)), preset },
    previousPeriod: { from: keyOf(previousStart), to: keyOf(addDays(start, -1)) },
    cards: [
      card('new_entities', now.newEntities, before.newEntities),
      card('requests_submitted', now.requests, before.requests),
      card('assessments_completed', now.completed, before.completed),
      card('accredited_average_percent', now.average, before.average, 'percent'),
      card('payments_collected', now.collected, before.collected, 'money'),
      card('entities_active', 37, null),
    ],
    trends: trendsOf(start, end, bucketOf(days)),
    paths: [
      { path: 'self', submitted: null, completed: now.completed - Math.floor(now.completed / 2) },
      { path: 'accredited', submitted: now.accredited, completed: Math.floor(now.completed / 2) },
      { path: 'advanced', submitted: now.requests - now.accredited, completed: null },
    ],
    snapshot: [{ key: 'entities_by_level', data: levels }, ...OVERVIEW_SNAPSHOT],
    generatedAt: new Date().toISOString(),
  }
}
