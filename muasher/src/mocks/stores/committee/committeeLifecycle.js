import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { assessmentReject } from '../assessments/assessmentRejection'
import { allAssessments, isFrozen } from '../assessments/assessmentsStore'
import { ACTORS, transition, visibleActorId } from '../assessments/statusHistory'
import { ADVANCED_STATES as S, PRECEDES_SECRETARY, isInCommittee } from '../award/advancedStates'
import { activeSeatsOf, insert, liveRowsOfRecord, openDiscussionOf } from './committeeState'
import { isComplete, recordAverage } from './committeeTally'
import { announceDistributed, announceAllSubmitted } from './committeeNotices'

export const COMMITTEE_STAGE = ['committee-assessment', 'awaiting-secretary']
const BACK_TO_COMMITTEE = [S.AWAITING_SECRETARY, S.COMMITTEE_DISCUSSION]

export function nowIso() {
  return new Date().toISOString()
}

export function moveRecord(record, to, verb, user = null, at = nowIso()) {
  const actor = user ? ACTORS.STAFF : ACTORS.SYSTEM
  record.transitions.push(
    transition(record.status, to, verb, at, actor, user ? visibleActorId(user) : null),
  )
  Object.assign(record, { status: to, updatedAt: at })
}

export function assertState(record, states, verb) {
  if (!states.includes(record.status))
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), verb)
}

export function cycleRecords(cycle) {
  return allAssessments().filter((record) => record.awardCycleId === cycle.id && !isFrozen(record))
}

export function newRow(seat, record, extra = {}) {
  return insert('assignments', {
    cycleId: seat.cycleId,
    seatId: seat.id,
    assessmentId: record.id,
    status: 'not-started',
    replacesAssignmentId: null,
    substituteApprovedAt: null,
    scores: {},
    notes: {},
    generalNote: null,
    percent: null,
    startedAt: null,
    submittedAt: null,
    returnedAt: null,
    voidedAt: null,
    voidReason: null,
    createdAt: nowIso(),
    ...extra,
  })
}

export function voidRows(rows, reason, at = nowIso()) {
  rows.forEach((row) => Object.assign(row, { status: 'voided', voidedAt: at, voidReason: reason }))
}

export function cancelOpenDiscussion(record, at = nowIso()) {
  const discussion = openDiscussionOf(record.id)
  if (discussion) Object.assign(discussion, { status: 'cancelled', closedAt: at })
}

function sendBackToCommittee(record, verb) {
  cancelOpenDiscussion(record)
  record.committeeAverageId = null
  moveRecord(record, S.UNDER_COMMITTEE, verb)
}

export function settleRecord(record) {
  if (!isInCommittee(record.status)) return
  if (!isComplete(record)) {
    if (BACK_TO_COMMITTEE.includes(record.status)) sendBackToCommittee(record, 'roster_changed')
    return
  }
  recordAverage(record)
  if ([S.UNDER_COMMITTEE, S.RETURNED_TO_COMMITTEE].includes(record.status)) {
    moveRecord(record, S.AWAITING_SECRETARY, 'average_computed')
    announceAllSubmitted(record)
  }
}

function seatRows(seat, record) {
  return liveRowsOfRecord(record.id).some((row) => row.seatId === seat.id)
}

export function distributeRecord(cycle, record) {
  const created = activeSeatsOf(cycle.id)
    .filter((seat) => !seatRows(seat, record))
    .map((seat) => newRow(seat, record))
  if (record.status === S.QUALIFIED) moveRecord(record, S.UNDER_COMMITTEE, 'distribute')
  else if (created.length && BACK_TO_COMMITTEE.includes(record.status))
    sendBackToCommittee(record, 'distribute')
  return created
}

export function distributeCycle(cycle) {
  const records = cycleRecords(cycle).filter(
    (record) => record.status === S.QUALIFIED || isInCommittee(record.status),
  )
  const created = records.flatMap((record) => distributeRecord(cycle, record))
  if (created.length) announceDistributed(cycle, created)
  return created
}

export function syncCycleStage(cycle) {
  if (!COMMITTEE_STAGE.includes(cycle.status)) return
  const waiting = cycleRecords(cycle).some((record) => PRECEDES_SECRETARY.includes(record.status))
  const next = waiting ? 'committee-assessment' : 'awaiting-secretary'
  if (next !== cycle.status) Object.assign(cycle, { status: next, updatedAt: nowIso() })
}

export function isCommitteeRunning(cycle) {
  return COMMITTEE_STAGE.includes(cycle.status)
}

export function startCommittee(user, cycle, reason) {
  if (user.superAdmin && !reason) throw assessmentReject.reasonRequired()
  if (cycle.status !== 'submission-closed')
    throw assessmentReject.illegalTransition(toCanonicalStatus(cycle.status), 'start_committee')
  if (!activeSeatsOf(cycle.id).length) throw assessmentReject.coded(422, 'committee.none_active')
  const at = nowIso()
  const startsLater = Date.parse(cycle.committeeStart) > Date.parse(at)
  Object.assign(cycle, {
    status: 'committee-assessment',
    committeeStartedAt: at,
    committeeStart: startsLater ? at : cycle.committeeStart,
    updatedAt: at,
  })
  distributeCycle(cycle)
  syncCycleStage(cycle)
  return cycle
}
