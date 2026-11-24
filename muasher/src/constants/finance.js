export const TRANSACTION_SORTS = [
  '-created_at',
  'created_at',
  '-total',
  'total',
  '-paid_at',
  'paid_at',
  '-updated_at',
]

export const DEFAULT_TRANSACTION_SORT = '-created_at'

export const FINANCE_MODES = { LIVE: 'live', TEST: 'test' }

export const EXPORT_STATUSES = {
  STREAMED: 'streamed',
  QUEUED: 'queued',
  READY: 'ready',
  FAILED: 'failed',
}

export const EXPORT_POLL_DELAYS_MS = [2000, 4000, 8000, 16000, 30000]

export const PAYMENT_EVENT_SOURCES = [
  'checkout',
  'verification',
  'webhook',
  'expiry',
  'reconciliation',
  'admin',
]

export const PAYMENT_EVENT_RESULTS = [
  'applied',
  'pending',
  'ignored',
  'rejected',
  'unmatched',
  'late_payment',
]

export const SCAN_STATUSES = {
  PENDING: 'pending',
  CLEAN: 'clean',
  INFECTED: 'infected',
  FAILED: 'failed',
}

export const UPLOAD_STATES = {
  IDLE: 'idle',
  PENDING: 'pending',
  CLEAN: 'clean',
  REFUSED: 'refused',
}

export const REFUND_RULES = {
  REASON_MIN: 20,
  REASON_MAX: 1000,
  TRANSFER_REFERENCE_MIN: 3,
  TRANSFER_REFERENCE_MAX: 80,
  TRANSFER_REFERENCE_PATTERN: /^[\p{L}\p{N}\s/._-]+$/u,
  EVIDENCE_EXTENSIONS: ['pdf', 'jpg', 'jpeg', 'png'],
  EVIDENCE_ACCEPT: '.pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png',
  EVIDENCE_MAX_MB: 20,
}

export const REFUSED_FILE_CODES = [
  'file.content_mismatch',
  'file.infected',
  'file.scan_failed',
  'file.password_protected',
  'file.check_failed',
]

export const MANUAL_SETTLE_RULES = {
  STATUSES: ['paid', 'failed'],
  REASON_MIN: 20,
  REASON_MAX: 1000,
  PROVIDER_ID_MAX: 80,
  PROVIDER_ID_PATTERN: /^[A-Za-z0-9_-]+$/,
}

export const FINANCE_SETTINGS_RULES = {
  PRICE_INPUT_PATTERN: /^\d{1,7}(\.\d{1,2})?$/,
  VAT_INPUT_PATTERN: /^\d{1,3}(\.\d{1,2})?$/,
  VAT_MAX: 100,
  REASON_MIN: 10,
  REASON_MAX: 1000,
}

export const ABANDONED_CART_CONTACTED = ['true', 'false']

// «العمليات المالية» filters: the operation type and the statuses an operation can be in.
export const TRANSACTION_TYPES = Object.freeze(['electronic', 'bank_transfer', 'refund'])

export const TRANSACTION_STATUS_FILTERS = Object.freeze([
  'paid',
  'awaiting_payment',
  'under_review',
  'rejected',
  'failed',
  'cancelled',
  'refunded',
])

// A row's status key in the definitions (created and pending both read as «بانتظار الدفع»).
export const STATUS_DEFINITION_KEY = Object.freeze({
  created: 'awaiting_payment',
  pending: 'awaiting_payment',
  'under-review': 'under_review',
})

// Badge colour of a processed payment event's outcome.
export const EVENT_RESULT_VARIANTS = Object.freeze({
  applied: 'success',
  pending: 'warning',
  ignored: 'muted',
  rejected: 'danger',
  unmatched: 'warning',
  late_payment: 'danger',
})

// The finance page's tabs: operations, the bank-transfer approval queue and the gateway events log.
// Badge colour of a ledger status (the statuses the operations filter and the overview cards use).
export const LEDGER_STATUS_VARIANTS = Object.freeze({
  paid: 'success',
  under_review: 'info',
  awaiting_payment: 'warning',
  rejected: 'danger',
  failed: 'danger',
  cancelled: 'muted',
  refunded: 'warning',
})

// The statuses each overview card splits its operations into.
// Each card always shows its main statuses; any other status that occurs is added after them,
// so a card's cells always add up to its total. `awaiting_payment` is the API's own grouping of
// the created and pending orders (both read «بانتظار الدفع»).
export const OVERVIEW_STATUSES = Object.freeze({
  all: ['paid', 'under_review', 'awaiting_payment', 'rejected', 'failed'],
  electronic: ['paid', 'awaiting_payment', 'failed'],
  bank_transfer: ['paid', 'under_review', 'awaiting_payment', 'rejected'],
})

// The operations table's columns; the optional ones are off until a viewer turns them on.
export const TRANSACTION_COLUMNS = Object.freeze({
  FIXED: ['operation', 'actions'],
  TOGGLEABLE: ['entity', 'total', 'payment', 'status', 'createdAt', 'receipt', 'beforeVat'],
  HIDDEN_BY_DEFAULT: ['receipt', 'beforeVat'],
  STORAGE_KEY: 'muasher.finance.columns',
})
