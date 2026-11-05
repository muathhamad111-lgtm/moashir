import { QUEUE_STATUSES } from '@/constants/accredited'
import { ADVANCED_QUEUE_STATUSES } from '@/constants/advanced'
import { toAssessmentRecord, toEntitySummary } from './assessments'
import { toCompletionRequest } from './accredited'
import { toRequestStatus, toStatusCounts } from './accreditedStatus'

const STATS_STATUSES = [...new Set([...QUEUE_STATUSES, ...ADVANCED_QUEUE_STATUSES])]

function toPerson(raw) {
  if (!raw) return null
  return { id: raw.id ?? null, name: raw.name ?? null }
}

export function toClock(raw = {}) {
  return {
    submittedAt: raw.submittedAt ?? null,
    dueAt: raw.dueAt ?? null,
    isOverdue: raw.isOverdue === true,
    completedAt: raw.completedAt ?? null,
  }
}

export function toPlace(raw) {
  if (!raw) return null
  return { id: raw.id ?? null, code: raw.code ?? '', name: raw.name ?? null }
}

export function toCycleSummary(raw) {
  if (!raw) return null
  return { id: raw.id ?? null, name: raw.name ?? null, status: raw.status ?? null }
}

export function toQueueRow(raw = {}) {
  return {
    id: raw.id ?? null,
    number: raw.number ?? '',
    path: raw.path ?? null,
    pathLabel: raw.pathLabel ?? null,
    status: toRequestStatus(raw.status),
    isFrozen: raw.isFrozen === true,
    entity: toEntitySummary(raw.entity),
    assessor: toPerson(raw.assessor),
    assignedAt: raw.assignedAt ?? null,
    completionDeadline: raw.completionDeadline ?? null,
    awardCycle: toCycleSummary(raw.awardCycle),
    region: toPlace(raw.region),
    ...toClock(raw),
  }
}

export function toQueueStats(raw) {
  if (!raw) return null
  return {
    total: raw.total ?? 0,
    byStatus: toStatusCounts(raw.byStatus, STATS_STATUSES),
    overdue: raw.overdue ?? 0,
  }
}

function toAssignment(raw) {
  const assignment = {
    id: raw.id ?? null,
    assessor: toPerson(raw.assessor),
    status: raw.status ?? null,
    assignedAt: raw.assignedAt ?? null,
    startedAt: raw.startedAt ?? null,
    endedAt: raw.endedAt ?? null,
  }
  if (!Object.hasOwn(raw, 'reason')) return assignment
  return {
    ...assignment,
    assignedBy: toPerson(raw.assignedBy),
    reason: raw.reason ?? null,
    endReason: raw.endReason ?? null,
  }
}

export function toRequestDetail(raw = {}) {
  return {
    ...toQueueRow(raw),
    payment: { paid: raw.payment?.paid === true },
    record: toAssessmentRecord(raw.record),
    assignmentHistory: (raw.assignmentHistory ?? []).map(toAssignment),
    completionRequests: (raw.completionRequests ?? []).map(toCompletionRequest),
  }
}

export function toEligibleAssessor(raw = {}) {
  return { id: raw.id ?? null, name: raw.name ?? '', openRequests: raw.openRequests ?? 0 }
}

export function toAssignedRow(raw = {}) {
  return {
    id: raw.id ?? null,
    number: raw.number ?? '',
    path: raw.path ?? null,
    pathLabel: raw.pathLabel ?? null,
    status: toRequestStatus(raw.status),
    entity: raw.entity
      ? { ...toEntitySummary(raw.entity), region: toPlace(raw.entity.region) }
      : null,
    assignedAt: raw.assignedAt ?? null,
    startedAt: raw.startedAt ?? null,
    completionDeadline: raw.completionDeadline ?? null,
    awardCycle: toCycleSummary(raw.awardCycle),
    ...toClock(raw),
  }
}

export function toQueuePathCount(raw = {}) {
  return {
    path: raw.path ?? null,
    pathLabel: raw.pathLabel ?? null,
    total: raw.total ?? 0,
    awaitingAssignment: raw.awaitingAssignment ?? 0,
    overdue: raw.overdue ?? 0,
  }
}

export function toAssignedPathCount(raw = {}) {
  return {
    path: raw.path ?? null,
    pathLabel: raw.pathLabel ?? null,
    new: raw.new ?? 0,
    inProgress: raw.inProgress ?? 0,
    completed: raw.completed ?? 0,
  }
}
