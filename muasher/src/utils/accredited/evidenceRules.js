import { EVIDENCE_TYPE_EXTENSIONS, SCAN_STATUSES } from '@/constants/accredited'

const MEGABYTE = 1024 * 1024
const COUNTING_SCANS = [SCAN_STATUSES.PENDING, SCAN_STATUSES.CLEAN]

export function acceptOf(allowedTypes) {
  return allowedTypes.flatMap((type) => EVIDENCE_TYPE_EXTENSIONS[type] ?? []).join(',')
}

export function exceedsSize(file, maxFileMb) {
  return Boolean(maxFileMb) && file.size > maxFileMb * MEGABYTE
}

export function hasAllowedExtension(file, allowedTypes) {
  const extension = `.${String(file.name).split('.').pop().toLowerCase()}`
  return allowedTypes.some((type) => (EVIDENCE_TYPE_EXTENSIONS[type] ?? []).includes(extension))
}

export function slotsOf(sub) {
  const own = (sub.attachments ?? []).map((slot) => ({ ...slot, pointCode: null }))
  const fromPoints = (sub.points ?? []).flatMap((point) => (point.attachments ?? []).map((slot) => ({ ...slot, pointCode: point.code })))
  return [...own, ...fromPoints]
}

export function evidenceOfSub(evidence, subId) {
  return evidence.filter((row) => row.subCriterionId === subId)
}

export function evidenceOfSlot(evidence, subId, key) {
  return evidence.filter((row) => row.subCriterionId === subId && row.attachmentKey === key)
}

export function countsTowardSlot(row) {
  return COUNTING_SCANS.includes(row.scanStatus)
}

export function hasPendingScan(evidence) {
  return evidence.some((row) => row.scanStatus === SCAN_STATUSES.PENDING)
}

export function isRequested(completionRequest, subId, kind) {
  return Boolean(completionRequest?.items.some((item) => item.subCriterionId === subId && item.kind === kind))
}
