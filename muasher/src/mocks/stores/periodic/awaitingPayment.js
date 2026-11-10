import { financeState } from '../finance/financeState'
import { expireDueOrders } from '../finance/paymentLifecycle'

const OPEN_ORDER = ['created', 'pending', 'under-review']

function matches(payment, entityId, purpose, cycleId) {
  return (
    payment.entityId === entityId &&
    payment.purpose === purpose &&
    (cycleId === null || payment.awardCycleId === cycleId)
  )
}

export function paymentSummaryOf(order) {
  return order
    ? {
        paymentId: order.id,
        reference: order.reference,
        channel: order.channel ?? 'electronic',
        status: order.status,
        expiresAt: order.channel === 'bank_transfer' ? null : order.expiresAt,
      }
    : null
}

export function latestPaymentOf(entityId, purpose, cycleId = null) {
  return (
    financeState()
      .payments.filter((payment) => matches(payment, entityId, purpose, cycleId))
      .sort((left, right) => (left.createdAt < right.createdAt ? 1 : -1))[0] ?? null
  )
}

export function openOrderSummaryOf(entityId, purpose, cycleId = null) {
  expireDueOrders()
  const order = financeState().payments.find(
    (payment) =>
      matches(payment, entityId, purpose, cycleId) && OPEN_ORDER.includes(payment.status),
  )
  return paymentSummaryOf(order)
}

export function awaitingPaymentOf(entityId) {
  return openOrderSummaryOf(entityId, 'accredited')
}
