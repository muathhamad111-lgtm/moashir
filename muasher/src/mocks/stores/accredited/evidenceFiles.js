import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { dispatchQueuedJob } from '../mockQueue'
import { findFile } from '../finance/financeState'
import { signedDownloadOf, storeFile } from '../finance/signedFiles'
import { assessmentSettings } from './assessmentSettingsStore'
import { STATES } from './accreditedStates'
import { nowIso, presentEvidence, subOfId, touch } from './accreditedRecords'
import { visibleEvidence } from './accreditedSerializers'
import { enterEntityGate, isRequestedItem } from './entityWorkflow'
import { announceRefusedEvidence } from './accreditedNotices'
import { isPositiveInteger, reasonError, throwIfErrors, trimmed } from './accreditedValidation'

const EVIDENCE_SCAN_DELAY_MS = 2500
const INFECTED_MARKER = 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE'
const ATTACHMENT_KEY_MAX = 40
const MEGABYTE = 1024 * 1024
const ZIP_SIGNATURE = [0x50, 0x4b, 0x03, 0x04]

const TYPES = {
  pdf: { mimeType: 'application/pdf', signature: [0x25, 0x50, 0x44, 0x46] },
  docx: {
    mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    signature: ZIP_SIGNATURE,
  },
  xlsx: {
    mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    signature: ZIP_SIGNATURE,
  },
  pptx: {
    mimeType: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    signature: ZIP_SIGNATURE,
  },
  jpg: { mimeType: 'image/jpeg', signature: [0xff, 0xd8, 0xff] },
  png: { mimeType: 'image/png', signature: [0x89, 0x50, 0x4e, 0x47] },
}

const EXTENSION_TYPES = { jpeg: 'jpg' }
const contents = new Map()

function typeOfName(name) {
  const extension = String(name).split('.').pop().toLowerCase()
  return EXTENSION_TYPES[extension] ?? extension
}

function isFileLike(value) {
  return typeof value?.arrayBuffer === 'function' && typeof value?.name === 'string'
}

function validateUpload(form) {
  const file = form.get('file')
  const errors = {}
  if (!isFileLike(file)) errors.file = [fieldMessage('validation.required', {}, 'file')]
  else if (file.size > assessmentSettings().uploadMaxFileMb * MEGABYTE) {
    errors.file = [
      fieldMessage('validation.file_max', { max: assessmentSettings().uploadMaxFileMb }, 'file'),
    ]
  }
  if (!isPositiveInteger(form.get('subCriterionId')))
    errors.subCriterionId = [fieldMessage('validation.integer', {}, 'subCriterionId')]
  const key = form.get('attachmentKey')
  if (key !== null && trimmed(key).length > ATTACHMENT_KEY_MAX)
    errors.attachmentKey = [fieldMessage('validation.invalid', {}, 'attachmentKey')]
  const reason = reasonError(form.get('reason') || null)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
  return {
    file,
    subId: form.get('subCriterionId'),
    attachmentKey: trimmed(key) || null,
    reason: trimmed(form.get('reason')) || null,
  }
}

function assertTarget(record, sub, attachmentKey) {
  if (!sub) throw assessmentReject.coded(422, 'assessment.sub_not_in_version', 'subCriterionId')
  if (attachmentKey && !sub.slotKeys.includes(attachmentKey))
    throw assessmentReject.coded(422, 'attachment.key_invalid', 'attachmentKey')
  if (!isRequestedItem(record, sub, 'evidence'))
    throw assessmentReject.coded(422, 'completion.item_not_requested', 'subCriterionId')
  const maximum = assessmentSettings().evidenceMaxFilesPerSub
  if (presentEvidence(record).filter((row) => row.subCode === sub.code).length >= maximum)
    throw assessmentReject.subFull(maximum)
}

function sniffedType(name, bytes) {
  const type = typeOfName(name)
  const known = TYPES[type]
  const allowed = assessmentSettings().evidenceAllowedTypes.includes(type)
  if (!known || !allowed || !known.signature.every((byte, index) => bytes[index] === byte)) {
    throw assessmentReject.coded(422, 'file.content_mismatch', 'file')
  }
  return known.mimeType
}

function scan(record, row) {
  if (row.status !== 'uploaded' || row.scanStatus !== 'pending') return
  const bytes = contents.get(row.id)
  row.scanStatus =
    bytes && new TextDecoder().decode(bytes).includes(INFECTED_MARKER) ? 'infected' : 'clean'
  if (row.scanStatus === 'infected') announceRefusedEvidence(record, row)
}

export async function uploadEvidence(user, record, form) {
  const input = validateUpload(form)
  enterEntityGate(user, record, { verb: 'upload_evidence', reason: input.reason })
  const sub = subOfId(record, input.subId)
  assertTarget(record, sub, input.attachmentKey)
  const bytes = new Uint8Array(await input.file.arrayBuffer())
  const mimeType = sniffedType(input.file.name, bytes)
  const row = {
    id: crypto.randomUUID(),
    subCode: sub.code,
    attachmentKey: input.attachmentKey,
    fileName: input.file.name,
    mimeType,
    sizeBytes: input.file.size,
    scanStatus: 'pending',
    status: 'uploaded',
    storedFileId: null,
    createdAt: nowIso(),
  }
  contents.set(row.id, bytes)
  record.evidence.push(row)
  touch(record)
  dispatchQueuedJob(() => scan(record, row), EVIDENCE_SCAN_DELAY_MS)
  return row
}

function presentRow(record, evidenceId) {
  const row = presentEvidence(record).find((item) => item.id === evidenceId)
  if (!row) throw assessmentReject.notFound()
  return row
}

export function removeEvidence(user, record, evidenceId, body) {
  const row = presentRow(record, evidenceId)
  const reason = trimmed(body.reason) || null
  enterEntityGate(user, record, { verb: 'remove_evidence', reason })
  if (
    record.status === STATES.NEEDS_COMPLETION &&
    !isRequestedItem(record, { code: row.subCode }, 'evidence')
  ) {
    throw assessmentReject.coded(422, 'completion.item_not_requested', 'evidence')
  }
  row.status = 'removed'
  contents.delete(row.id)
  touch(record)
}

function storedFileOf(row) {
  const stored = row.storedFileId ? findFile(row.storedFileId) : null
  if (stored) return stored
  const file = storeFile({
    purpose: 'assessment_evidence',
    name: row.fileName,
    mimeType: row.mimeType,
    sizeBytes: row.sizeBytes,
    content: contents.get(row.id) ?? null,
  })
  row.storedFileId = file.id
  return file
}

export function evidenceDownload(user, record, evidenceId) {
  const row = visibleEvidence(record, user).find((item) => item.id === evidenceId)
  if (!row) throw assessmentReject.notFound()
  if (row.scanStatus !== 'clean') throw assessmentReject.coded(409, 'file.not_clean')
  return signedDownloadOf(user, storedFileOf(row))
}
