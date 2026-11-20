import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { boundTree, entityOf, isFrozen } from '../assessments/assessmentsStore'
import {
  assessmentResource,
  resultResource,
  scoresResource,
  scoringProgressOf,
  subRows,
} from '../assessments/selfSerializers'
import { criteriaUpdateOf } from '../assessments/criteriaMoves'
import { ACCESS, accessOf } from '../assessments/assessmentAccess'
import { entityProfileOf, entitySummary } from '../entitySerializers'
import { findRegion } from '../regionsStore'
import { entityLevelOf, lastLevelOf } from '../entityLevels'
import { uploadLimits } from './assessmentSettingsStore'
import {
  boundSubsOf,
  gapsOf,
  boundMainsOf,
  idOfCode,
  isOverdue,
  lateAttachmentKeysOf,
  nameOfCode,
  openCompletionOf,
  ownerCodeOf,
  presentEvidence,
} from './accreditedRecords'
import { findCycle } from '../award/cycleStore'
import { maskedStatus } from '../award/statusMask'
import { awardOutcomeOf } from '../committee/awardOutcome'
import { pathLabelOf } from '../../fixtures/pathLabels'

function placeOf(region) {
  return region ? { id: region.id, code: region.code, name: { ...region.name } } : null
}

function cycleSummaryOf(record) {
  const cycle = record.awardCycleId ? findCycle(record.awardCycleId) : null
  return cycle ? { id: cycle.id, name: { ...cycle.name }, status: cycle.status } : null
}

const FEEDBACK_FIELDS = ['strengths', 'improvementPoints', 'notes']

function clockOf(record) {
  return {
    submittedAt: record.submittedAt,
    dueAt: record.dueAt,
    isOverdue: isOverdue(record),
    completedAt: record.completedAt,
  }
}

export function evidenceItem(row, subs, mains = []) {
  return {
    id: row.id,
    level: row.mainCode ? 'main' : 'sub',
    mainCriterionId: row.mainCode ? idOfCode(mains, row.mainCode) : null,
    subCriterionId: row.subCode ? idOfCode(subs, row.subCode) : null,
    code: ownerCodeOf(row),
    attachmentKey: row.attachmentKey,
    fileName: row.fileName,
    mimeType: row.mimeType,
    sizeBytes: row.sizeBytes,
    scanStatus: row.scanStatus,
    uploadedAt: row.createdAt,
  }
}

export function visibleEvidence(record, viewer) {
  const present = presentEvidence(record)
  return accessOf(viewer, record) === ACCESS.OWNER
    ? present
    : present.filter((row) => row.scanStatus === 'clean')
}

export function evidenceList(rows, record) {
  const subs = boundSubsOf(record)
  const mains = boundMainsOf(record)
  return rows.map((row) => evidenceItem(row, subs, mains))
}

function completionItemResource(item, subs, mains) {
  const nodes = item.mainCode ? mains : subs
  return {
    mainCriterionId: item.mainCode && mains ? idOfCode(mains, item.mainCode) : null,
    subCriterionId: item.subCode && subs ? idOfCode(subs, item.subCode) : null,
    code: ownerCodeOf(item),
    name: nodes ? nameOfCode(nodes, ownerCodeOf(item)) : null,
    kind: item.kind,
    note: item.note,
  }
}

export function completionResource(completion, subs, mains = null) {
  if (!completion) return null
  return {
    id: completion.id,
    status: completion.status,
    notes: completion.notes,
    items: completion.items.map((item) => completionItemResource(item, subs, mains)),
    deadlineAt: completion.deadlineAt,
    expiryOutcome: completion.expiryOutcome,
    requestedAt: completion.createdAt,
    resolvedAt: completion.resolvedAt,
  }
}

export function cvBySubId(record, subs) {
  return Object.fromEntries(
    subs
      .filter((sub) => record.cv[sub.code] !== undefined)
      .map((sub) => [String(sub.id), record.cv[sub.code]]),
  )
}

export function cvResource(record) {
  return { assessment: assessmentResource(record), cv: cvBySubId(record, boundSubsOf(record)) }
}

function feedbackFieldsOf(record, code) {
  return Object.fromEntries(
    FEEDBACK_FIELDS.map((field) => [field, record.feedback[code]?.[field] ?? null]),
  )
}

function feedbackBySubId(record, subs) {
  return Object.fromEntries(subs.map((sub) => [String(sub.id), feedbackFieldsOf(record, sub.code)]))
}

