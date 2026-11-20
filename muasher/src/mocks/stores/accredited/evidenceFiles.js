import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { dispatchQueuedJob } from '../mockQueue'
import { findFile } from '../finance/financeState'
import { signedDownloadOf, storeFile } from '../finance/signedFiles'
import { assessmentSettings } from './assessmentSettingsStore'
import { STATES } from './accreditedStates'
import { mainOfId, nowIso, ownerCodeOf, presentEvidence, subOfId, touch } from './accreditedRecords'
import { visibleEvidence } from './accreditedSerializers'
import { enterEntityGate, isRequestedItem } from './entityWorkflow'
import { announceRefusedEvidence } from './accreditedNotices'
import { isPositiveInteger, reasonError, throwIfErrors, trimmed } from './accreditedValidation'
import { isLockedPdf } from '../lockedPdf'

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
  const isMain = form.get('mainCriterionId') !== null
  const idField = isMain ? 'mainCriterionId' : 'subCriterionId'
  if (!isPositiveInteger(form.get(idField)))
    errors[idField] = [fieldMessage('validation.integer', {}, idField)]
  const key = form.get('attachmentKey')
  if ((key !== null && trimmed(key).length > ATTACHMENT_KEY_MAX) || (isMain && !trimmed(key)))
    errors.attachmentKey = [fieldMessage('validation.invalid', {}, 'attachmentKey')]
  const reason = reasonError(form.get('reason') || null)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
  return {
    file,
    level: isMain ? 'main' : 'sub',
    nodeId: form.get(idField),
    attachmentKey: trimmed(key) || null,
    reason: trimmed(form.get('reason')) || null,
  }
}

function nodeOf(record, input) {
  const node =
    input.level === 'main' ? mainOfId(record, input.nodeId) : subOfId(record, input.nodeId)
  if (node) return node
  throw input.level === 'main'
    ? assessmentReject.coded(422, 'assessment.main_not_in_version', 'mainCriterionId')
    : assessmentReject.coded(422, 'assessment.sub_not_in_version', 'subCriterionId')
}

function presentOf(record, node, key) {
  return presentEvidence(record).filter(
    (row) => ownerCodeOf(row) === node.code && (key === undefined || row.attachmentKey === key),
  )
}

// Mirrors EvidenceTarget: the slot, the completion scope, room in the criterion and in the slot.
function assertTarget(record, node, input) {
  const slot = input.attachmentKey
    ? node.slots.find((item) => item.key === input.attachmentKey)
    : null
  if (input.attachmentKey && !slot)
    throw assessmentReject.coded(422, 'attachment.key_invalid', 'attachmentKey')
  if (!isRequestedItem(record, node, 'evidence'))
    throw assessmentReject.coded(422, 'completion.item_not_requested', 'subCriterionId')
  const maximum = assessmentSettings().evidenceMaxFilesPerSub
  if (presentOf(record, node).length >= maximum)
    throw input.level === 'main'
      ? assessmentReject.mainFull(maximum)
      : assessmentReject.subFull(maximum)
  const slotMaximum = Math.min(slot?.maxFiles ?? maximum, maximum)
  if (slot && presentOf(record, node, slot.key).length >= slotMaximum)
    throw assessmentReject.slotFull(slotMaximum)
  return slot
}

function slotTypes(slot) {
  const pathTypes = assessmentSettings().evidenceAllowedTypes
  const types = pathTypes.filter((type) => (slot?.allowedTypes ?? []).includes(type))
  return types.length ? types : pathTypes
}

function sniffedType(name, bytes, slot) {
  const type = typeOfName(name)
  const known = TYPES[type]
  const allowed = slotTypes(slot).includes(type)
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
  if (row.scanStatus === 'infected') announceRefusedEvidence(record)
}

export async function uploadEvidence(user, record, form) {
  const input = validateUpload(form)
  enterEntityGate(user, record, { verb: 'upload_evidence', reason: input.reason })
  const node = nodeOf(record, input)
  const slot = assertTarget(record, node, input)
  const bytes = new Uint8Array(await input.file.arrayBuffer())
  const mimeType = sniffedType(input.file.name, bytes, slot)
  if (isLockedPdf(bytes)) throw assessmentReject.coded(422, 'file.password_protected', 'file')
  const row = {
    id: crypto.randomUUID(),
    mainCode: input.level === 'main' ? node.code : null,
    subCode: input.level === 'main' ? null : node.code,
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
    !isRequestedItem(record, { code: ownerCodeOf(row) }, 'evidence')
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
