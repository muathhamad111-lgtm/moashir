import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { allAssessments, boundTree, isOpen } from '../assessments/assessmentsStore'
import { isReviewedPath, pathRules } from '../assessments/reviewedPaths'

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

export function boundSubsOf(record) {
  const tree = boundTree(record)
  return tree.mains.flatMap((main) =>
    main.subs.map((sub) => {
      const pointSlots = sub.points.flatMap((point) => point.attachments)
      const slots = [...sub.attachments, ...pointSlots]
      return {
        id: sub.id,
        code: sub.code,
        mainId: main.id,
        hasCv: sub.hasCv,
        slotKeys: slots.map((slot) => slot.key),
        requiredKeys: slots.filter((slot) => slot.required).map((slot) => slot.key),
      }
    }),
  )
}

export function subOfId(record, id) {
  return boundSubsOf(record).find((sub) => String(sub.id) === String(id)) ?? null
}

export function idOfCode(subs, code) {
  return subs.find((sub) => sub.code === code)?.id ?? null
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

export function gapsOf(record) {
  const subs = boundSubsOf(record)
  const provided = new Set(
    presentEvidence(record)
      .filter((row) => row.attachmentKey && COUNTING_SCANS.includes(row.scanStatus))
      .map((row) => `${row.subCode}|${row.attachmentKey}`),
  )
  return {
    missingCv: subs
      .filter((sub) => sub.hasCv && !String(record.cv[sub.code] ?? '').trim())
      .map((sub) => ({ subCriterionId: sub.id, code: sub.code })),
    missingAttachments: subs.flatMap((sub) =>
      sub.requiredKeys
        .filter((key) => !provided.has(`${sub.code}|${key}`))
        .map((key) => ({ subCriterionId: sub.id, code: sub.code, attachmentKey: key })),
    ),
  }
}

export function touch(record) {
  record.updatedAt = nowIso()
}
