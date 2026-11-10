import { ROUTE_NAMES as R } from './routeNames'

export const PATH_KEYS = Object.freeze({
  SELF: 'self',
  ACCREDITED: 'accredited',
  AWARD: 'award',
})

export const API_PATHS = Object.freeze({
  [PATH_KEYS.SELF]: 'self',
  [PATH_KEYS.ACCREDITED]: 'accredited',
  [PATH_KEYS.AWARD]: 'advanced',
})

export const PATH_ORDER = Object.freeze([PATH_KEYS.SELF, PATH_KEYS.ACCREDITED, PATH_KEYS.AWARD])

export const PATH_ROUTES = Object.freeze({
  [PATH_KEYS.SELF]: R.PATHS_SELF,
  [PATH_KEYS.ACCREDITED]: R.PATHS_ACCREDITED,
  [PATH_KEYS.AWARD]: R.PATHS_AWARD,
})

export const ENTITY_PATH_ICONS = Object.freeze({
  [PATH_KEYS.SELF]: 'list-check',
  [PATH_KEYS.ACCREDITED]: 'clipboard-check',
  [PATH_KEYS.AWARD]: 'trophy',
})

export const NEXT_STEP_ROUTES = Object.freeze([
  R.PATHS_SELF,
  R.PATHS_ACCREDITED,
  R.PATHS_ACCREDITED_REQUEST,
  R.PATHS_AWARD,
])

export const AVAILABILITY = Object.freeze({
  AVAILABLE: 'available',
  PAUSED: 'paused',
  HIDDEN: 'hidden',
})

export const WINDOW_STATES = Object.freeze({
  OPEN: 'open',
  UPCOMING: 'upcoming',
  CLOSED: 'closed',
  NONE: 'none',
})

export const PERIOD_STATES = Object.freeze({
  ASSIGNED: 'assigned',
  PRE_PERIODIC: 'pre_periodic',
  PENDING: 'pending',
  NONE: 'none',
})

export const PERIOD_STATUSES = Object.freeze({
  ASSESSED: 'assessed',
  IN_PROGRESS: 'in-progress',
  NOT_SUBMITTED: 'not-submitted',
  MISSED: 'missed',
  CLOSED_WITHOUT_RESULT: 'closed-without-result',
  FROZEN: 'frozen',
  NOT_REQUIRED: 'not-required',
})

export const ROW_TYPES = Object.freeze({
  REQUEST: 'request',
  MISSED_PERIOD: 'missed_period',
})

export const REQUEST_TABS = Object.freeze({
  REQUEST: 'request',
  RESULT: 'result',
  FEEDBACK: 'feedback',
  CERTIFICATE: 'certificate',
  TOOLS: 'tools',
  KIT: 'kit',
})

export const REQUEST_TAB_ORDER = Object.freeze(Object.values(REQUEST_TABS))

export const KIT_STATUSES = Object.freeze({
  OFFERED: 'offered',
  PREPARING: 'preparing',
  ACTIVE: 'active',
  CLOSED: 'closed',
})

export const SUBMISSION_REASONS = Object.freeze({
  PERIOD_ALREADY_ASSESSED: 'period_already_assessed',
})

export const PERIOD_CONFLICT_CODE = 'period.already_assessed'

export const PATH_REQUESTS_PER_PAGE = 10

export const PATH_COLUMNS = Object.freeze({
  [PATH_KEYS.SELF]: ['number', 'startedAt', 'submittedAt', 'status', 'result', 'action'],
  [PATH_KEYS.ACCREDITED]: [
    'number',
    'period',
    'payment',
    'status',
    'submittedAt',
    'result',
    'level',
    'kit',
    'action',
  ],
  [PATH_KEYS.AWARD]: [
    'number',
    'cycle',
    'status',
    'submittedAt',
    'result',
    'certificate',
    'action',
  ],
})

export const PATH_CODES = Object.freeze({
  UNAVAILABLE: 'path.unavailable',
  PAYMENT_REQUIRED: 'path.payment_required',
  PAYMENT_NOT_REQUIRED: 'payment.not_required',
  SELF_PAYMENT_PROCESSING: 'self.payment_being_processed',
  AWARD_PAYMENT_PROCESSING: 'advanced.payment_being_processed',
})

export const NEXT_STEP_PATH_KEYS = Object.freeze({ advanced: PATH_KEYS.AWARD })
