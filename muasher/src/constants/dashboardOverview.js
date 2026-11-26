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

export const OVERVIEW_QUERY = Object.freeze({
  PERIOD: 'period',
  YEAR: 'year',
  MONTH: 'month',
  QUARTER: 'quarter',
  FROM: 'from',
  TO: 'to',
})

export const OVERVIEW_QUARTERS = Object.freeze([1, 2, 3, 4])

// The first month the boards can show (YYYY-MM); mirrors config('moashir.dashboard.first_month').
export const OVERVIEW_FIRST_MONTH = '2026-01'
export const OVERVIEW_FIRST_DAY = `${OVERVIEW_FIRST_MONTH}-01`

// How the API chose the comparison window: the previous full period, the same span of the
// previous period while the chosen one is still running, or the same number of days before.
export const COMPARISON_BASES = Object.freeze({
  FULL: 'previous_full',
  ELAPSED: 'elapsed_span',
  PRECEDING: 'preceding_days',
})

export const TREND_BUCKETS = Object.freeze({ DAY: 'day', WEEK: 'week', MONTH: 'month' })

// Fixed series order: the categorical chart colours follow the series, never their rank.
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

// The assessor board: the open reviews by state (the featured tile), the live alerts, then the
// period tiles in reading order.
export const ASSESSOR_OPEN_STATES = Object.freeze(['to_start', 'in_progress', 'awaiting_entity'])

export const ASSESSOR_ALERT_CARDS = Object.freeze([
  Object.freeze({ key: 'overdue', tone: 'danger', icon: 'warning-triangle' }),
  Object.freeze({ key: 'due_within_week', tone: 'warning', icon: 'clock' }),
])

export const ASSESSOR_CARD_ICONS = Object.freeze({
  completed: 'check-circle',
  average_duration_days: 'calendar',
  average_result_percent: 'pie-chart',
  completion_requests: 'docs',
})

// Period tiles where a fall is an improvement.
export const ASSESSOR_LOWER_IS_BETTER = Object.freeze(['average_duration_days'])

export const ASSESSOR_PATHS = Object.freeze(['accredited', 'advanced'])

// The overview map's measures, each with the formatter that renders its value.
export const GEOGRAPHY_METRICS = Object.freeze([
  Object.freeze({ key: 'entities', format: 'count' }),
  Object.freeze({ key: 'requests', format: 'count' }),
  Object.freeze({ key: 'averagePercent', format: 'percent' }),
  Object.freeze({ key: 'collected', format: 'money' }),
])

export const DEFAULT_GEOGRAPHY_METRIC = 'entities'

// Four shades of one hue, light to dark; an empty region stays neutral.
export const GEOGRAPHY_STEPS = 4
