import { PAYMENT_STATUSES } from '@/constants/payments'
import { riyadhYear } from '@/utils/riyadhYear'

export function paymentReferenceOf(payment) {
  return payment.status === PAYMENT_STATUSES.PAID && payment.request?.number
    ? payment.request.number
    : payment.reference
}

export function paymentYearOf(payment) {
  return riyadhYear(payment.paidAt ?? payment.createdAt)
}

export function paymentDateOf(payment) {
  return payment.paidAt ?? payment.createdAt
}

export function canDownloadReceipt(payment) {
  return payment.status === PAYMENT_STATUSES.PAID && Boolean(payment.receipt)
}

export function canCompletePayment(payment, now = Date.now()) {
  return (
    payment.status === PAYMENT_STATUSES.CREATED &&
    Boolean(payment.expiresAt) &&
    new Date(payment.expiresAt).getTime() > now
  )
}
