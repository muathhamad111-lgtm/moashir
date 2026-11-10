import { toPathPricing } from './paths'
import { PAYMENT_CHANNEL_VALUES } from '@/constants/payments'
import { BANK_ACCOUNT_STATES } from '@/constants/bankTransfer'
import { toRequestStatus } from './accreditedStatus'

const ACCOUNT_STATE_VALUES = Object.values(BANK_ACCOUNT_STATES)

function bilingual(raw) {
  if (!raw) return null
  return { ar: raw.ar ?? '', en: raw.en ?? '' }
}

function knownChannels(raw) {
  return (Array.isArray(raw) ? raw : []).filter((channel) =>
    PAYMENT_CHANNEL_VALUES.includes(channel),
  )
}

function count(value) {
  const number = Number(value ?? 0)
  return Number.isFinite(number) ? number : 0
}

function actorOf(raw) {
  return raw ? { id: raw.id ?? null, name: raw.name ?? null } : null
}

export function toPaymentChannels(raw = {}) {
  return {
    channels: knownChannels(raw.channels),
    availableChannels: knownChannels(raw.availableChannels),
    gatewayReady: raw.gatewayReady === true,
    activeBankAccountsCount: count(raw.activeBankAccountsCount),
    reviewersCount: count(raw.reviewersCount),
  }
}

export function toPayee(raw = {}) {
  return {
    id: raw.id ?? null,
    bankName: bilingual(raw.bankName) ?? { ar: '', en: '' },
    accountHolderName: bilingual(raw.accountHolderName) ?? { ar: '', en: '' },
    iban: raw.iban ?? '',
  }
}

export function toPayerBankAccount(raw = {}) {
  return {
    ...toPayee(raw),
    accountNumber: raw.accountNumber ?? null,
    payerNote: bilingual(raw.payerNote),
  }
}

export function toBankAccount(raw = {}) {
  return {
    ...toPayerBankAccount(raw),
    displayOrder: count(raw.displayOrder),
    isActive: raw.isActive === true,
    isUsed: raw.isUsed === true,
    pendingTransfersCount: count(raw.pendingTransfersCount),
    activatedAt: raw.activatedAt ?? null,
    deactivatedAt: raw.deactivatedAt ?? null,
    createdAt: raw.createdAt ?? null,
    createdBy: actorOf(raw.createdBy),
  }
}

export function toTransferAccount(raw = {}) {
  return {
    id: raw.id ?? null,
    bankName: bilingual(raw.bankName) ?? { ar: '', en: '' },
    ibanLast4: raw.ibanLast4 ?? '',
    state: ACCOUNT_STATE_VALUES.includes(raw.state) ? raw.state : null,
  }
}

export function toCheckoutOptions(raw = {}) {
  return {
    channels: knownChannels(raw.channels),
    bankAccounts: (raw.bankAccounts ?? []).map(toPayerBankAccount),
    unifiedNumber: raw.transferNote?.unifiedNumber ?? null,
    pricing: toPathPricing(raw.pricing),
  }
}

function toProof(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    name: raw.name ?? '',
    mimeType: raw.mimeType ?? '',
    sizeBytes: count(raw.sizeBytes),
    scanStatus: raw.scanStatus ?? null,
  }
}

export function toOwnTransfer(raw) {
  if (!raw) return null
  return {
    bankAccount: toPayee(raw.bankAccount ?? {}),
    proof: toProof(raw.proof),
    submittedAt: raw.submittedAt ?? null,
    reviewedAt: raw.reviewedAt ?? null,
    rejectionReason: raw.rejectionReason ?? null,
    reviewNote: raw.reviewNote ?? null,
  }
}

function toStaffAccount(raw = {}) {
  return { ...toPayee(raw), isActive: raw.isActive === true, isArchived: raw.isArchived === true }
}

function toTransferEntity(raw = {}) {
  return {
    id: raw.id ?? null,
    reference: raw.reference ?? '',
    name: bilingual(raw.name) ?? { ar: '', en: '' },
    email: raw.email ?? null,
    isFrozen: raw.isFrozen === true,
  }
}

function toDuplicate(raw = {}) {
  return { paymentId: raw.paymentId ?? null, reference: raw.reference ?? '', status: raw.status }
}

export function toStaffTransfer(raw = {}) {
  return {
    id: raw.id ?? null,
    reference: raw.reference ?? '',
    purpose: raw.purpose ?? '',
    status: raw.status ?? null,
    isTest: raw.isTest === true,
    pricing: raw.pricing ?? null,
    entity: toTransferEntity(raw.entity),
    bankAccount: toStaffAccount(raw.bankAccount),
    proof: toProof(raw.proof),
    submittedAt: raw.submittedAt ?? null,
    reviewedAt: raw.reviewedAt ?? null,
    reviewedBy: actorOf(raw.reviewedBy),
    rejectionReason: raw.rejectionReason ?? null,
    reviewNote: raw.reviewNote ?? null,
    hasDuplicateProof: raw.hasDuplicateProof === true,
    duplicateProofs: Array.isArray(raw.duplicateProofs)
      ? raw.duplicateProofs.map(toDuplicate)
      : null,
    createdAt: raw.createdAt ?? null,
    paidAt: raw.paidAt ?? null,
    rejectedAt: raw.rejectedAt ?? null,
    receipt: raw.receipt ?? null,
    linkedRequest: toLinkedRequest(raw.linkedRequest),
  }
}

export function toLinkedRequest(raw) {
  if (!raw?.number) return null
  return {
    id: raw.id ?? null,
    number: raw.number,
    path: raw.path ?? null,
    pathLabel: raw.pathLabel ?? null,
    status: toRequestStatus(raw.status),
    progress: raw.progress
      ? { complete: count(raw.progress.subsComplete), total: count(raw.progress.subsTotal) }
      : null,
  }
}

export function toBulkOutcome(raw = {}) {
  return {
    decided: Array.isArray(raw.decided) ? raw.decided : [],
    skipped: (raw.skipped ?? []).map((item) => ({ id: item.id ?? null, code: item.code ?? '' })),
  }
}

export function toPendingTransfer(raw = {}) {
  return {
    paymentId: raw.paymentId ?? null,
    reference: raw.reference ?? '',
    entityName: bilingual(raw.entityName) ?? { ar: '', en: '' },
    total: raw.total ?? null,
    currency: raw.currency ?? 'SAR',
    submittedAt: raw.submittedAt ?? null,
  }
}

export function pendingTransfersOf(error) {
  return (error?.normalized?.meta?.transfers ?? []).map(toPendingTransfer)
}
