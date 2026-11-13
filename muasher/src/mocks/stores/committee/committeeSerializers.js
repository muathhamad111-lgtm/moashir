import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { boundTree } from '../assessments/assessmentsStore'
import {
  computeSelfResult,
  decimalRaw,
  displayPercent,
  rawPercent,
  scaleMaximumHundredths,
  scorableUnits,
  unitKey,
} from '../assessments/selfScoring'
import { boundSubsOf, presentEvidence } from '../accredited/accreditedRecords'
import { cvBySubId, evidenceList } from '../accredited/accreditedSerializers'
import { findCycle } from '../award/cycleStore'
import { staffNameOf } from '../accredited/staffDirectory'
import { committeeState, findAssignment, findSeat, rowsOfSeat } from './committeeState'
import { committeeEntityOf } from './committeeEntity'

const SCORE_GROUPS = { sub: 'subs', point: 'points' }
const PROGRESS_KEYS = {
  'not-started': 'notStarted',
  'in-progress': 'inProgress',
  completed: 'completed',
  voided: 'voided',
}

export function percentOf(row) {
  return row.percent === null ? null : displayPercent(row.percent)
}

export function memberResource(seat) {
  const progress = { notStarted: 0, inProgress: 0, completed: 0, voided: 0 }
  rowsOfSeat(seat.id).forEach((row) => {
    progress[PROGRESS_KEYS[row.status]] += 1
  })
  return {
    id: seat.id,
    user: { id: seat.userId, name: staffNameOf(seat.userId) },
    status: seat.status,
    isSubstitute: seat.replacesId !== null,
    replacesId: seat.replacesId,
    replacedById: committeeState.seats.find((other) => other.replacesId === seat.id)?.id ?? null,
    appointedAt: seat.createdAt,
    disabledAt: seat.disabledAt,
    disabledReason: seat.disabledReason,
    removedAt: seat.removedAt,
    progress,
  }
}

function isOverdue(row, cycle) {
  return (
    ['not-started', 'in-progress'].includes(row.status) &&
    Date.parse(cycle.committeeEnd) < Date.now()
  )
}

export function assignmentRowResource(row, record) {
  const cycle = findCycle(row.cycleId)
  const seat = findSeat(row.seatId)
  return {
    id: row.id,
    assessmentId: row.assessmentId,
    cycle: { id: cycle.id, name: { ...cycle.name }, status: cycle.status },
    entity: committeeEntityOf(record),
    status: row.status,
    membershipStatus: seat.status,
    isSubstitute: row.replacesAssignmentId !== null,
    substituteApprovedAt: row.substituteApprovedAt,
    dueAt: cycle.committeeEnd,
    isOverdue: isOverdue(row, cycle),
    percent: percentOf(row),
    startedAt: row.startedAt,
    submittedAt: row.submittedAt,
    returnedAt: row.returnedAt,
  }
}

function scoresByGroup(row, record, units) {
  const groups = { subs: {}, points: {} }
  units.forEach((unit) => {
    const raw = row.scores[unitKey(unit.level, unit.id)]
    if (raw === undefined) return
    groups[SCORE_GROUPS[unit.level]][String(unit.id)] = {
      rawValue: decimalRaw(raw),
      percent: displayPercent(rawPercent(raw, record.scaleKind)),
    }
  })
  return groups
}

export function committeeScoresResource(row, record) {
  const units = scorableUnits(boundTree(record))
  return {
    assignmentId: row.id,
    status: row.status,
    scale: {
      kind: toCanonicalStatus(record.scaleKind),
      maximum: decimalRaw(scaleMaximumHundredths(record.scaleKind)),
    },
    scores: scoresByGroup(row, record, units),
    progress: {
      scored: units.filter((unit) => row.scores[unitKey(unit.level, unit.id)] !== undefined).length,
      required: units.length,
    },
    percent: percentOf(row),
  }
}

export function notesResource(row) {
  return { subs: { ...row.notes }, general: row.generalNote }
}

export function rowResult(row, record) {
  return computeSelfResult(boundTree(record), new Map(Object.entries(row.scores)), record.scaleKind)
}

function priorResource(row, record) {
  const replaced = row.replacesAssignmentId ? findAssignment(row.replacesAssignmentId) : null
  if (!replaced) return null
  const scores = committeeScoresResource(replaced, record)
  return {
    scores: scores.scores,
    progress: scores.progress,
    percent: scores.percent,
    status: scores.status,
    notes: notesResource(replaced),
    viewOnly: true,
  }
}

export function evaluationResource(row, record, { mode, returnNote }) {
  const subs = boundSubsOf(record)
  return {
    assignment: assignmentRowResource(row, record),
    mode,
    ...committeeScoresResource(row, record),
    criteria: boundTree(record),
    cv: cvBySubId(record, subs),
    evidence: evidenceList(
      presentEvidence(record).filter((file) => file.scanStatus === 'clean'),
      record,
    ),
    notes: notesResource(row),
    returnNote,
    prior: priorResource(row, record),
  }
}
