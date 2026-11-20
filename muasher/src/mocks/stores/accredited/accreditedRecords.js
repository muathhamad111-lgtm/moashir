import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { allAssessments, boundTree, isOpen } from '../assessments/assessmentsStore'
import { isReviewedPath, pathRules } from '../assessments/reviewedPaths'
import { STATES } from './accreditedStates'

const COUNTING_SCANS = ['pending', 'clean']

export function nowIso() {
  return new Date().toISOString()
}

export function accreditedRecords() {
  return allAssessments().filter((record) => record.path === ASSESSMENT_PATHS.ACCREDITED)
}

export function reviewedRecords() {
  return allAssessments().filter((record) => isReviewedPath(record.path))
}

export function accreditedOf(entityId) {
  return accreditedRecords().filter((record) => record.entityId === Number(entityId))
}

export function openAccreditedOf(entityId) {
  return accreditedOf(entityId).find((record) => isOpen(record)) ?? null
}

export function recordOfPayment(paymentId) {
  return accreditedRecords().find((record) => record.paymentId === paymentId) ?? null
}

function boundNode(node, slots) {
  return {
    id: node.id,
    code: node.code,
    name: { ...node.name },
    slots,
    slotKeys: slots.map((slot) => slot.key),
    requiredKeys: slots.filter((slot) => slot.required).map((slot) => slot.key),
  }
}

export function boundSubsOf(record) {
  const tree = boundTree(record)
  return tree.mains.flatMap((main) =>
    main.subs.map((sub) => {
      const pointSlots = (sub.points ?? []).flatMap((point) => point.attachments ?? [])
      const slots = [...(sub.attachments ?? []), ...pointSlots]
      return { ...boundNode(sub, slots), mainId: main.id, hasCv: sub.hasCv }
    }),
  )
}

// The main criteria with the attachments they ask for themselves (S7).
export function boundMainsOf(record) {
  return boundTree(record).mains.map((main) => boundNode(main, main.attachments ?? []))
}

export function subOfId(record, id) {
  return boundSubsOf(record).find((sub) => String(sub.id) === String(id)) ?? null
}

export function mainOfId(record, id) {
  return boundMainsOf(record).find((main) => String(main.id) === String(id)) ?? null
}

// The code of the criterion an evidence row or completion item belongs to.
export function ownerCodeOf(row) {
  return row.mainCode ?? row.subCode
}

// While the request is back for completion, only the requested items change or count.
export function isRequestedCode(record, code, kind) {
  if (record.status !== STATES.NEEDS_COMPLETION) return true
  const completion = openCompletionOf(record)
  return Boolean(completion?.items.some((item) => ownerCodeOf(item) === code && item.kind === kind))
}

export function idOfCode(subs, code) {
  return subs.find((sub) => sub.code === code)?.id ?? null
}

export function nameOfCode(subs, code) {
  return subs.find((sub) => sub.code === code)?.name ?? null
}

export function presentEvidence(record) {
  return record.evidence.filter((row) => row.status === 'uploaded')
}

export function openCompletionOf(record) {
  return record.completionRequests.find((completion) => completion.status === 'open') ?? null
}

export function activeAssignment(record) {
  return record.assignments.find((assignment) => assignment.status === 'active') ?? null
}

export function isOverdue(record, now = Date.now()) {
  return (
    isReviewedPath(record.path) &&
    pathRules(record.path).runsClock(record.status) &&
    record.dueAt !== null &&
    new Date(record.dueAt).getTime() < now
  )
}

function missingKeysOf(node, provided) {
  return node.requiredKeys.filter((key) => !provided.has(`${node.code}|${key}`))
}

export function gapsOf(record) {
  const subs = boundSubsOf(record)
  const wanted = (kind) => (node) => isRequestedCode(record, node.code, kind)
  const provided = new Set(
    presentEvidence(record)
      .filter((row) => row.attachmentKey && COUNTING_SCANS.includes(row.scanStatus))
      .map((row) => `${ownerCodeOf(row)}|${row.attachmentKey}`),
  )
  return {
    missingCv: subs
      .filter(wanted('cv'))
      .filter((sub) => sub.hasCv && !String(record.cv[sub.code] ?? '').trim())
      .map((sub) => ({ subCriterionId: sub.id, code: sub.code })),
    missingAttachments: subs.filter(wanted('evidence')).flatMap((sub) =>
      missingKeysOf(sub, provided).map((key) => ({
        subCriterionId: sub.id,
        code: sub.code,
        attachmentKey: key,
      })),
    ),
    missingMainAttachments: boundMainsOf(record)
      .filter(wanted('evidence'))
      .flatMap((main) =>
        missingKeysOf(main, provided).map((key) => ({
          mainCriterionId: main.id,
          code: main.code,
          attachmentKey: key,
        })),
      ),
  }
}

// Required attachments that came after the submission: no file was ever uploaded to them (D-5).
export function lateAttachmentKeysOf(record) {
  if (!record.submittedAt) return []
  const received = new Set(
    record.evidence
      .filter((row) => row.attachmentKey)
      .map((row) => `${ownerCodeOf(row)}|${row.attachmentKey}`),
  )
  return [...boundMainsOf(record), ...boundSubsOf(record)].flatMap((node) =>
    missingKeysOf(node, received),
  )
}

export function touch(record) {
  record.updatedAt = nowIso()
}
