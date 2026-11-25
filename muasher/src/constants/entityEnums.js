export const ENTITY_TYPES = ['civil_association', 'civil_foundation', 'waqf']
export const DEVELOPMENT_SECTORS = ['educational', 'social', 'health', 'developmental']
export const ORG_SIZES = ['small', 'medium', 'large']

export const ENTITY_STATUSES = {
  PENDING_VERIFICATION: 'pending-verification',
  PENDING_REVIEW: 'pending-review',
  ACTIVE: 'active',
  DEACTIVATED: 'deactivated',
  REJECTED: 'rejected',
}

export const ENTITY_REQUEST_PATHS = ['accredited', 'advanced', 'bag']

export const ENTITY_SORTS = [
  '-created_at',
  'created_at',
  'name',
  '-name',
  '-governance_grade',
  'governance_grade',
  '-level',
  'level',
  '-last_assessed_at',
  'last_assessed_at',
  '-last_assessment_percent',
  'last_assessment_percent',
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

export const PROFILE_CHANGE_STATUSES = {
  PENDING: 'pending',
  APPROVED: 'approved',
  PARTIALLY_APPROVED: 'partially-approved',
  REJECTED: 'rejected',
  CANCELLED: 'cancelled',
}

export const PROFILE_CHANGE_VERDICTS = { APPROVED: 'approved', REJECTED: 'rejected' }

export const PROFILE_CHANGE_FILES = {
  LOGO: 'logo',
  VAT_CERTIFICATE: 'vat_certificate',
  LICENSE_CERTIFICATE: 'license_certificate',
  AUTHORIZATION_LETTER: 'authorization_letter',
}

/**
 * Items that only make sense together: a city belongs to its region, a VAT number to a registration, a new
 * authorized person to the authorization letter that names them.
 */
export const PROFILE_CHANGE_COUPLED = Object.freeze([
  ['region_id', 'city_id'],
  ['vat_registered', 'vat_number'],
  ['has_governance_grade', 'governance_grade'],
  ['authorized_person_name', 'authorization_letter'],
])

/** An item that can only be approved when its partner is in the same request. */
export const PROFILE_CHANGE_NEEDS = Object.freeze({
  authorized_person_name: 'authorization_letter',
})

export const EMAIL_CHANGE_POLICIES = {
  REQUIRES_APPROVAL: 'requires_approval',
  IMMEDIATE_NOTIFY: 'immediate_notify',
}

export const ERASURE_KEPT_RECORDS = [
  'payments_receipts_refunds',
  'audit_log',
  'anonymized_results',
  'certificate_verification_records',
  'consent_records',
  'security_events',
]

export const GOVERNANCE_GRADE = { MIN: 0, MAX: 100, INTEGER_DIGITS: 3, DECIMALS: 2 }
export const FOUNDING_YEAR_MIN = 1900

export const EARLIEST_FOUNDING_DATE = '1900-01-01'
export const PASSWORD_LENGTH = { MIN: 12, MAX: 128 }
export const REASON_LENGTH = { MIN: 10, MAX: 1000, PERMANENT_MIN: 20 }
export const PHONE_PATTERN = /^(05\d{8}|\+9665\d{8})$/

export const UNIFIED_NUMBER_LENGTH = 10
export const UNIFIED_NUMBER_PATTERN = /^\d{10}$/
