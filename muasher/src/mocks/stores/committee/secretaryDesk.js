import { paginate } from '../../contract/pagination'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { entityOf, findAssessment, isFrozen } from '../assessments/assessmentsStore'
import { snapshotResource, mainRows, pointRows, subRows } from '../assessments/selfSerializers'
import { displayEarned, decimalWeight, displayPercent } from '../assessments/selfScoring'
import { entitySummary } from '../entitySerializers'
import { staffNameOf } from '../accredited/staffDirectory'
import { assertNotFrozen } from '../accredited/entityWorkflow'
import {
  isPositiveInteger,
  lengthError,
  optionalReason,
  reasonError,
  throwIfErrors,
  trimmed,
} from '../accredited/accreditedValidation'
import { ADVANCED_STATES as S, COMMITTEE_STATES, RESULT_STATES } from '../award/advancedStates'
import {
  committeeState,
  decisionsOf,
  discussionsOf,
  findAssignment,
  findSeat,
  insert,
  rowsOfRecord,
} from './committeeState'
import {
  averageResource,
  countedRows,
  currentAverage,
  isComplete,
  pendingSubstitutes,
  recordAverage,
} from './committeeTally'
import {
  assertState,
  cancelOpenDiscussion,
  cycleRecords,
  moveRecord,
  nowIso,
  settleRecord,
  syncCycleStage,
} from './committeeLifecycle'
import {
  committeeScoresResource,
  notesResource,
  percentOf,
  rowResult,
} from './committeeSerializers'
import { placeOfRegion } from './committeeEntity'
import { discussionResource } from './committeeDiscussions'
import { requireReason } from './committeeAccess'
import { announceAverageApproved, announceReturnedToCommittee } from './committeeNotices'

const DESK_STATES = [...COMMITTEE_STATES, ...RESULT_STATES]
const SORTS = {
  submittedAt: (record) => record.submittedAt,
  average: (record) => Number(currentAverage(record)?.percent ?? -1),
}
const RETURN_NOTE = { MIN: 10, MAX: 2000 }
const NOTE_MAX = 1000

function initialPercent(record) {
  return record.result ? displayPercent(record.result.percent) : null
}

export function secretaryRow(record) {
  const live = rowsOfRecord(record.id).filter((row) => row.status !== 'voided')
  return {
    id: record.id,
    number: record.number,
    status: record.status,
    isFrozen: isFrozen(record),
    entity: entitySummary(entityOf(record)),
    region: placeOfRegion(record.regionId),
    initialPercent: initialPercent(record),
    average: averageResource(currentAverage(record)),
    committee: {
      assignments: live.length,
      completed: live.filter((row) => row.status === 'completed').length,
      pendingSubstitutions: pendingSubstitutes(record).length,
    },
    submittedAt: record.submittedAt,
  }
}

function filtersOf(query) {
  const errors = {}
  const status = query.get('filter[status]')
  if (status && !DESK_STATES.includes(status))
    errors['filter.status'] = [fieldMessage('validation.invalid', {}, 'filter')]
  const regionId = query.get('filter[regionId]')
  if (regionId && !isPositiveInteger(regionId))
    errors['filter.regionId'] = [fieldMessage('validation.integer', {}, 'filter')]
  const sort = query.get('sort') ?? '-submitted_at'
  const field = sort.replace(/^-/, '').replace(/_([a-z])/g, (match, letter) => letter.toUpperCase())
  if (!Object.hasOwn(SORTS, field)) errors.sort = [fieldMessage('validation.invalid', {}, 'sort')]
  throwIfErrors(errors)
  return {
    status,
    regionId: regionId ? Number(regionId) : null,
    search: trimmed(query.get('search')),
    field,
    descending: sort.startsWith('-'),
  }
}

function matches(record, needle, normalize) {
  if (!needle) return true
  const entity = entityOf(record)
  return [record.number, entity.name.ar, entity.name.en].some((value) =>
    normalize(value ?? '').includes(normalize(needle)),
  )
}

function compare(field, descending) {
  return (left, right) => {
    const a = SORTS[field](left)
    const b = SORTS[field](right)
    if (a === b) return right.id - left.id
    return (a < b ? -1 : 1) * (descending ? -1 : 1)
  }
}

