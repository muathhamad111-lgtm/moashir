import { PAYMENT_CHANNELS, PAYMENT_STATUSES } from '@/constants/payments'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { riyadhYear } from '@/utils/riyadhYear'

const ENTRY_ROUTES = Object.freeze({
  accredited: ROUTE_NAMES.ASSESSMENT_ACCREDITED_PAY,
  bag: ROUTE_NAMES.BAG_PAY,
})

const TRANSFER_STATES = [PAYMENT_STATUSES.UNDER_REVIEW, PAYMENT_STATUSES.REJECTED]

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

export function isBankTransfer(payment) {
  return payment?.channel === PAYMENT_CHANNELS.BANK_TRANSFER
}

export function entryRouteOf(purpose) {
  return ENTRY_ROUTES[purpose] ? { name: ENTRY_ROUTES[purpose] } : null
}

export function newRequestRouteOf(payment) {
  return payment.status === PAYMENT_STATUSES.REJECTED ? entryRouteOf(payment.purpose) : null
}

export function showsTransferState(payment) {
  return isBankTransfer(payment) && TRANSFER_STATES.includes(payment.status)
}

export function canCompletePayment(payment, now = Date.now()) {
  return (
    !isBankTransfer(payment) &&
    payment.status === PAYMENT_STATUSES.CREATED &&
    Boolean(payment.expiresAt) &&
    new Date(payment.expiresAt).getTime() > now
  )
}
