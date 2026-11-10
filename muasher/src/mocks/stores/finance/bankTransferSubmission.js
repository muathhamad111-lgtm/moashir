import { PROOF_RULES } from '@/constants/bankTransfer'
import { FINANCE_YEAR } from '../../fixtures/finance'
import { financeState, nextSequence, persistFinanceState } from './financeState'
import { fieldMessage, financeReject } from './financeRejection'
import { nowIso } from './paymentLifecycle'
import { storeFile } from './signedFiles'
import { listedAccount } from './bankAccountsStore'
import { isChannelAvailable } from './paymentChannelsStore'

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
const TYPES = { pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png' }
const MAX_BYTES = PROOF_RULES.MAX_MB * PROOF_RULES.BYTES_PER_MB
const INFECTED_MARKER = 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE'
const SCAN_FAILURE_MARKER = 'MOASHIR-SCANNER-UNAVAILABLE'
const SIGNATURES = [
  { mimeType: 'application/pdf', bytes: [0x25, 0x50, 0x44, 0x46] },
  { mimeType: 'image/png', bytes: [0x89, 0x50, 0x4e, 0x47] },
  { mimeType: 'image/jpeg', bytes: [0xff, 0xd8, 0xff] },
]

function extensionOf(name) {
  return String(name ?? '')
    .split('.')
    .pop()
    .toLowerCase()
}

function text(form, key) {
  const value = form.get(key)
  return typeof value === 'string' ? value.trim() : ''
}

export function isTransferForm(request) {
  return /multipart\/form-data/i.test(request.headers.get('content-type') ?? '')
}

function proofError(file) {
  if (!(file instanceof Blob)) return fieldMessage('required')
  if (!TYPES[extensionOf(file.name)]) return fieldMessage('file_type')
  if (file.size > MAX_BYTES) return fieldMessage('file_size', { max: PROOF_RULES.MAX_MB })
  return null
}

export function validatedTransfer(form) {
  const order = {
    purpose: text(form, 'purpose'),
    channel: text(form, 'channel'),
    bankAccountId: text(form, 'bankAccountId'),
    proof: form.get('proof'),
  }
  const errors = {
    channel: order.channel === 'bank_transfer' ? null : fieldMessage('choice'),
    bankAccountId: UUID.test(order.bankAccountId) ? null : fieldMessage('required'),
    proof: proofError(order.proof),
  }
  const failed = Object.entries(errors).filter(([, message]) => message)
  if (failed.length)
    throw financeReject.validation(
      Object.fromEntries(failed.map(([field, message]) => [field, [message]])),
    )
  return order
}

export function assertTransferAvailable(order) {
  if (!isChannelAvailable('bank_transfer')) throw financeReject.channelNotAvailable()
  const account = listedAccount(order.bankAccountId)
  if (!account || !account.isActive) throw financeReject.accountNotSelectable()
  return account
}

function detectedType(bytes) {
  return (
    SIGNATURES.find((signature) => signature.bytes.every((byte, index) => bytes[index] === byte))
      ?.mimeType ?? null
  )
}

async function digestOf(bytes) {
  const hash = await crypto.subtle.digest('SHA-256', bytes)
  return [...new Uint8Array(hash)].map((byte) => byte.toString(16).padStart(2, '0')).join('')
}

export async function acceptProof(file) {
  const bytes = new Uint8Array(await file.arrayBuffer())
  const decoded = new TextDecoder().decode(bytes)
  if (detectedType(bytes) !== TYPES[extensionOf(file.name)])
    throw financeReject.fileRefused('file.content_mismatch', 'proof')
  if (decoded.includes(INFECTED_MARKER)) throw financeReject.fileRefused('file.infected', 'proof')
  if (decoded.includes(SCAN_FAILURE_MARKER))
    throw financeReject.fileRefused('file.scan_failed', 'proof')
  const stored = storeFile({
    purpose: 'transfer_proof',
    name: file.name,
    mimeType: TYPES[extensionOf(file.name)],
    sizeBytes: file.size,
    content: bytes,
  })
  stored.sha256 = await digestOf(bytes)
  return stored
}

function referenceOf(sequence) {
  return `PAY-${FINANCE_YEAR}-${String(sequence).padStart(6, '0')}`
}

function transferPaymentOf({ user, entity, purpose, quote, key, cycle }) {
  const at = nowIso()
  return {
    id: crypto.randomUUID(),
    reference: referenceOf(nextSequence('payment')),
    entityId: entity.id,
    entityName: { ar: entity.name.ar, en: entity.name.en ?? null },
    initiatedByUserId: user.id,
    purpose,
    awardCycleId: cycle?.id ?? null,
    channel: 'bank_transfer',
    mode: 'test',
    idempotencyKey: key,
    providerIntentId: null,
    providerPaymentId: null,
    method: null,
    cardLast4: null,
    ...quote,
    status: 'under-review',
    expiresAt: null,
    createdAt: at,
    updatedAt: at,
    boundAt: null,
    verifiedAt: null,
    paidAt: null,
    failedAt: null,
    cancelledAt: null,
    rejectedAt: null,
    refundedAt: null,
    latePaidAt: null,
    manuallySettledAt: null,
    failureReason: null,
    failureCode: null,
    failureMessage: null,
  }
}

export function openTransfer({ user, entity, purpose, quote, key, account, proof, cycle = null }) {
  const payment = transferPaymentOf({ user, entity, purpose, quote, key, cycle })
  const state = financeState()
  state.payments.push(payment)
  state.transfers.push({
    paymentId: payment.id,
    bankAccountId: account.id,
    proofFileId: proof.id,
    submittedAt: payment.createdAt,
    submittedBy: user.id,
    reviewedAt: null,
    reviewedBy: null,
    rejectionReason: null,
    reviewNote: null,
  })
  persistFinanceState()
  return payment
}
