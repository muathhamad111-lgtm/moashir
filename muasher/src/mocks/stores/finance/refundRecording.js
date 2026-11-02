import { usersFixture } from '../../fixtures/users'
import { financeState, persistFinanceState, refundOf } from './financeState'
import { isCalendarDate } from './financeQueries'
import { fieldMessage, financeReject } from './financeRejection'
import { toMinor } from './financeMoney'
import { assertTransition, nowIso, recordEvent, transition, voidReceipt } from './paymentLifecycle'
import { storeFile } from './signedFiles'
import { announcePaymentRefunded } from './paymentHooks'

const AMOUNT_PATTERN = /^\d{1,10}\.\d{2}$/
const REFERENCE_PATTERN = /^[\p{L}\p{N}\s/._-]+$/u
const EVIDENCE_TYPES = { pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png' }
const MAX_EVIDENCE_BYTES = 20 * 1024 * 1024
const MAX_EVIDENCE_MB = 20
const INFECTED_MARKER = 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE'
const SIGNATURES = [
  { mimeType: 'application/pdf', bytes: [0x25, 0x50, 0x44, 0x46] },
  { mimeType: 'image/png', bytes: [0x89, 0x50, 0x4e, 0x47] },
  { mimeType: 'image/jpeg', bytes: [0xff, 0xd8, 0xff] },
]

export function riyadhToday() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(new Date())
}

function riyadhDateOf(iso) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(new Date(iso))
}

function staffNameOf(user) {
  const staff = usersFixture.find((item) => item.id === user.id)
  if (staff) return staff.name
  return typeof user.name === 'string' ? user.name : (user.name?.ar ?? '')
}

function text(form, key) {
  const value = form.get(key)
  return typeof value === 'string' ? value.trim() : ''
}

function extensionOf(name) {
  return String(name ?? '').split('.').pop().toLowerCase()
}

function lengthError(value, min, max) {
  if (!value) return fieldMessage('required')
  if (value.length < min) return fieldMessage('min', { min })
  if (value.length > max) return fieldMessage('max', { max })
  return null
}

function evidenceError(file) {
  if (!(file instanceof Blob)) return fieldMessage('required')
  if (!EVIDENCE_TYPES[extensionOf(file.name)]) return fieldMessage('file_type')
  if (file.size > MAX_EVIDENCE_BYTES) return fieldMessage('file_size', { max: MAX_EVIDENCE_MB })
  return null
}

function dateError(value) {
  if (!isCalendarDate(value)) return fieldMessage('date')
  return value > riyadhToday() ? fieldMessage('date_future') : null
}

function referenceError(value) {
  return lengthError(value, 3, 80) ?? (REFERENCE_PATTERN.test(value) ? null : fieldMessage('invalid'))
}

export function validatedRefund(form) {
  const instruction = {
    amount: text(form, 'amount'),
    refundedOn: text(form, 'refundedOn'),
    reason: text(form, 'reason'),
    transferReference: text(form, 'transferReference'),
    evidence: form.get('evidence'),
  }
  const errors = {
    amount: AMOUNT_PATTERN.test(instruction.amount) ? null : fieldMessage('amount'),
    refundedOn: dateError(instruction.refundedOn),
    reason: lengthError(instruction.reason, 20, 1000),
    transferReference: referenceError(instruction.transferReference),
    evidence: evidenceError(instruction.evidence),
  }
  const failed = Object.entries(errors).filter(([, message]) => message)
  if (failed.length) throw financeReject.validation(Object.fromEntries(failed.map(([field, message]) => [field, [message]])))
  return instruction
}

function assertRecordable(payment, instruction) {
  assertTransition(payment, 'refunded', 'record_refund')
  if (toMinor(instruction.amount) !== toMinor(payment.total)) throw financeReject.refundAmount(payment.total)
  if (payment.paidAt && instruction.refundedOn < riyadhDateOf(payment.paidAt)) throw financeReject.refundDate()
}

function detectedType(bytes) {
  return SIGNATURES.find((signature) => signature.bytes.every((byte, index) => bytes[index] === byte))?.mimeType ?? null
}

async function acceptEvidence(file) {
  const bytes = new Uint8Array(await file.arrayBuffer())
  if (detectedType(bytes) !== EVIDENCE_TYPES[extensionOf(file.name)]) throw financeReject.fileRefused('file.content_mismatch', 'evidence')
  if (new TextDecoder().decode(bytes).includes(INFECTED_MARKER)) throw financeReject.fileRefused('file.infected', 'evidence')
  return storeFile({
    purpose: 'refund_evidence',
    name: file.name,
    mimeType: EVIDENCE_TYPES[extensionOf(file.name)],
    sizeBytes: file.size,
    content: bytes,
  })
}

export async function recordRefund(user, payment, instruction) {
  assertRecordable(payment, instruction)
  const evidence = await acceptEvidence(instruction.evidence)
  if (refundOf(payment.id)) throw financeReject.refundRecorded()
  const refund = {
    id: crypto.randomUUID(),
    paymentId: payment.id,
    status: 'recorded',
    amount: instruction.amount,
    currency: payment.currency,
    refundedOn: instruction.refundedOn,
    transferReference: instruction.transferReference,
    reason: instruction.reason,
    recordedBy: { id: user.id, name: staffNameOf(user) },
    evidenceFileId: evidence.id,
    createdAt: nowIso(),
  }
  financeState().refunds.push(refund)
  transition(payment, 'refunded', 'record_refund')
  voidReceipt(payment)
  recordEvent(payment, { source: 'admin', eventType: 'refund_recorded', result: 'applied', providerPaymentId: null })
  persistFinanceState()
  announcePaymentRefunded(payment, refund)
  return refund
}
