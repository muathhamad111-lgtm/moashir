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

export const REFUSED_FILE_CODES = ['file.content_mismatch', 'file.infected', 'file.scan_failed']

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
