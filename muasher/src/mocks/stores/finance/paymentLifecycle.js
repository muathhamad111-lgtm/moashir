import { FINANCE_YEAR } from '../../fixtures/finance'
import {
  financeState,
  nextEventId,
  nextSequence,
  persistFinanceState,
  receiptOf,
} from './financeState'
import { financeReject } from './financeRejection'
import { receiptNumberOf, receiptSnapshotOf } from './receiptSnapshot'

const TRANSITIONS = {
  electronic: {
    created: ['pending', 'cancelled'],
    pending: ['paid', 'failed', 'cancelled'],
    paid: ['refunded'],
  },
  bank_transfer: {
    'under-review': ['paid', 'rejected', 'cancelled'],
    paid: ['refunded'],
  },
}

const STAMPS = {
  paid: 'paidAt',
  failed: 'failedAt',
  cancelled: 'cancelledAt',
  rejected: 'rejectedAt',
  refunded: 'refundedAt',
}

export const OPEN_STATUSES = ['created', 'pending', 'under-review']
export const OPEN_CARD_STATUSES = ['created', 'pending']
export const CONCLUDED_STATUSES = ['paid', 'failed', 'rejected', 'refunded']

export function isBankTransfer(payment) {
  return payment?.channel === 'bank_transfer'
}

function allowedTargets(payment) {
  return TRANSITIONS[payment.channel ?? 'electronic']?.[payment.status] ?? []
}

export function nowIso() {
  return new Date().toISOString()
}

export function assertTransition(payment, target, verb) {
  if (!allowedTargets(payment).includes(target))
    throw financeReject.illegalTransition(payment.status, verb)
}

export function transition(payment, target, verb) {
  assertTransition(payment, target, verb)
  const at = nowIso()
  payment.status = target
  payment.updatedAt = at
  if (STAMPS[target]) payment[STAMPS[target]] = at
}

export function recordEvent(payment, fields) {
  const at = nowIso()
  financeState().events.push({
    id: nextEventId(),
    paymentId: payment?.id ?? null,
    mode: payment?.mode ?? null,
    signatureValid: null,
    errorCode: null,
    providerPaymentId: payment?.providerPaymentId ?? null,
    createdAt: at,
    processedAt: at,
    ...fields,
  })
}

export function issueReceipt(payment) {
  if (receiptOf(payment.id)) return
  const isTest = payment.mode === 'test'
  financeState().receipts.push({
    id: crypto.randomUUID(),
    paymentId: payment.id,
    number: isTest ? null : receiptNumberOf(FINANCE_YEAR, nextSequence('receipt')),
    status: 'issued',
    isTest,
    issuedAt: payment.paidAt,
    voidedAt: null,
    reissueCount: 0,
    lastReissuedAt: null,
    snapshot: receiptSnapshotOf(payment),
  })
}

export function voidReceipt(payment) {
  const receipt = receiptOf(payment.id)
  if (!receipt) return
  receipt.status = 'voided'
  receipt.voidedAt = nowIso()
}

function providerIdInUse(payment, providerPaymentId) {
  return financeState().payments.some(
    (other) => other.id !== payment.id && other.providerPaymentId === providerPaymentId,
  )
}

export function bindProvider(payment, providerPaymentId) {
  if (payment.providerPaymentId === providerPaymentId) return
  if (payment.providerPaymentId) throw financeReject.bindingMismatch()
  if (providerIdInUse(payment, providerPaymentId)) throw financeReject.providerIdInUse()
  payment.providerPaymentId = providerPaymentId
  payment.boundAt = nowIso()
  if (payment.status === 'created') transition(payment, 'pending', 'bind')
}

export function expireDueOrders(now = Date.now()) {
  let changed = false
  for (const payment of financeState().payments) {
    if (isBankTransfer(payment) || payment.status !== 'created') continue
    if (new Date(payment.expiresAt).getTime() > now) continue
    transition(payment, 'cancelled', 'expire')
    recordEvent(payment, { source: 'expiry', eventType: 'order_expired', result: 'applied' })
    changed = true
  }
  if (changed) persistFinanceState()
}
