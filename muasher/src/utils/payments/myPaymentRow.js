import { PAYMENT_CHANNELS, PAYMENT_STATUSES } from '@/constants/payments'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { riyadhYear } from '@/utils/riyadhYear'

const ENTRY_ROUTES = Object.freeze({
  self: ROUTE_NAMES.ASSESSMENT_SELF_PAY,
  accredited: ROUTE_NAMES.ASSESSMENT_ACCREDITED_PAY,
  award: ROUTE_NAMES.ASSESSMENT_ADVANCED_PAY,
  bag: ROUTE_NAMES.BAG_PAY,
})

const PURPOSE_HOMES = Object.freeze({
  self: ROUTE_NAMES.PATHS_SELF,
  accredited: ROUTE_NAMES.ASSESSMENT_ACCREDITED,
  award: ROUTE_NAMES.PATHS_AWARD,
  bag: ROUTE_NAMES.BAG_INDEX,
})

const REQUEST_ROUTES = Object.freeze({
  self: () => ({ name: ROUTE_NAMES.ASSESSMENT_SELF }),
  accredited: (request) => ({
    name: ROUTE_NAMES.ASSESSMENT_ACCREDITED_FORM,
    params: { id: String(request.id) },
  }),
  advanced: (request) => ({
    name: ROUTE_NAMES.ASSESSMENT_ADVANCED_FORM,
    params: { id: String(request.id) },
  }),
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

export function entryRouteOf(payment) {
  const name = ENTRY_ROUTES[payment?.purpose]
  if (!name) return null
  const cycleId = payment.awardCycle?.id ?? null
  return cycleId ? { name, query: { cycle: String(cycleId) } } : { name }
}

export function newRequestRouteOf(payment) {
  return payment.status === PAYMENT_STATUSES.REJECTED ? entryRouteOf(payment) : null
}

export function nextRouteOf(payment) {
  const request = payment?.request
  const opened = request ? REQUEST_ROUTES[request.path]?.(request) : null
  if (opened) return opened
  const name = PURPOSE_HOMES[payment?.purpose]
  return name ? { name } : null
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
