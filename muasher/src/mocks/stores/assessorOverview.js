// Mirrors backend BuildAssessorOverview: one assessor's reviews in a reporting period and the one
// before it. The mock draws a stable daily rhythm of completed reviews per assessor, as the
// executive overview mock does for the platform.
import {
  addDays,
  bucketOf,
  bucketValues,
  daysBetween,
  keyOf,
  totals,
  windowOf,
} from './dashboardOverview'

const REVIEWS = 'completed'
const ADVANCED_EVERY = 4
const REQUESTS_EVERY = 3
const LEVELS = ['diamond', 'gold', 'silver', 'bronze', 'none']
const LEVEL_SHARES = [0.1, 0.25, 0.35, 0.2, 0.1]

// The same rhythm for every assessor would hide whose board it is: each one is shifted by a few days.
function reviewsBetween(user, start, end) {
  const shift = (user.id % 7) - 3
  return totals(addDays(start, shift), addDays(end, shift), REVIEWS)
}

function figures(user, start, end) {
  const completed = reviewsBetween(user, start, end)
  const advanced = Math.floor(completed / ADVANCED_EVERY)
  return {
    completed,
    advanced,
    accredited: completed - advanced,
    averageDays: completed ? (9 + (completed % 8) * 0.9).toFixed(1) : null,
    averagePercent: completed ? (68 + (completed % 15)).toFixed(2) : null,
    completionRequests: Math.floor(completed / REQUESTS_EVERY),
  }
}

function levelsOf(accredited) {
  const counts = LEVEL_SHARES.map((share) => Math.floor(accredited * share))
  counts[2] += accredited - counts.reduce((sum, count) => sum + count, 0)
  return LEVELS.map((key, index) => ({ key, count: counts[index] }))
}

function card(key, value, previous, format = 'count') {
  return { key, value, previous, format }
}

export function assessorOverviewFor(user, query) {
  const { preset, start, end } = windowOf(query)
  const days = daysBetween(start, end)
  const previousStart = addDays(start, -days)
  const now = figures(user, start, end)
  const before = figures(user, previousStart, start)
  const bucket = bucketOf(days)
  const trend = bucketValues(start, end, bucket, (from, until) => reviewsBetween(user, from, until))
  return {
    period: { from: keyOf(start), to: keyOf(addDays(end, -1)), preset },
    previousPeriod: { from: keyOf(previousStart), to: keyOf(addDays(start, -1)) },
    cards: [
      card('completed', now.completed, before.completed),
      card('average_duration_days', now.averageDays, before.averageDays, 'days'),
      card('average_result_percent', now.averagePercent, before.averagePercent, 'percent'),
      card('completion_requests', now.completionRequests, before.completionRequests),
    ],
    trends: { bucket, buckets: trend.buckets, series: { completed: trend.values } },
    paths: [
      { path: 'accredited', completed: now.accredited },
      { path: 'advanced', completed: now.advanced },
    ],
    levels: levelsOf(now.accredited),
    generatedAt: new Date().toISOString(),
  }
}
