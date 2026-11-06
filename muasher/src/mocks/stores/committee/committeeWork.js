import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { paginate } from '../../contract/pagination'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { boundTree, findAssessment, isFrozen } from '../assessments/assessmentsStore'
import { parseScoreSheet, validateScoreRequest } from '../assessments/scoreInput'
import { scorableUnits, unitKey } from '../assessments/selfScoring'
import { subOfId } from '../accredited/accreditedRecords'
import { assertNotFrozen } from '../accredited/entityWorkflow'
import {
  isPlainObject,
  optionalReason,
  reasonError,
  throwIfErrors,
  trimmed,
} from '../accredited/accreditedValidation'
import { findCycle } from '../award/cycleStore'
import { ADVANCED_STATES as S, isInCommittee } from '../award/advancedStates'
import { committeeState, decisionsOf, findSeat } from './committeeState'
import {
  COMMITTEE_STAGE,
  moveRecord,
  nowIso,
  settleRecord,
  syncCycleStage,
} from './committeeLifecycle'
import { committeeScoresResource, rowResult } from './committeeSerializers'
import { requireReason } from './committeeAccess'

const FILTER_STATUSES = ['not-started', 'in-progress', 'completed']
const NOTE_MAX = 5000
const NOTES_MAX_ITEMS = 500

export function myRows(user, query) {
  const cycleId = query.get('filter[cycleId]')
  const status = query.get('filter[status]')
  if (status && !FILTER_STATUSES.includes(status))
    throw assessmentReject.validation({
      'filter.status': [fieldMessage('validation.invalid', {}, 'filter')],
    })
  const rows = committeeState.assignments
    .filter((row) => row.status !== 'voided' && findSeat(row.seatId).userId === user.id)
    .filter((row) => !isFrozen(findAssessment(row.assessmentId)))
    .filter((row) => !cycleId || row.cycleId === Number(cycleId))
    .filter((row) => !status || row.status === status)
    .sort((left, right) => right.id - left.id)
  return paginate(rows, query)
}

export function authorizeOwnRow(user, row) {
  if (!row || row.status === 'voided') throw assessmentReject.notFound()
  if (!user.superAdmin && findSeat(row.seatId).userId !== user.id) throw assessmentReject.notFound()
}

export function evaluationMode(user, row) {
  const seat = findSeat(row.seatId)
  const record = findAssessment(row.assessmentId)
  const works =
    seat.status === 'active' &&
    ['not-started', 'in-progress'].includes(row.status) &&
    isInCommittee(record.status) &&
    COMMITTEE_STAGE.includes(findCycle(row.cycleId).status)
  return works && (user.superAdmin || seat.userId === user.id) ? 'edit' : 'read_only'
}

export function latestReturnNote(row) {
  if (!row.returnedAt) return null
  const decision = decisionsOf(row.assessmentId)
    .filter((item) => item.kind === 'return_to_committee')
    .at(-1)
  return decision ? { note: decision.note, returnedAt: decision.createdAt } : null
}

function assertWritable(user, row, verb, reason) {
  const seat = findSeat(row.seatId)
  const record = findAssessment(row.assessmentId)
  requireReason(user, reason)
  if (!user.superAdmin && seat.status !== 'active')
    throw assessmentReject.coded(403, 'committee.membership_inactive')
  if (row.status === 'completed') throw assessmentReject.coded(409, 'committee.evaluation_locked')
  if (!COMMITTEE_STAGE.includes(findCycle(row.cycleId).status))
    throw assessmentReject.coded(409, 'cycle.committee_not_started')
  assertNotFrozen(record)
  if (!isInCommittee(record.status) || row.status === 'voided')
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), verb)
  return record
}

function startRow(row, record) {
  if (row.status === 'not-started')
    Object.assign(row, { status: 'in-progress', startedAt: row.startedAt ?? nowIso() })
  if (record.status === S.RETURNED_TO_COMMITTEE) moveRecord(record, S.UNDER_COMMITTEE, 'resume')
}

export function saveCommitteeScores(user, row, body) {
  const input = validateScoreRequest(body, findAssessment(row.assessmentId), 'present')
  const record = assertWritable(user, row, 'score', input.reason)
  const changes = parseScoreSheet(input.scores, boundTree(record), record.scaleKind)
  const merged = { ...row.scores, ...Object.fromEntries(changes) }
  ;['subs', 'points'].forEach((group) => {
    Object.entries(input.scores[group] ?? {})
      .filter(([, value]) => value === null)
      .forEach(([id]) => delete merged[unitKey(group === 'subs' ? 'sub' : 'point', id)])
  })
  row.scores = merged
  startRow(row, record)
  return committeeScoresResource(row, record)
}

function validatedNotes(record, body) {
  const errors = {}
  const hasNotes = isPlainObject(body.notes)
  if (!hasNotes && body.generalNote === undefined)
    errors.notes = [fieldMessage('validation.required', {}, 'notes')]
  const entries = hasNotes ? Object.entries(body.notes) : []
  if (entries.length > NOTES_MAX_ITEMS)
    errors.notes = [fieldMessage('validation.invalid', {}, 'notes')]
  entries.forEach(([subId, text]) => {
    if (text !== null && (typeof text !== 'string' || text.length > NOTE_MAX))
      errors[`notes.${subId}`] = [fieldMessage('validation.max_chars', { max: NOTE_MAX }, 'notes')]
  })
  if (
    body.generalNote !== undefined &&
    body.generalNote !== null &&
    (typeof body.generalNote !== 'string' || body.generalNote.length > NOTE_MAX)
  ) {
    errors.generalNote = [fieldMessage('validation.max_chars', { max: NOTE_MAX }, 'notes')]
  }
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
  entries.forEach(([subId]) => {
    if (!subOfId(record, subId))
      throw assessmentReject.coded(422, 'assessment.sub_not_in_version', `notes.${subId}`)
  })
  return entries
}

export function saveCommitteeNotes(user, row, body) {
  const entries = validatedNotes(findAssessment(row.assessmentId), body)
  const record = assertWritable(user, row, 'notes', trimmed(body.reason) || null)
  const notes = { ...row.notes }
  entries.forEach(([subId, text]) => {
    if (trimmed(text)) notes[String(subId)] = text
    else delete notes[String(subId)]
  })
  row.notes = notes
  if (body.generalNote !== undefined)
    row.generalNote = trimmed(body.generalNote) ? body.generalNote : null
  startRow(row, record)
  return { assignmentId: row.id, subs: { ...row.notes }, general: row.generalNote }
}

export function submitEvaluation(user, row, body) {
  const record = assertWritable(user, row, 'submit', optionalReason(body))
  const missing = scorableUnits(boundTree(record)).filter(
    (unit) => row.scores[unitKey(unit.level, unit.id)] === undefined,
  )
  if (missing.length) throw assessmentReject.scoresIncomplete(missing)
  const at = nowIso()
  Object.assign(row, {
    status: 'completed',
    submittedAt: at,
    startedAt: row.startedAt ?? at,
    percent: rowResult(row, record).percent,
  })
  settleRecord(record)
  syncCycleStage(findCycle(row.cycleId))
  return {
    id: row.id,
    status: row.status,
    percent: committeeScoresResource(row, record).percent,
    submittedAt: at,
  }
}
