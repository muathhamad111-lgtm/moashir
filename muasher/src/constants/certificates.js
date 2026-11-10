export const CERTIFICATE_TABS = Object.freeze({
  VALID: 'valid',
  ISSUING: 'in_progress',
  PREVIOUS: 'previous',
})

export const CERTIFICATE_TAB_STATUSES = Object.freeze({
  [CERTIFICATE_TABS.VALID]: ['issued', 'suspended'],
  [CERTIFICATE_TABS.PREVIOUS]: ['revoked', 'superseded'],
})

export const CERTIFICATE_STATUS_TONES = Object.freeze({
  issued: 'success',
  suspended: 'warning',
  revoked: 'danger',
  superseded: 'info',
})

export const CERTIFICATE_LIST_LIMIT = 50

export const VERIFICATION_RESULTS = Object.freeze({
  VALID: 'valid',
  UNDER_REVIEW: 'under_review',
  EXPIRED: 'expired',
  REVOKED: 'revoked',
  SUPERSEDED: 'superseded',
  NOT_FOUND: 'not_found',
  UNVERIFIABLE: 'unverifiable',
})

export const VERIFICATION_CODE_PATTERN = /^[0-9A-Za-z-\s]{4,60}$/

export const CERTIFICATE_LEVELS = Object.freeze(['bronze', 'silver', 'gold', 'diamond'])

export const LEVEL_THRESHOLD_PATTERN = /^\d{1,3}(\.\d{1,2})?$/
