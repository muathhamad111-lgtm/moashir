import { TREND_BUCKETS } from '@/constants/dashboardOverview'
import { withLatinNumerals } from '@/utils/numerals'

const OPTIONS = Object.freeze({
  [TREND_BUCKETS.DAY]: { day: 'numeric', month: 'short' },
  [TREND_BUCKETS.WEEK]: { day: 'numeric', month: 'short' },
  [TREND_BUCKETS.MONTH]: { month: 'short', year: 'numeric' },
})
const LONG_DAY = Object.freeze({ day: 'numeric', month: 'long', year: 'numeric' })

// Day keys (YYYY-MM-DD) are calendar days, so they are read and written in UTC.
function dayOf(dayKey) {
  return new Date(`${dayKey}T12:00:00Z`)
}

function formatDay(dayKey, locale, options) {
  return new Intl.DateTimeFormat(withLatinNumerals(locale), { ...options, timeZone: 'UTC' }).format(
    dayOf(dayKey),
  )
}

export function bucketLabel(dayKey, bucket, locale) {
  return dayKey ? formatDay(dayKey, locale, OPTIONS[bucket] ?? OPTIONS[TREND_BUCKETS.DAY]) : ''
}

export function longDayLabel(dayKey, locale) {
  return dayKey ? formatDay(dayKey, locale, LONG_DAY) : ''
}