export function secretaryQueue(cycle, query, normalize) {
  const filters = filtersOf(query)
  const scoped = cycleRecords(cycle)
    .filter((record) => DESK_STATES.includes(record.status))
    .filter((record) => filters.regionId === null || record.regionId === filters.regionId)
    .filter((record) => matches(record, filters.search, normalize))
  const rows = scoped
    .filter((record) => !filters.status || record.status === filters.status)
    .sort(compare(filters.field, filters.descending))
  const { items, pagination } = paginate(rows, query)
  const counts = Object.fromEntries(
    DESK_STATES.map((status) => [
      status.replace(/-([a-z])/g, (match, letter) => letter.toUpperCase()),
      scoped.filter((record) => record.status === status).length,
    ]),
  )
  return { items: items.map(secretaryRow), meta: { counts, pagination } }
}

export function deskRecord(cycle, assessmentId) {
  const record = findAssessment(assessmentId)
  if (
    !record ||
    record.awardCycleId !== cycle.id ||
    !DESK_STATES.includes(record.status) ||
    isFrozen(record)
  )
    throw assessmentReject.notFound()
  return record
}

function evaluationSummary(row, counted) {
  const seat = findSeat(row.seatId)
  return {
    id: row.id,
    member: {
      id: seat.id,
      user: { id: seat.userId, name: staffNameOf(seat.userId) },
      status: seat.status,
    },
    status: row.status,
    counted: counted.includes(row.id),
    isSubstitute: row.replacesAssignmentId !== null,
    replacesAssignmentId: row.replacesAssignmentId,
    substituteApprovedAt: row.substituteApprovedAt,
    percent: percentOf(row),
    startedAt: row.startedAt,
    submittedAt: row.submittedAt,
    returnedAt: row.returnedAt,
    voidedAt: row.voidedAt,
    voidReason: row.voidReason,
  }
}

function decisionResource(decision) {
  return {
    id: decision.id,
    kind: decision.kind,
    note: decision.note,
    committeeAverageId: decision.committeeAverageId,
    decidedBy: {
      id: decision.decidedById,
      name: decision.decidedById === null ? null : staffNameOf(decision.decidedById),
    },
    decidedAt: decision.createdAt,
  }
}

export function secretaryDetail(record) {
  const counted = countedRows(record).map((row) => row.id)
  const { total, maxTotal, percent } = record.result ? snapshotResource(record) : {}
  return {
    id: record.id,
    number: record.number,
    status: record.status,
    entity: entitySummary(entityOf(record)),
    region: placeOfRegion(record.regionId),
    initialResult: record.result ? { total, maxTotal, percent } : null,
    average: averageResource(currentAverage(record)),
    allSubmitted: isComplete(record),
    pendingAssignmentIds: countedRows(record)
      .filter((row) => row.status !== 'completed')
      .map((row) => row.id),
    pendingSubstitutionIds: pendingSubstitutes(record).map((row) => row.id),
    evaluations: rowsOfRecord(record.id).map((row) => evaluationSummary(row, counted)),
    decisions: decisionsOf(record.id).map(decisionResource),
    discussions: discussionsOf(record.id).map(discussionResource),
  }
}

export function secretaryEvaluation(record, assignmentId) {
  const row = findAssignment(assignmentId)
  if (!row || row.assessmentId !== record.id) throw assessmentReject.notFound()
  const seat = findSeat(row.seatId)
  const result = rowResult(row, record)
  return {
    id: row.id,
    member: {
      id: seat.id,
      user: { id: seat.userId, name: staffNameOf(seat.userId) },
      status: seat.status,
    },
    isSubstitute: row.replacesAssignmentId !== null,
    submittedAt: row.submittedAt,
    ...committeeScoresResource(row, record),
    notes: notesResource(row),
    result: {
      total: displayEarned(result.total),
      maxTotal: decimalWeight(result.totalWeight),
      percent: displayPercent(result.percent),
      main: mainRows(result),
      sub: subRows(result),
      points: pointRows(result),
    },
  }
}

export function recordDecision(user, cycle, record, kind, extra = {}) {
  return insert('decisions', {
    cycleId: cycle.id,
    assessmentId: record?.id ?? null,
    regionId: null,
    kind,
    note: null,
    committeeAverageId: record?.committeeAverageId ?? null,
    decidedById: user.superAdmin ? null : user.id,
    createdAt: nowIso(),
    ...extra,
  })
}

