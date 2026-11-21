export const PRICE_AVAILABILITY = Object.freeze({
  AVAILABLE: 'available',
  PAUSED: 'paused',
  HIDDEN: 'hidden',
})

export const PRICE_AVAILABILITY_VALUES = Object.freeze(Object.values(PRICE_AVAILABILITY))

export const PRICE_VERSION_STATES = Object.freeze({
  IN_FORCE: 'in_force',
  SCHEDULED: 'scheduled',
  SUPERSEDED: 'superseded',
  CANCELLED: 'cancelled',
})

export const PRICE_CODES = Object.freeze({ STALE_VERSION: 'prices.stale_version' })

export const PRICE_HISTORY_PER_PAGE = 10

export const PRICE_AVAILABILITY_VARIANTS = Object.freeze({
  [PRICE_AVAILABILITY.AVAILABLE]: 'success',
  [PRICE_AVAILABILITY.PAUSED]: 'warning',
  [PRICE_AVAILABILITY.HIDDEN]: 'muted',
})

export const PRICE_STATE_VARIANTS = Object.freeze({
  [PRICE_VERSION_STATES.IN_FORCE]: 'success',
  [PRICE_VERSION_STATES.SCHEDULED]: 'info',
  [PRICE_VERSION_STATES.SUPERSEDED]: 'muted',
  [PRICE_VERSION_STATES.CANCELLED]: 'danger',
})
