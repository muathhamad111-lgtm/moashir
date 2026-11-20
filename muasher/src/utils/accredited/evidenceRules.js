import { EVIDENCE_TYPE_EXTENSIONS, SCAN_STATUSES } from '@/constants/accredited'

const MEGABYTE = 1024 * 1024
const COUNTING_SCANS = [SCAN_STATUSES.PENDING, SCAN_STATUSES.CLEAN]

export const EVIDENCE_LEVELS = Object.freeze({ MAIN: 'main', SUB: 'sub' })

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

// The path types the slot takes; the path's own types when the slot names none of them (as on the server).
export function slotTypesOf(slot, limits) {
  const pathTypes = limits?.allowedTypes ?? []
  const types = pathTypes.filter((type) => (slot?.allowedTypes ?? []).includes(type))
  return types.length ? types : pathTypes
}

export function slotMaxFilesOf(slot, limits) {
  const pathMaximum = limits?.maxFilesPerSub ?? null
  if (!slot?.maxFiles) return pathMaximum
  return pathMaximum === null ? slot.maxFiles : Math.min(slot.maxFiles, pathMaximum)
}

export function mainOwner(main) {
  return { level: EVIDENCE_LEVELS.MAIN, id: main.id }
}

export function subOwner(sub) {
  return { level: EVIDENCE_LEVELS.SUB, id: sub.id }
}

export function ownerOfEvidence(row) {
  return row.level === EVIDENCE_LEVELS.MAIN
    ? { level: EVIDENCE_LEVELS.MAIN, id: row.mainCriterionId }
    : { level: EVIDENCE_LEVELS.SUB, id: row.subCriterionId }
}

export function belongsTo(row, owner) {
  const rowOwner = ownerOfEvidence(row)
  return rowOwner.level === owner.level && rowOwner.id === owner.id
}

// A sub's slots: its own attachments, then those of its indicators (the tree lists indicators only
// when the sub is scored on them).
export function slotsOf(sub) {
  const fromPoints = (sub.points ?? []).flatMap((point) => point.attachments ?? [])
  return [...(sub.attachments ?? []), ...fromPoints]
}

// The sub's slots grouped for display: its own first, then one group per indicator with attachments.
export function slotGroupsOf(sub) {
  const pointGroups = (sub.points ?? [])
    .filter((point) => point.attachments?.length)
    .map((point) => ({ key: `point-${point.id}`, point, slots: point.attachments }))
  return [{ key: 'own', point: null, slots: sub.attachments ?? [] }, ...pointGroups]
}

export function evidenceOfSub(evidence, subId) {
  return evidence.filter((row) => belongsTo(row, { level: EVIDENCE_LEVELS.SUB, id: subId }))
}

export function evidenceOfMain(evidence, mainId) {
  return evidence.filter((row) => belongsTo(row, { level: EVIDENCE_LEVELS.MAIN, id: mainId }))
}

export function evidenceOfSlot(evidence, owner, key) {
  return evidence.filter((row) => belongsTo(row, owner) && row.attachmentKey === key)
}

export function countsTowardSlot(row) {
  return COUNTING_SCANS.includes(row.scanStatus)
}

export function hasPendingScan(evidence) {
  return evidence.some((row) => row.scanStatus === SCAN_STATUSES.PENDING)
}

export function isRequested(completionRequest, owner, kind) {
  const idField = owner.level === EVIDENCE_LEVELS.MAIN ? 'mainCriterionId' : 'subCriterionId'
  return Boolean(
    completionRequest?.items.some((item) => item[idField] === owner.id && item.kind === kind),
  )
}
