import { ROUTE_NAMES } from './routeNames'
import { ACCREDITED_STATUSES as S } from './accredited'

export const LEVEL_TIERS = Object.freeze({
  NONE: 'none',
  PROGRESS: 'progress',
  PARTICIPATION: 'participation',
  BRONZE: 'bronze',
  SILVER: 'silver',
  GOLD: 'gold',
  DIAMOND: 'diamond',
})

const T = LEVEL_TIERS

export const METAL_TIERS = Object.freeze([T.BRONZE, T.SILVER, T.GOLD, T.DIAMOND])

export const TIER_ORDER = Object.freeze([
  T.NONE,
  T.PROGRESS,
  T.PARTICIPATION,
  T.BRONZE,
  T.SILVER,
  T.GOLD,
  T.DIAMOND,
])

export const NEXT_LEVEL_TIERS = Object.freeze([T.PARTICIPATION, T.BRONZE, T.SILVER, T.GOLD])

export const NEUTRAL_TIER_ICONS = Object.freeze({
  [T.NONE]: 'flag',
  [T.PROGRESS]: 'clock',
  [T.PARTICIPATION]: 'certificate',
})

export const OPEN_ACCREDITED_STATUSES = Object.freeze([
  S.DRAFT,
  S.SUBMITTED,
  S.ASSIGNED,
  S.UNDER_REVIEW,
  S.NEEDS_COMPLETION,
])

export const LEVEL_CERTIFICATE_STATUS = 'issued'

export const LEVEL_MINI_CARD = Object.freeze({
  [T.NONE]: { actionKey: 'level.miniCard.start', routeName: ROUTE_NAMES.ASSESSMENT_ACCREDITED },
  [T.PROGRESS]: {
    actionKey: 'level.miniCard.follow',
    routeName: ROUTE_NAMES.ASSESSMENT_ACCREDITED,
  },
  [T.PARTICIPATION]: {
    actionKey: 'level.miniCard.participation',
    routeName: ROUTE_NAMES.CERTIFICATES_INDEX,
  },
  level: {
    actionKey: 'level.miniCard.certificate',
    routeName: ROUTE_NAMES.CERTIFICATES_INDEX,
    icon: 'eye',
  },
})

export const LEVEL_FILTER_VALUES = Object.freeze([
  T.NONE,
  T.PARTICIPATION,
  T.BRONZE,
  T.SILVER,
  T.GOLD,
  T.DIAMOND,
])

export const LEVEL_QUERY_KEY = 'level'

export const LEVEL_BUCKETS = Object.freeze({
  none: T.NONE,
  in_progress: T.PROGRESS,
  participation: T.PARTICIPATION,
  bronze: T.BRONZE,
  silver: T.SILVER,
  gold: T.GOLD,
  diamond: T.DIAMOND,
})

export const EMBLEM_SIZES = Object.freeze(['xl', 'l', 'm', 'list', 's'])
