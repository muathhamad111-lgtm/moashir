export const PAYMENT_PURPOSE_VALUES = ['accredited', 'bag']

export const PAYMENT_STATUSES = {
  CREATED: 'created',
  PENDING: 'pending',
  PAID: 'paid',
  FAILED: 'failed',
  CANCELLED: 'cancelled',
  REFUNDED: 'refunded',
}

export const PAYMENT_STATUS_VALUES = Object.values(PAYMENT_STATUSES)

export const PAYMENT_STATUS_VARIANTS = {
  created: 'info',
  pending: 'warning',
  paid: 'success',
  failed: 'danger',
  cancelled: 'muted',
  refunded: 'warning',
}

export const OPEN_PAYMENT_STATUSES = [PAYMENT_STATUSES.CREATED, PAYMENT_STATUSES.PENDING]

export const PAYMENT_METHOD_VALUES = ['creditcard', 'mada', 'applepay', 'stcpay']

export const RECEIPT_STATUSES = { ISSUED: 'issued', VOIDED: 'voided' }

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

export const MY_PAYMENT_TABS = Object.freeze({
  ALL: 'all',
  AWAITING: 'awaiting',
  PAID: 'paid',
  CANCELLED: 'cancelled',
})

export const MY_PAYMENT_TAB_STATUSES = Object.freeze({
  [MY_PAYMENT_TABS.ALL]: [],
  [MY_PAYMENT_TABS.AWAITING]: [PAYMENT_STATUSES.CREATED, PAYMENT_STATUSES.PENDING],
  [MY_PAYMENT_TABS.PAID]: [PAYMENT_STATUSES.PAID],
  [MY_PAYMENT_TABS.CANCELLED]: [PAYMENT_STATUSES.CANCELLED, PAYMENT_STATUSES.FAILED],
})

export const PENDING_ORDER_LIMIT = 5
