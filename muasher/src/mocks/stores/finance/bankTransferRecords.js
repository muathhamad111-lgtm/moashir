import { riyadhDateKey } from '@/utils/datetime'
import { financeEntityEmails } from '../../fixtures/finance'
import { findEntity } from '../entitiesStore'
import { financeState, findBankAccount, findFile, receiptOf, transferOf } from './financeState'
import { entityNameOf, entityReferenceOf } from './receiptSnapshot'

const UNDER_REVIEW = 'under-review'

function pricingOf(payment) {
  return {
    priceBeforeVat: payment.priceBeforeVat,
    vatRate: payment.vatRate,
    vatAmount: payment.vatAmount,
    total: payment.total,
    currency: payment.currency,
  }
}

export function isFrozenEntity(entityId) {
  return Boolean(findEntity(entityId)?.deletedAt)
}

export function payeeOf(account) {
  return {
    id: account.id,
    bankName: { ...account.bankName },
    accountHolderName: { ...account.accountHolderName },
    iban: account.iban,
  }
}

function proofOf(file) {
  return { id: file.id, name: file.name, mimeType: file.mimeType, sizeBytes: file.sizeBytes }
}

export function transferPayments() {
  return financeState().payments.filter((payment) => payment.channel === 'bank_transfer')
}

export function ownTransferResource(payment) {
  const transfer = transferOf(payment.id)
  if (!transfer) return null
  return {
    bankAccount: payeeOf(findBankAccount(transfer.bankAccountId)),
    proof: proofOf(findFile(transfer.proofFileId)),
    submittedAt: transfer.submittedAt,
    reviewedAt: transfer.reviewedAt,
    rejectionReason: transfer.rejectionReason,
    reviewNote: transfer.reviewNote,
  }
}

function proofHashOf(payment) {
  const transfer = transferOf(payment.id)
  return transfer ? (findFile(transfer.proofFileId)?.sha256 ?? null) : null
}

export function duplicatesOf(payment) {
  const hash = proofHashOf(payment)
  if (!hash) return []
  return transferPayments().filter(
    (other) => other.id !== payment.id && proofHashOf(other) === hash,
  )
}

function receiptSummaryOf(payment) {
  const receipt = receiptOf(payment.id)
  if (!receipt) return null
  return {
    id: receipt.id,
    number: receipt.number,
    status: receipt.status,
    isTest: receipt.isTest,
    issuedAt: receipt.issuedAt,
  }
}

function staffAccountOf(account) {
  return {
    ...payeeOf(account),
    isActive: account.isActive,
    isArchived: account.archivedAt !== null,
  }
}

export function transferDetailsOf(payment) {
  const transfer = transferOf(payment.id)
  if (!transfer) return null
  const file = findFile(transfer.proofFileId)
  return {
    bankAccount: staffAccountOf(findBankAccount(transfer.bankAccountId)),
    proof: { ...proofOf(file), scanStatus: file.scanStatus },
    submittedAt: transfer.submittedAt,
    reviewedAt: transfer.reviewedAt,
    reviewedBy: transfer.reviewedBy ? { ...transfer.reviewedBy } : null,
    rejectionReason: transfer.rejectionReason,
    reviewNote: transfer.reviewNote,
  }
}

export function staffTransferResource(payment, { detail = false } = {}) {
  const duplicates = duplicatesOf(payment)
  return {
    id: payment.id,
    reference: payment.reference,
    purpose: payment.purpose,
    status: payment.status,
    isTest: payment.mode === 'test',
    pricing: pricingOf(payment),
    entity: {
      id: payment.entityId,
      reference: entityReferenceOf(payment.entityId),
      name: { ...entityNameOf(payment) },
      email: financeEntityEmails[payment.entityId] ?? null,
      isFrozen: isFrozenEntity(payment.entityId),
    },
    ...transferDetailsOf(payment),
    hasDuplicateProof: duplicates.length > 0,
    duplicateProofs: detail
      ? duplicates.map((other) => ({
          paymentId: other.id,
          reference: other.reference,
          status: other.status,
        }))
      : null,
    createdAt: payment.createdAt,
    paidAt: payment.paidAt,
    rejectedAt: payment.rejectedAt ?? null,
    receipt: receiptSummaryOf(payment),
  }
}

export function pendingTransfersOn(accountId) {
  return transferPayments()
    .filter(
      (payment) =>
        payment.status === UNDER_REVIEW && transferOf(payment.id)?.bankAccountId === accountId,
    )
    .map((payment) => ({
      paymentId: payment.id,
      reference: payment.reference,
      entityName: { ...entityNameOf(payment) },
      total: payment.total,
      currency: payment.currency,
      submittedAt: transferOf(payment.id).submittedAt,
    }))
}

export function awaitingReviewCount() {
  return transferPayments().filter(
    (payment) => payment.status === UNDER_REVIEW && !isFrozenEntity(payment.entityId),
  ).length
}

function decidedToday() {
  const today = riyadhDateKey(new Date())
  return transferPayments()
    .filter((payment) => !isFrozenEntity(payment.entityId))
    .map((payment) => transferOf(payment.id))
    .filter((transfer) => transfer?.reviewedAt && riyadhDateKey(transfer.reviewedAt) === today)
}

export function queueCounts() {
  const decisions = decidedToday()
  return {
    underReview: awaitingReviewCount(),
    approvedToday: decisions.filter((transfer) => !transfer.rejectionReason).length,
    rejectedToday: decisions.filter((transfer) => Boolean(transfer.rejectionReason)).length,
  }
}
