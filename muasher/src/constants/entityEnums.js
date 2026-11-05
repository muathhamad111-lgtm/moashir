export const ENTITY_TYPES = ['civil_association', 'civil_foundation', 'waqf']
export const DEVELOPMENT_SECTORS = ['educational', 'social', 'health', 'developmental']
export const ORG_SIZES = ['small', 'medium', 'large']

export const ENTITY_STATUSES = {
  PENDING_VERIFICATION: 'pending-verification',
  ACTIVE: 'active',
  DEACTIVATED: 'deactivated',
}

export const ENTITY_REQUEST_PATHS = ['accredited', 'advanced', 'bag']

export const ENTITY_SORTS = [
  '-created_at',
  'created_at',
  'name',
  '-name',
  '-governance_grade',
  'governance_grade',
]

export const EMAIL_CHANGE_STATUSES = {
  AWAITING_OTP: 'awaiting-otp',
  AWAITING_APPROVAL: 'awaiting-approval',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  APPLIED: 'applied',
  CANCELLED: 'cancelled',
  EXPIRED: 'expired',
}

export const EMAIL_CHANGE_POLICIES = {
  REQUIRES_APPROVAL: 'requires_approval',
  IMMEDIATE_NOTIFY: 'immediate_notify',
}

export const PRIVACY_REQUEST_KINDS = ['access', 'correction', 'deletion']

export const PRIVACY_REQUEST_STATUSES = {
  OPEN: 'open',
  FULFILLED: 'fulfilled',
  REJECTED: 'rejected',
}

export const ERASURE_KEPT_RECORDS = [
  'payments_receipts_refunds',
  'audit_log',
  'anonymized_results',
  'certificate_verification_records',
  'consent_records',
  'security_events',
]

export const GOVERNANCE_GRADE = { MIN: 0, MAX: 100, DECIMALS: 2 }
export const FOUNDING_YEAR_MIN = 1900
export const PASSWORD_LENGTH = { MIN: 12, MAX: 128 }
export const REASON_LENGTH = { MIN: 10, MAX: 1000, PERMANENT_MIN: 20 }
export const PRIVACY_DETAILS_LENGTH = { MIN: 10, MAX: 2000 }
export const PHONE_PATTERN = /^(05\d{8}|\+9665\d{8})$/

export const UNIFIED_NUMBER_LENGTH = 10
export const UNIFIED_NUMBER_PATTERN = /^7\d{9}$/
