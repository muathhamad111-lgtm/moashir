import {
  OVERVIEW_PERIODS,
  OVERVIEW_FIRST_DAY,
  OVERVIEW_PRESETS,
} from '@/constants/dashboardOverview'
import { OVERVIEW_SNAPSHOT } from '../fixtures/overviewSnapshot'
import { geographyFor } from './dashboardGeography'
import { comparisonWindow, reportingWindow } from './reportingWindow'

const DAY_MS = 86400000
const MAX_DAYS = 1096
const DAILY_UP_TO = 31
const WEEKLY_UP_TO = 92
const PAYMENT_TOTAL = 1150
const DAY_KEY = /^\d{4}-\d{2}-\d{2}$/
const FIRST_DAY = new Date(`${OVERVIEW_FIRST_DAY}T00:00:00Z`)
const DAILY_CEILING = Object.freeze({ entities: 4, requests: 3, completed: 2 })

export function keyOf(date) {
  return date.toISOString().slice(0, 10)
}

function dayOf(key) {
  return new Date(`${key}T00:00:00Z`)
}

export function riyadhToday() {
  return dayOf(new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(new Date()))
}

export function addDays(date, days) {
  return new Date(date.getTime() + days * DAY_MS)
}

export function daysBetween(start, end) {
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

export function overviewErrors(query) {
  const { period, from, to } = query
  const errors = {}
  const today = keyOf(riyadhToday())
  if (period && !OVERVIEW_PRESETS.includes(period))
    errors.period = ['The selected period is invalid.']
  if (period !== OVERVIEW_PERIODS.CUSTOM) return { ...errors, ...calendarErrors(period, query) }
  if (!DAY_KEY.test(from ?? '')) errors.from = ['The start date is required.']
  else if (from > today) errors.from = ['The start date must be today or earlier.']
  else if (from < OVERVIEW_FIRST_DAY) errors.from = ['The boards have no data before this day.']
  if (!DAY_KEY.test(to ?? '')) errors.to = ['The end date is required.']
  else if (to > today) errors.to = ['The end date must be today or earlier.']
  else if (DAY_KEY.test(from ?? '') && to < from)
    errors.to = ['The end date must be on or after the start date.']
  else if (DAY_KEY.test(from ?? '') && daysBetween(dayOf(from), dayOf(to)) + 1 > MAX_DAYS)
    errors.to = [`A custom period cannot be longer than ${MAX_DAYS} days.`]
  return errors
}

function calendarErrors(period, query) {
  const today = riyadhToday()
  const window = reportingWindow({ ...query, period }, today)
  const fields = ['year', 'month', 'quarter'].filter((field) => query[field])
  if (fields.some((field) => !/^\d+$/.test(query[field])))
    return { [fields[0]]: ['The selected period is invalid.'] }
  if (window.start > today) return { year: ['The period must not be in the future.'] }
  return window.start < FIRST_DAY ? { year: ['The boards have no data before this period.'] } : {}
}

export function windowOf(query) {
  return reportingWindow(query, riyadhToday())
}

export function bucketOf(days) {
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

export function totals(start, end, series) {
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

// One value per bucket of the window, each from the days the bucket covers inside it.
export function bucketValues(start, end, bucket, valueOf) {
  const buckets = []
  const values = []
  for (let cursor = bucketStart(start, bucket); cursor < end; cursor = nextBucket(cursor, bucket)) {
    const from = cursor < start ? start : cursor
    const until = nextBucket(cursor, bucket) < end ? nextBucket(cursor, bucket) : end
    buckets.push(keyOf(cursor))
    values.push(valueOf(from, until))
  }
  return { buckets, values }
}

function trendsOf(start, end, bucket) {
  const seriesOf = (valueOf) => bucketValues(start, end, bucket, valueOf).values
  return {
    bucket,
    buckets: bucketValues(start, end, bucket, () => 0).buckets,
    series: {
      entities: seriesOf((from, until) => totals(from, until, 'entities')),
      requests: seriesOf((from, until) => totals(from, until, 'requests')),
      completed: seriesOf((from, until) => totals(from, until, 'completed')),
      collected: seriesOf((from, until) =>
        (totals(from, until, 'requests') * PAYMENT_TOTAL).toFixed(2),
      ),
    },
  }
}

function card(key, value, previous, format = 'count') {
  return { key, value, previous, format }
}

export function overviewFor(query, levels) {
  const window = windowOf(query)
  const { preset, start, end } = window
  const days = daysBetween(start, end)
  const previous = comparisonWindow(window)
  const now = figures(start, end)
  const before = figures(previous.start, previous.end)
  return {
    period: { from: keyOf(start), to: keyOf(addDays(end, -1)), preset },
    previousPeriod: {
      from: keyOf(previous.start),
      to: keyOf(addDays(previous.end, -1)),
      basis: previous.basis,
    },
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
    geography: geographyFor(now.requests),
    generatedAt: new Date().toISOString(),
  }
}
