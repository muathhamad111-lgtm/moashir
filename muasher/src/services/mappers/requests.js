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

function toQueueEntity(raw) {
  const entity = toEntitySummary(raw)
  return entity ? { ...entity, level: raw.level ?? null, lastLevel: raw.lastLevel ?? null } : null
}

export function toQueueRow(raw = {}) {
  return {
    id: raw.id ?? null,
    number: raw.number ?? '',
    path: raw.path ?? null,
    pathLabel: raw.pathLabel ?? null,
    status: toRequestStatus(raw.status),
    isFrozen: raw.isFrozen === true,
    entity: toQueueEntity(raw.entity),
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

function toRequestReceipt(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    number: raw.number ?? null,
    status: raw.status ?? null,
    isTest: raw.isTest === true,
  }
}

export function toRequestPayment(raw) {
  if (!raw?.reference) return null
  return {
    id: raw.id ?? null,
    reference: raw.reference,
    channel: raw.channel ?? null,
    status: raw.status ?? null,
    isTest: raw.isTest === true,
    paidAt: raw.paidAt ?? null,
    receipt: toRequestReceipt(raw.receipt),
  }
}

export function toProgressSummary(raw) {
  if (!raw) return null
  return { complete: raw.subsComplete ?? 0, total: raw.subsTotal ?? 0 }
}

export function toPreparingRow(raw = {}) {
  return {
    id: raw.id ?? null,
    number: raw.number ?? '',
    path: raw.path ?? null,
    pathLabel: raw.pathLabel ?? null,
    status: toRequestStatus(raw.status),
    isFrozen: raw.isFrozen === true,
    entity: toQueueEntity(raw.entity),
    awardCycle: toCycleSummary(raw.awardCycle),
    openedAt: raw.openedAt ?? null,
    payment: toRequestPayment(raw.payment),
    progress: toProgressSummary(raw.progress),
    lastActivityAt: raw.lastActivityAt ?? null,
  }
}

export function toRequestDetail(raw = {}) {
  return {
    ...toQueueRow(raw),
    payment: { paid: raw.payment?.paid === true, details: toRequestPayment(raw.payment) },
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
    preparing: raw.preparing ?? 0,
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
