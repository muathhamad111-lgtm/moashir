export const OVERVIEW_PERIODS = Object.freeze({
  MONTH: 'month',
  QUARTER: 'quarter',
  YEAR: 'year',
  CUSTOM: 'custom',
})

export const OVERVIEW_PRESETS = Object.freeze([
  OVERVIEW_PERIODS.MONTH,
  OVERVIEW_PERIODS.QUARTER,
  OVERVIEW_PERIODS.YEAR,
  OVERVIEW_PERIODS.CUSTOM,
])

export const DEFAULT_OVERVIEW_PERIOD = OVERVIEW_PERIODS.MONTH

export const OVERVIEW_QUERY = Object.freeze({ PERIOD: 'period', FROM: 'from', TO: 'to' })

export const TREND_BUCKETS = Object.freeze({ DAY: 'day', WEEK: 'week', MONTH: 'month' })

// Fixed series order: the categorical chart colours follow the series, never their rank.
export const ACTIVITY_SERIES = Object.freeze(['entities', 'requests', 'completed'])

export const REVENUE_SERIES = 'collected'

export const PATH_SERIES = Object.freeze(['submitted', 'completed'])

export const OVERVIEW_PATHS = Object.freeze(['self', 'accredited', 'advanced'])

// Ordered from the weakest band to the strongest, then the bands without a grade.
export const GOVERNANCE_BANDS = Object.freeze([
  'below_50',
  'from_50_to_69',
  'from_70_to_84',
  'from_85_to_100',
  'not_reported',
])

export const DELTA_DIRECTIONS = Object.freeze({ UP: 'up', DOWN: 'down', FLAT: 'flat' })

// The overview tiles, in reading order; the first is the featured (brand-filled) tile.
export const OVERVIEW_CARD_ORDER = Object.freeze([
  'payments_collected',
  'new_entities',
  'requests_submitted',
  'assessments_completed',
  'accredited_average_percent',
  'entities_active',
])

export const OVERVIEW_CARD_ICONS = Object.freeze({
  payments_collected: 'sar',
  new_entities: 'building',
  requests_submitted: 'docs',
  assessments_completed: 'check-circle',
  accredited_average_percent: 'pie-chart',
  entities_active: 'users-two',
})
