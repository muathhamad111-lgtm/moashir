import { financeEntityEmails } from '../../fixtures/finance'
import { findFile, findPayment, receiptOf, refundOf } from './financeState'
import { entityNameOf, entityReferenceOf } from './receiptSnapshot'

function pricingOf(payment) {
  return {
    priceBeforeVat: payment.priceBeforeVat,
    vatRate: payment.vatRate,
    vatAmount: payment.vatAmount,
    total: payment.total,
    currency: payment.currency,
  }
}

function receiptSummaryOf(receipt) {
  if (!receipt) return null
  return { id: receipt.id, number: receipt.number, status: receipt.status, isTest: receipt.isTest, issuedAt: receipt.issuedAt }
}

export function ownPaymentResource(payment) {
  return {
    id: payment.id,
    reference: payment.reference,
    purpose: payment.purpose,
    status: payment.status,
    isTest: payment.mode === 'test',
    pricing: pricingOf(payment),
    method: payment.method,
    cardLast4: payment.cardLast4,
    failureReason: payment.failureReason,
    expiresAt: payment.expiresAt,
    createdAt: payment.createdAt,
    paidAt: payment.paidAt,
    failedAt: payment.failedAt,
    cancelledAt: payment.cancelledAt,
    refundedAt: payment.refundedAt,
    receipt: receiptSummaryOf(receiptOf(payment.id)),
  }
}

export function receiptResource(receipt) {
  return {
    id: receipt.id,
    number: receipt.number,
    status: receipt.status,
    isTest: receipt.isTest,
    issuedAt: receipt.issuedAt,
    voidedAt: receipt.voidedAt,
    reissueCount: receipt.reissueCount,
    lastReissuedAt: receipt.lastReissuedAt,
    snapshot: { ...receipt.snapshot, entityName: { ...receipt.snapshot.entityName } },
  }
}

export function transactionResource(payment) {
  return {
    id: payment.id,
    reference: payment.reference,
    receiptNumber: receiptOf(payment.id)?.number ?? null,
    purpose: payment.purpose,
    status: payment.status,
    isTest: payment.mode === 'test',
    entity: {
      id: payment.entityId,
      reference: entityReferenceOf(payment.entityId),
      name: { ...entityNameOf(payment) },
      email: financeEntityEmails[payment.entityId] ?? null,
    },
    method: payment.method,
    cardLast4: payment.cardLast4,
    pricing: pricingOf(payment),
    latePaid: payment.latePaidAt !== null,
    manuallySettled: payment.manuallySettledAt !== null,
    hasRefund: refundOf(payment.id) !== null,
    createdAt: payment.createdAt,
    paidAt: payment.paidAt,
    refundedAt: payment.refundedAt,
    updatedAt: payment.updatedAt,
  }
}

export function refundResource(refund) {
  const payment = findPayment(refund.paymentId)
  const evidence = findFile(refund.evidenceFileId)
  return {
    id: refund.id,
    payment: { id: payment.id, reference: payment.reference },
    status: refund.status,
    amount: refund.amount,
    currency: refund.currency,
    refundedOn: refund.refundedOn,
    transferReference: refund.transferReference,
    reason: refund.reason,
    recordedBy: { id: refund.recordedBy.id, name: refund.recordedBy.name },
    evidence: {
      id: evidence.id,
      name: evidence.name,
      mimeType: evidence.mimeType,
      sizeBytes: evidence.sizeBytes,
      scanStatus: evidence.scanStatus,
    },
    createdAt: refund.createdAt,
  }
}

export function transactionDetailResource(payment) {
  const receipt = receiptOf(payment.id)
  const refund = refundOf(payment.id)
  return {
    ...transactionResource(payment),
    providerPaymentId: payment.providerPaymentId,
    failureReason: payment.failureReason,
    failureCode: payment.failureCode,
    failureMessage: payment.failureMessage,
    expiresAt: payment.expiresAt,
    verifiedAt: payment.verifiedAt,
    failedAt: payment.failedAt,
    cancelledAt: payment.cancelledAt,
    latePaidAt: payment.latePaidAt,
    manuallySettledAt: payment.manuallySettledAt,
    receipt: receipt ? receiptResource(receipt) : null,
    refund: refund ? refundResource(refund) : null,
  }
}

export function maskedReference(reference) {
  if (reference === null) return null
  return reference.length <= 6 ? reference : `…${reference.slice(-6)}`
}

export function paymentEventResource(event) {
  const payment = event.paymentId ? findPayment(event.paymentId) : null
  return {
    id: event.id,
    payment: payment ? { id: payment.id, reference: payment.reference } : null,
    source: event.source,
    eventType: event.eventType,
    mode: event.mode,
    signatureValid: event.signatureValid,
    result: event.result,
    errorCode: event.errorCode,
    providerRef: maskedReference(event.providerPaymentId),
    createdAt: event.createdAt,
    processedAt: event.processedAt,
  }
}

function maskedEmail(email) {
  const [local, domain] = email.split('@')
  return `${local.slice(0, 1)}***@${domain}`
}

export function abandonedCartResource(cart) {
  return {
    id: cart.id,
    entity: {
      id: cart.entityId,
      reference: entityReferenceOf(cart.entityId),
      name: { ...entityNameOf({ entityId: cart.entityId }) },
    },
    email: cart.email ? maskedEmail(cart.email) : null,
    marketingConsent: cart.marketingConsent,
    purpose: cart.purpose,
    startedAt: cart.startedAt,
    lastSeenAt: cart.lastSeenAt,
    contactedAt: cart.contactedAt,
  }
}

export function financeExportResource(record) {
  return {
    id: record.id,
    status: record.status,
    rowCount: record.rowCount,
    createdAt: record.createdAt,
    completedAt: record.completedAt,
  }
}
