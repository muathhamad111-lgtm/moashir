import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { usersFixture } from '../../fixtures/users'
import { grantedPermissionKeys } from '../permissionsStore'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { allAssessments } from '../assessments/assessmentsStore'
import {
  isPositiveInteger,
  optionalReason,
  reasonError,
  trimmed,
} from '../accredited/accreditedValidation'
import { isLinkedAssessor, isMember } from '../award/cycleStore'
import { isInCommittee } from '../award/advancedStates'
import {
  activeSeatsOf,
  findAssignment,
  insert,
  liveRowsOfRecord,
  rowsOfSeat,
  seatOfUser,
  seatsOfCycle,
} from './committeeState'
import {
  COMMITTEE_STAGE,
  cycleRecords,
  distributeCycle,
  newRow,
  nowIso,
  settleRecord,
  syncCycleStage,
  voidRows,
} from './committeeLifecycle'
import { requireReason } from './committeeAccess'

const SCORE_PERMISSION = 'award.committee.score'
const LOCKED_CYCLE = ['approved', 'announced', 'closed']
const CANDIDATES_MAX = 100

function isCandidate(userId) {
  const staff = usersFixture.find((user) => user.id === userId && !user.roles.includes('entity'))
  return (
    Boolean(staff) &&
    staff.status === 'active' &&
    grantedPermissionKeys(staff.roles).includes(SCORE_PERMISSION)
  )
}

function initialAssessorOfCycle(cycle, userId) {
  return (
    isLinkedAssessor(cycle, userId) ||
    allAssessments().some(
      (record) =>
        record.awardCycleId === cycle.id &&
        record.assignments.some((item) => item.assessorId === userId),
    )
  )
}

function conflicts(cycle, userId) {
  return isMember(cycle, userId) || initialAssessorOfCycle(cycle, userId)
}

function assertOpenCycle(cycle, verb) {
  if (LOCKED_CYCLE.includes(cycle.status))
    throw assessmentReject.illegalTransition(toCanonicalStatus(cycle.status), verb)
}

function assertSeatStatus(seat, status, verb) {
  if (seat.status !== status)
    throw assessmentReject.illegalTransition(toCanonicalStatus(seat.status), verb)
}

function candidateId(body, field, errors) {
  const value = body[field]
  if (!isPositiveInteger(value)) errors[field] = [fieldMessage('validation.required', {}, field)]
  return Number(value)
}

function assertCandidate(cycle, userId, field) {
  if (!isCandidate(userId)) throw assessmentReject.coded(422, 'committee.candidate_invalid', field)
  if (seatOfUser(cycle.id, userId))
    throw assessmentReject.coded(422, 'committee.member_exists', field)
  if (conflicts(cycle, userId)) throw assessmentReject.coded(422, 'sod_conflict', field)
}

function validated(body, field) {
  const errors = {}
  const userId = candidateId(body, field, errors)
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  if (Object.keys(errors).length) throw assessmentReject.validation(errors)
  return { userId, reason: trimmed(body.reason) || null }
}

function createSeat(cycle, userId, replacesId = null) {
  return insert('seats', {
    cycleId: cycle.id,
    userId,
    status: 'active',
    replacesId,
    disabledAt: null,
    disabledReason: null,
    removedAt: null,
    createdAt: nowIso(),
  })
}

export function rosterOf(cycle) {
  return seatsOfCycle(cycle.id)
}

export function committeeCandidates(cycle, search, normalize) {
  const needle = normalize(trimmed(search))
  return usersFixture
    .filter(
      (user) =>
        isCandidate(user.id) && !seatOfUser(cycle.id, user.id) && !conflicts(cycle, user.id),
    )
    .filter((user) => !needle || normalize(user.name).includes(needle))
    .sort((left, right) => left.name.localeCompare(right.name))
    .slice(0, CANDIDATES_MAX)
    .map((user) => ({ id: user.id, name: user.name }))
}