function assertCompleteCommittee(record) {
  if (!isComplete(record)) throw assessmentReject.coded(422, 'committee.incomplete')
}

export function recomputeAverage(user, cycle, record, body) {
  requireReason(user, optionalReason(body))
  assertState(record, COMMITTEE_STATES, 'recompute_average')
  assertNotFrozen(record)
  assertCompleteCommittee(record)
  const { average, recomputed } = recordAverage(record, { isManual: true })
  return { recomputed, committeeAverageId: average.id }
}

function noteOf(body) {
  if (
    body.note !== undefined &&
    body.note !== null &&
    (typeof body.note !== 'string' || body.note.length > NOTE_MAX)
  ) {
    throw assessmentReject.validation({
      note: [fieldMessage('validation.max_chars', { max: NOTE_MAX }, 'note')],
    })
  }
  return trimmed(body.note) || null
}

export function approveAverage(user, cycle, record, body) {
  const note = noteOf(body)
  requireReason(user, optionalReason(body))
  assertNotFrozen(record)
  assertState(record, [S.AWAITING_SECRETARY], 'approve')
  if (pendingSubstitutes(record).length)
    throw assessmentReject.coded(409, 'committee.substitution_pending')
  assertCompleteCommittee(record)
  const average = currentAverage(record) ?? recordAverage(record).average
  moveRecord(record, S.APPROVED, 'approve', user)
  insert('rankings', {
    cycleId: cycle.id,
    assessmentId: record.id,
    regionId: record.regionId,
    averagePercent: average.percent,
    regionalRank: null,
    nationalRank: null,
    createdAt: nowIso(),
  })
  recordDecision(user, cycle, record, 'approve_average', { note })
  announceAverageApproved(record)
  syncCycleStage(cycle)
  return record
}

export function returnToCommittee(user, cycle, record, body) {
  const fault = lengthError(body.generalNote, {
    min: RETURN_NOTE.MIN,
    max: RETURN_NOTE.MAX,
    attribute: 'generalNote',
  })
  const reason = reasonError(body.reason)
  throwIfErrors({ ...(fault ? { generalNote: fault } : {}), ...(reason ? { reason } : {}) })
  requireReason(user, trimmed(body.reason) || null)
  assertNotFrozen(record)
  assertState(record, [S.AWAITING_SECRETARY, S.COMMITTEE_DISCUSSION], 'return_to_committee')
  const at = nowIso()
  cancelOpenDiscussion(record, at)
  countedRows(record).forEach((row) =>
    Object.assign(row, { status: 'in-progress', submittedAt: null, percent: null, returnedAt: at }),
  )
  recordDecision(user, cycle, record, 'return_to_committee', { note: trimmed(body.generalNote) })
  record.committeeAverageId = null
  moveRecord(record, S.RETURNED_TO_COMMITTEE, 'return_to_committee', user, at)
  announceReturnedToCommittee(record, trimmed(body.generalNote))
  syncCycleStage(cycle)
  return record
}

export function approveSubstitution(user, cycle, record, substitutionId, body) {
  const note = noteOf(body)
  requireReason(user, optionalReason(body))
  assertNotFrozen(record)
  const row = findAssignment(substitutionId)
  if (!row || row.assessmentId !== record.id || row.replacesAssignmentId === null)
    throw assessmentReject.notFound()
  assertState(record, COMMITTEE_STATES, 'approve_substitute')
  if (row.substituteApprovedAt !== null)
    throw assessmentReject.illegalTransition('approved', 'approve_substitute')
  if (row.status !== 'completed')
    throw assessmentReject.coded(409, 'committee.substitute_not_completed')
  const at = nowIso()
  row.substituteApprovedAt = at
  const replaced = findAssignment(row.replacesAssignmentId)
  Object.assign(replaced, { status: 'voided', voidedAt: at, voidReason: 'replaced' })
  const replacedSeat = findSeat(replaced.seatId)
  const stillPending = committeeState.assignments.some(
    (other) => other.seatId === replacedSeat.id && other.status !== 'voided',
  )
  if (!stillPending) replacedSeat.status = 'excluded'
  recordDecision(user, cycle, record, 'approve_substitute', { note })
  settleRecord(record)
  syncCycleStage(cycle)
  return record
}
