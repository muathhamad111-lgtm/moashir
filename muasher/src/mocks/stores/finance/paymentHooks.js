const paidListeners = new Set()
const refundListeners = new Set()
const purchaseChecks = new Set()

export function registerPaymentPaidListener(listener) {
  paidListeners.add(listener)
}

export function registerPaymentRefundedListener(listener) {
  refundListeners.add(listener)
}

export function registerPurchaseEligibilityCheck(check) {
  purchaseChecks.add(check)
}

export function announcePaymentPaid(payment) {
  paidListeners.forEach((listener) => listener(payment))
}

export function announcePaymentRefunded(payment, refund) {
  refundListeners.forEach((listener) => listener(payment, refund))
}

export function assertMayPurchase(entity, purpose, cycle = null) {
  purchaseChecks.forEach((check) => check(entity, purpose, cycle))
}
