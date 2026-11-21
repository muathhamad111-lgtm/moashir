// Mirrors backend AssessorBoardQuery + AssessorActionItems: the assessor's live figures are computed
// from the same assigned records as «الطلبات المسندة إليّ», so the two screens always agree. The
// period figures and charts come from the assessor overview (stores/assessorOverview.js).
import { isFrozen } from '../assessments/assessmentsStore'
import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { isOverdue, reviewedRecords } from './accreditedRecords'

const ASSIGNED_LINK = '/admin/requests/assigned'
const DUE_SOON_DAYS = 7
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

function isDueWithinWeek(record, now) {
  if (!record.dueAt || isOverdue(record)) return false
  const due = new Date(record.dueAt).getTime()
  return due >= now && due < now + DUE_SOON_DAYS * DAY_MS
}

function openCounts(records) {
  const now = Date.now()
  return {
    toStart: countWhere(records, TO_START),
    inProgress: countWhere(records, IN_PROGRESS),
    awaitingEntity: countWhere(records, AWAITING_ENTITY),
    overdue: records.filter((record) => isOverdue(record)).length,
    dueWithinWeek: records.filter((record) => isDueWithinWeek(record, now)).length,
  }
}

function card(key, value) {
  return { key, value, format: 'count', link: ASSIGNED_LINK }
}

export function assessorBoard(user) {
  const open = openCounts(activeFor(user))
  return {
    cards: [
      card('to_start', open.toStart),
      card('in_progress', open.inProgress),
      card('awaiting_entity', open.awaitingEntity),
      card('overdue', open.overdue),
      card('due_within_week', open.dueWithinWeek),
    ],
    widgets: [],
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
