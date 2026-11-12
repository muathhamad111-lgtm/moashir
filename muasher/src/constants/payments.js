export const PAYMENT_PURPOSES = Object.freeze({
  ACCREDITED: 'accredited',
  BAG: 'bag',
  SELF: 'self',
  AWARD: 'award',
})

export const PAYMENT_PURPOSE_VALUES = Object.values(PAYMENT_PURPOSES)

export const CART_PURPOSE_VALUES = Object.freeze([
  PAYMENT_PURPOSES.ACCREDITED,
  PAYMENT_PURPOSES.BAG,
])

export const REQUEST_PURPOSE_VALUES = Object.freeze([
  PAYMENT_PURPOSES.SELF,
  PAYMENT_PURPOSES.ACCREDITED,
  PAYMENT_PURPOSES.AWARD,
])

export const PAYMENT_STATUSES = {
  CREATED: 'created',
  PENDING: 'pending',
  UNDER_REVIEW: 'under-review',
  PAID: 'paid',
  FAILED: 'failed',
  REJECTED: 'rejected',
  CANCELLED: 'cancelled',
  REFUNDED: 'refunded',
}

export const PAYMENT_STATUS_VALUES = Object.values(PAYMENT_STATUSES)

export const PAYMENT_STATUS_VARIANTS = {
  created: 'info',
  pending: 'warning',
  'under-review': 'info',
  paid: 'success',
  failed: 'danger',
  rejected: 'danger',
  cancelled: 'muted',
  refunded: 'warning',
}

export const PAYMENT_CHANNELS = Object.freeze({
  ELECTRONIC: 'electronic',
  BANK_TRANSFER: 'bank_transfer',
})

export const PAYMENT_CHANNEL_VALUES = Object.values(PAYMENT_CHANNELS)

export const OPEN_PAYMENT_STATUSES = [PAYMENT_STATUSES.CREATED, PAYMENT_STATUSES.PENDING]

export const PAYMENT_METHOD_VALUES = ['creditcard', 'mada', 'applepay', 'stcpay']

export const RECEIPT_STATUSES = { ISSUED: 'issued', VOIDED: 'voided' }

export const TEST_MODE_AUDIENCES = Object.freeze({ ENTITY: 'entity', STAFF: 'staff' })

export const TEST_MODE_ICONS = Object.freeze({
  [TEST_MODE_AUDIENCES.ENTITY]: 'info-circle',
  [TEST_MODE_AUDIENCES.STAFF]: 'flask',
})

export const CHECKOUT_PATTERNS = { INTENT: 'intent', FORM: 'form' }

export const VERIFICATION_RESULTS = {
  APPLIED: 'applied',
  PENDING: 'pending',
  IGNORED: 'ignored',
  LATE_PAYMENT: 'late_payment',
}

export const IDEMPOTENCY_HEADER = 'Idempotency-Key'

export const PROVIDER_PAYMENT_ID_PATTERN = /^[A-Za-z0-9_-]{1,80}$/

export const VERIFY_POLL_DELAYS_MS = [3000, 6000, 12000, 24000]

export const COUNTDOWN_TICK_MS = 1000

export const COUNTDOWN_WARNING_SECONDS = 120

export const MY_PAYMENT_STATUS_GROUPS = Object.freeze({
  awaiting: [PAYMENT_STATUSES.CREATED, PAYMENT_STATUSES.PENDING, PAYMENT_STATUSES.UNDER_REVIEW],
  paid: [PAYMENT_STATUSES.PAID],
  cancelled: [PAYMENT_STATUSES.CANCELLED, PAYMENT_STATUSES.FAILED, PAYMENT_STATUSES.REJECTED],
  refunded: [PAYMENT_STATUSES.REFUNDED],
})

export const PENDING_ORDER_LIMIT = 5
