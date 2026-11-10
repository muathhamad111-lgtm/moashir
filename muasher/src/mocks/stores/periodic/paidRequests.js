import { allAssessments } from '../assessments/assessmentsStore'
import { financeState } from '../finance/financeState'

const OPEN_ORDER = ['created', 'pending', 'under-review']

export function requestOfPayment(paymentId) {
  return allAssessments().find((record) => record.paymentId === paymentId) ?? null
}

function ofPurpose(payment, entityId, purpose, cycleId) {
  return (
    payment.entityId === Number(entityId) &&
    payment.purpose === purpose &&
    (cycleId === null || payment.awardCycleId === cycleId)
  )
}

export function openOrderFor(entityId, purpose, cycleId = null) {
  return (
    financeState().payments.find(
      (payment) =>
        ofPurpose(payment, entityId, purpose, cycleId) && OPEN_ORDER.includes(payment.status),
    ) ?? null
  )
}

export function paidWithoutRequest(entityId, purpose, cycleId = null) {
  return financeState().payments.some(
    (payment) =>
      ofPurpose(payment, entityId, purpose, cycleId) &&
      payment.status === 'paid' &&
      !requestOfPayment(payment.id),
  )
}
