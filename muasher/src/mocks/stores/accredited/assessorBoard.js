// Mirrors backend AssessorBoardQuery + AssessorActionItems: the assessor's home is computed from
// the same assigned records as «الطلبات المسندة إليّ», so the two screens always agree.
import { entityOf, isFrozen } from '../assessments/assessmentsStore'
import { pathLabelOf } from '../../fixtures/pathLabels'
import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { LOWEST_CRITERIA } from '../../fixtures/overviewSnapshot'
import { isOverdue, reviewedRecords } from './accreditedRecords'

const ASSIGNED_LINK = '/admin/requests/assigned'
const DUE_SOON = 5
const WEAKEST_CRITERIA = 2
const DAY_MS = 86400000
const TO_START = ['assigned']
const IN_PROGRESS = ['under_review', 'under_initial_assessment']
const AWAITING_ENTITY = ['needs_completion']

function activeFor(user) {
  return reviewedRecords()
    .filter((record) => !isFrozen(record))
    .filter((record) =>
      record.assignments.some(
        (assignment) => assignment.assessorId === user.id && assignment.status === 'active',
      ),
    )
}

function countWhere(records, statuses) {
  return records.filter((record) => statuses.includes(toCanonicalStatus(record.status))).length
}

function openCounts(records) {
  return {
    toStart: countWhere(records, TO_START),
    inProgress: countWhere(records, IN_PROGRESS),
    awaitingEntity: countWhere(records, AWAITING_ENTITY),
    overdue: records.filter((record) => isOverdue(record)).length,
  }
}

function completedAssignments(user) {
  return reviewedRecords()
    .flatMap((record) => record.assignments)
    .filter((assignment) => assignment.assessorId === user.id && assignment.status === 'completed')
}

function averageDays(assignments) {
  const timed = assignments.filter((assignment) => assignment.endedAt)
  if (!timed.length) return null
  const total = timed.reduce(
    (sum, item) => sum + (new Date(item.endedAt) - new Date(item.createdAt)),
    0,
  )
  return (total / timed.length / DAY_MS).toFixed(1)
}

function byDueDate(left, right) {
  if (left.dueAt === right.dueAt) return left.id - right.id
  if (!left.dueAt) return 1
  if (!right.dueAt) return -1
  return new Date(left.dueAt) - new Date(right.dueAt)
}

function dueSoonRow(record) {
  return {
    id: record.id,
    number: record.number,
    path: record.path,
    pathLabel: pathLabelOf(record.path),
    status: toCanonicalStatus(record.status),
    entityName: entityOf(record).name,
    dueAt: record.dueAt,
    isOverdue: isOverdue(record),
  }
}

function card(key, value, extra = {}) {
  return { key, value, format: 'count', link: ASSIGNED_LINK, ...extra }
}

export function assessorBoard(user) {
  const active = activeFor(user)
  const open = openCounts(active)
  const history = completedAssignments(user)
  return {
    cards: [
      card('to_start', open.toStart),
      card('in_progress', open.inProgress),
      card('awaiting_entity', open.awaitingEntity),
      card('overdue', open.overdue),
      card('completed', history.length),
      card('average_duration_days', averageDays(history), { format: 'days', link: null }),
    ],
    widgets: [
      { key: 'due_soon', data: [...active].sort(byDueDate).slice(0, DUE_SOON).map(dueSoonRow) },
      { key: 'weakest_criteria', data: LOWEST_CRITERIA.slice(0, WEAKEST_CRITERIA) },
    ],
  }
}

function actionItem(key, count, severity) {
  return { key, count, severity, link: ASSIGNED_LINK, dueAt: null, context: null }
}

export function assessorActionItems(user) {
  const open = openCounts(activeFor(user))
  return [
    actionItem('overdue_reviews', open.overdue, 'danger'),
    actionItem('start_reviews', open.toStart, 'warning'),
    actionItem('continue_reviews', open.inProgress, 'info'),
  ].filter((item) => item.count > 0)
}
