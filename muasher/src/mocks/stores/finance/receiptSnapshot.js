import { cycleRefOf } from '../award/cycleRef'
import { ENTITY_REFERENCE_PREFIX } from '../../fixtures/entities'
import { financeEntitiesSeed } from '../../fixtures/finance'

export function entityReferenceOf(entityId) {
  return `${ENTITY_REFERENCE_PREFIX}${String(entityId).padStart(6, '0')}`
}

export function entityNameOf(payment) {
  return payment.entityName ?? financeEntitiesSeed[payment.entityId] ?? { ar: '', en: null }
}

export function receiptNumberOf(year, sequence) {
  return `RCP-${year}-${String(sequence).padStart(6, '0')}`
}

export function receiptSnapshotOf(payment) {
  return {
    paymentReference: payment.reference,
    entityReference: entityReferenceOf(payment.entityId),
    entityName: { ...entityNameOf(payment) },
    purpose: payment.purpose,
    awardCycle: payment.purpose === 'award' ? cycleRefOf(payment.awardCycleId) : null,
    channel: payment.channel ?? 'electronic',
    priceBeforeVat: payment.priceBeforeVat,
    vatRate: payment.vatRate,
    vatAmount: payment.vatAmount,
    total: payment.total,
    currency: payment.currency,
    paidAt: payment.paidAt,
    method: payment.method,
    cardLast4: payment.cardLast4,
    providerPaymentId: payment.providerPaymentId,
  }
}