export function feedbackResource(record) {
  return {
    assessment: assessmentResource(record),
    feedback: feedbackBySubId(record, boundSubsOf(record)),
  }
}

export function accreditedFormResource(record, viewer) {
  const subs = boundSubsOf(record)
  return {
    assessment: assessmentResource(record),
    criteria: boundTree(record),
    cv: cvBySubId(record, subs),
    evidence: evidenceList(visibleEvidence(record, viewer), record),
    missing: gapsOf(record),
    completionRequest: completionResource(openCompletionOf(record), subs, boundMainsOf(record)),
    uploadLimits: uploadLimits(),
    criteriaUpdate: criteriaUpdateOf(record),
    passedStations: [...(record.passedStations ?? [])],
  }
}

export function accreditedResultResource(record) {
  const result = resultResource(record)
  if (!result.available) return result
  const withAward =
    record.path === ASSESSMENT_PATHS.ADVANCED
      ? { ...result, award: awardOutcomeOf(record) }
      : result
  return withFeedback(record, withAward)
}

export function withFeedback(record, result) {
  return {
    ...result,
    feedback: subRows(record.result).map((sub) => ({
      subId: sub.subId,
      code: sub.code,
      name: sub.name,
      percent: sub.percent,
      ...feedbackFieldsOf(record, sub.code),
    })),
  }
}

function assessorOf(assignment, staffName) {
  return assignment ? { id: assignment.assessorId, name: staffName(assignment.assessorId) } : null
}

function queueEntityOf(entity) {
  return entity
    ? {
        ...entitySummary(entity),
        level: entityLevelOf(entity.id),
        lastLevel: lastLevelOf(entity.id),
      }
    : null
}

// The latest assignment in any status: its assessor stays on the request after the review ends.
function latestAssignmentOf(record) {
  return record.assignments.at(-1) ?? null
}

export function queueRow(record, staffName) {
  const assignment = latestAssignmentOf(record)
  return {
    id: record.id,
    number: record.number,
    path: record.path,
    pathLabel: pathLabelOf(record.path),
    status: maskedStatus(record),
    isFrozen: isFrozen(record),
    entity: queueEntityOf(entityOf(record)),
    assessor: assessorOf(assignment, staffName),
    isReassigned: record.assignments.some((item) => item.status === 'reassigned'),
    assignedAt: assignment?.createdAt ?? null,
    completionDeadline: openCompletionOf(record)?.deadlineAt ?? null,
    awardCycle: cycleSummaryOf(record),
    region: placeOf(record.regionId ? findRegion(record.regionId) : null),
    ...clockOf(record),
  }
}

export function assignedRow(record, viewer) {
  const assignment =
    [...record.assignments].reverse().find((item) => item.assessorId === viewer.id) ?? null
  const entity = entityOf(record)
  return {
    id: record.id,
    number: record.number,
    path: record.path,
    pathLabel: pathLabelOf(record.path),
    status: maskedStatus(record),
    entity: { ...entitySummary(entity), region: placeOf(findRegion(entity.regionId)) },
    assignedAt: assignment?.createdAt ?? null,
    startedAt: assignment?.startedAt ?? null,
    completionDeadline: openCompletionOf(record)?.deadlineAt ?? null,
    awardCycle: cycleSummaryOf(record),
    reviewProgress: scoringProgressOf(record),
    ...clockOf(record),
  }
}

export function reviewResource(record, { mode, context }) {
  const subs = boundSubsOf(record)
  return {
    ...scoresResource(record),
    mode,
    ...clockOf(record),
    entity: { ...entitySummary(entityOf(record)), ...entityProfileOf(entityOf(record)) },
    criteria: boundTree(record),
    cv: cvBySubId(record, subs),
    evidence: evidenceList(
      presentEvidence(record).filter((row) => row.scanStatus === 'clean'),
      record,
    ),
    lateAttachmentKeys: lateAttachmentKeysOf(record),
    feedback: feedbackBySubId(record, subs),
    completionRequest: completionResource(openCompletionOf(record), subs, boundMainsOf(record)),
    context,
    criteriaUpdate: criteriaUpdateOf(record),
  }
}

export function bundleResource(bundle, record) {
  return {
    id: bundle.id,
    assessmentId: record.id,
    status: bundle.status,
    fileCount: bundle.fileCount,
    sizeBytes: bundle.sizeBytes,
    isDownloadable: bundle.status === 'ready' && new Date(bundle.expiresAt).getTime() > Date.now(),
    requestedAt: bundle.createdAt,
    readyAt: bundle.readyAt,
    expiresAt: bundle.expiresAt,
  }
}