export function appointMember(user, cycle, body) {
  const { userId, reason } = validated(body, 'userId')
  requireReason(user, reason)
  assertOpenCycle(cycle, 'appoint')
  assertCandidate(cycle, userId, 'userId')
  const seat = createSeat(cycle, userId)
  if (COMMITTEE_STAGE.includes(cycle.status)) {
    distributeCycle(cycle)
    syncCycleStage(cycle)
  }
  return seat
}

function committeeRunsRequests(cycle) {
  return cycleRecords(cycle).some((record) => isInCommittee(record.status))
}

function assertAnotherActive(cycle, seat) {
  const others = activeSeatsOf(cycle.id).filter((other) => other.id !== seat.id)
  if (!others.length && committeeRunsRequests(cycle))
    throw assessmentReject.coded(422, 'committee.none_active')
}

function affectedRecords(rows) {
  const ids = [...new Set(rows.map((row) => row.assessmentId))]
  return allAssessments().filter((record) => ids.includes(record.id))
}

export function removeMember(user, cycle, seat, body) {
  requireReason(user, optionalReason(body))
  assertSeatStatus(seat, 'active', 'remove')
  const rows = rowsOfSeat(seat.id).filter((row) => row.status !== 'voided')
  if (rows.some((row) => row.status !== 'not-started' || Object.keys(row.scores).length))
    throw assessmentReject.coded(422, 'committee_assessor.has_scores')
  assertAnotherActive(cycle, seat)
  Object.assign(seat, { status: 'removed', removedAt: nowIso() })
  voidRows(rows, 'removed')
  affectedRecords(rows).forEach(settleRecord)
  syncCycleStage(cycle)
}

export function disableMember(user, cycle, seat, body) {
  const fault = reasonError(body.reason, { required: true })
  if (fault) throw assessmentReject.validation({ reason: fault })
  assertSeatStatus(seat, 'active', 'disable')
  Object.assign(seat, {
    status: 'disabled',
    disabledAt: nowIso(),
    disabledReason: trimmed(body.reason),
    disabledById: user.superAdmin ? null : user.id,
  })
  return seat
}

export function excludeMember(user, cycle, seat, body) {
  requireReason(user, optionalReason(body))
  assertSeatStatus(seat, 'disabled', 'exclude')
  assertAnotherActive(cycle, seat)
  const rows = rowsOfSeat(seat.id).filter(
    (row) => row.status !== 'voided' && isInCommittee(recordOf(row)?.status),
  )
  Object.assign(seat, { status: 'excluded' })
  voidRows(rows, 'excluded')
  const affected = affectedRecords(rows)
  affected.forEach(settleRecord)
  syncCycleStage(cycle)
  const recomputed = affected
    .filter((record) => Boolean(record.committeeAverageId))
    .map((record) => record.id)
  return {
    seat,
    meta: {
      affectedRequestIds: affected.map((record) => record.id),
      recomputedRequestIds: recomputed,
    },
  }
}

function recordOf(row) {
  return allAssessments().find((record) => record.id === row.assessmentId) ?? null
}

export function substituteMember(user, cycle, seat, body) {
  const { userId, reason } = validated(body, 'substituteUserId')
  requireReason(user, reason)
  assertSeatStatus(seat, 'disabled', 'substitute')
  assertCandidate(cycle, userId, 'substituteUserId')
  const substitute = createSeat(cycle, userId, seat.id)
  rowsOfSeat(seat.id)
    .filter((row) => row.status !== 'voided' && isInCommittee(recordOf(row)?.status))
    .forEach((row) => newRow(substitute, recordOf(row), { replacesAssignmentId: row.id }))
  affectedRecords(rowsOfSeat(substitute.id)).forEach(settleRecord)
  syncCycleStage(cycle)
  return substitute
}

export function replacedRowOf(row) {
  return row.replacesAssignmentId ? findAssignment(row.replacesAssignmentId) : null
}

export function liveRowOfSeat(seat, record) {
  return liveRowsOfRecord(record.id).find((row) => row.seatId === seat.id) ?? null
}
