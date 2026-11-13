export const IBAN_RULES = Object.freeze({
  LENGTH: 24,
  COUNTRY: 'SA',
  GROUP_SIZE: 4,
  BODY_PATTERN: /^SA\d{2}\d{2}[A-Z0-9]{18}$/,
  ALLOWED_PATTERN: /^[A-Z0-9]*$/,
  MOD: 97,
  REMAINDER: 1,
})

export const EXAMPLE_IBAN = 'SA0380000000608010167519'

export const BANK_ACCOUNT_RULES = Object.freeze({
  NAME_MIN: 2,
  NAME_MAX: 120,
  NOTE_MAX: 300,
  ACCOUNT_NUMBER_PATTERN: /^[A-Za-z0-9]{6,30}$/,
  ACCOUNT_NUMBER_MIN: 6,
  ACCOUNT_NUMBER_MAX: 30,
})

export const PROOF_RULES = Object.freeze({
  EXTENSIONS: ['pdf', 'jpg', 'jpeg', 'png'],
  MIME_TYPES: ['application/pdf', 'image/jpeg', 'image/png'],
  ACCEPT: '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png',
  MAX_MB: 20,
  BYTES_PER_MB: 1048576,
})

export const PROOF_STATES = Object.freeze({
  EMPTY: 'empty',
  READY: 'ready',
  UPLOADING: 'uploading',
  SCANNING: 'scanning',
  ACCEPTED: 'accepted',
  WRONG_TYPE: 'wrong-type',
  TOO_LARGE: 'too-large',
  UNSAFE: 'unsafe',
  FAILED: 'failed',
})

export const REFUSED_PROOF_STATES = Object.freeze([
  PROOF_STATES.WRONG_TYPE,
  PROOF_STATES.TOO_LARGE,
  PROOF_STATES.UNSAFE,
  PROOF_STATES.FAILED,
])

export const PROOF_FILE_CODES = Object.freeze({
  'file.content_mismatch': PROOF_STATES.WRONG_TYPE,
  'file.infected': PROOF_STATES.UNSAFE,
  'file.scan_failed': PROOF_STATES.FAILED,
})

export const REJECTION_REASONS = Object.freeze([
  'not_received',
  'amount_mismatch',
  'unreadable',
  'wrong_account',
  'duplicate',
  'other',
])

export const OTHER_REJECTION_REASON = 'other'

export const REJECTION_NOTE = Object.freeze({ MIN: 10, MAX: 500 })

export const SUPER_ADMIN_REASON = Object.freeze({ MIN: 10, MAX: 1000 })

export const BULK_DECISION_MAX = 50

export const TRANSFER_REVIEW_TABS = Object.freeze({
  UNDER_REVIEW: 'under-review',
  PAID: 'paid',
  REJECTED: 'rejected',
  ALL: 'all',
})

export const TRANSFER_SORTS = Object.freeze(['submitted_at', '-submitted_at'])

export const DEFAULT_TRANSFER_SORT = 'submitted_at'

export const TRANSFER_FILTERS = Object.freeze({
  status: TRANSFER_REVIEW_TABS.UNDER_REVIEW,
  purpose: '',
  bankAccount: '',
  mode: '',
  frozen: '0',
})

export const FROZEN_FILTER_VALUES = Object.freeze(['0', '1'])

export const BANK_ACCOUNT_STATES = Object.freeze({
  ACTIVE: 'active',
  INACTIVE: 'inactive',
  ARCHIVED: 'archived',
})

export const TRANSFER_ACCOUNTS_PER_PAGE = 100

export const CHANNEL_CHOICES = Object.freeze({
  ELECTRONIC: 'electronic',
  BANK_TRANSFER: 'bank_transfer',
  BOTH: 'both',
})

export const CHANNEL_ICONS = Object.freeze({
  electronic: 'credit-card',
  bank_transfer: 'sar',
  both: 'docs',
})

export const BANK_TRANSFER_CODES = Object.freeze({
  PENDING_TRANSFERS: 'bank_account.pending_transfers',
  LAST_ACTIVE_ACCOUNT: 'bank_account.last_active_account',
  NOT_SELECTABLE: 'bank_account.not_selectable',
  OPEN_ORDER_EXISTS: 'payment.open_order_exists',
  CHANNEL_NOT_AVAILABLE: 'payment.channel_not_available',
  ENTITY_DELETED: 'entity.deleted',
  ENTITY_NOT_ACTIVE: 'payment.entity_not_active',
  ALREADY_DECIDED: 'state.transition_illegal',
  REASON_REQUIRED: 'reason_required',
})

export const PROOF_ZOOM = Object.freeze({ MIN: 50, MAX: 200, STEP: 25, DEFAULT: 100 })

export const SKIPPED_CODES = Object.freeze([
  'state.transition_illegal',
  'entity.deleted',
  'not_found',
])
