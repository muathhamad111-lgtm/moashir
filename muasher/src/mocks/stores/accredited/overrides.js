import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { boundTree, exposesResult } from '../assessments/assessmentsStore'
import { ACTORS, transition } from '../assessments/statusHistory'
import { parseScoreSheet, validateScoreRequest } from '../assessments/scoreInput'
import { FORCE_SOURCES_BLOCKED, FORCE_TARGETS, STATES } from './accreditedStates'
import { activeAssignment, nowIso, touch } from './accreditedRecords'
import { assertNotFrozen } from './entityWorkflow'
import { recordSnapshot } from './resultSnapshots'
import { isActiveAssessor } from './staffDirectory'
import { optionalReason, throwIfErrors } from './accreditedValidation'

const OVERRIDE_REASON_MIN = 20

let reopenSequence = 90000

function overrideReason(body) {
  return optionalReason(body, { required: true, min: OVERRIDE_REASON_MIN })
}

function recordOverride(record, from, to, verb, at) {
  record.transitions.push(transition(from, to, verb, at, ACTORS.STAFF, null))
  touch(record)
}

function requireValues(scores) {
  const errors = {}
  ;['subs', 'points'].forEach((group) =>
    Object.entries(scores?.[group] ?? {}).forEach(([id, value]) => {
      if (value === null)
        errors[`scores.${group}.${id}`] = [
          fieldMessage('validation.required', {}, `scores.${group}`),
        ]
    }),
  )
  throwIfErrors(errors)
}

export function overrideScores(record, body) {
  const input = validateScoreRequest(body, record, 'present')
  requireValues(input.scores)
  overrideReason(body)
  assertNotFrozen(record)
  if (!exposesResult(record))
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), 'override_score')
  const changes = parseScoreSheet(input.scores, boundTree(record), record.scaleKind)
  record.scores = { ...record.scores, ...Object.fromEntries(changes) }
  recordSnapshot(record)
  recordOverride(record, record.status, record.status, 'override_score', nowIso())
  return record
}

function lastAssignment(record) {
  return record.assignments.at(-1) ?? null
}

export function reopenAssessment(record, body) {
  overrideReason(body)
  assertNotFrozen(record)
  if (record.path !== ASSESSMENT_PATHS.ACCREDITED || record.status !== STATES.COMPLETED) {
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), 'reopen')
  }
  const at = nowIso()
  const last = lastAssignment(record)
  const target = last && isActiveAssessor(last.assessorId) ? STATES.UNDER_REVIEW : STATES.SUBMITTED
  if (target === STATES.UNDER_REVIEW) {
    reopenSequence += 1
    record.assignments.push({
      id: reopenSequence,
      assessorId: last.assessorId,
      assignedById: null,
      status: 'active',
      reason: null,
      startedAt: at,
      endedAt: null,
      endReason: null,
      createdAt: at,
    })
  }
  Object.assign(record, { status: target, completedAt: null })
  recordOverride(record, STATES.COMPLETED, target, 'reopen', at)
  return record
}

function validateTarget(body) {
  if (!FORCE_TARGETS.includes(body.toStatus))
    throw assessmentReject.validation({
      toStatus: [fieldMessage('validation.invalid', {}, 'toStatus')],
    })
}

function endActive(record, at) {
  const active = activeAssignment(record)
  if (active)
    Object.assign(active, { status: 'ended', endedAt: at, endReason: 'forced_transition' })
}

const TARGET_EFFECTS = {
  [STATES.DRAFT]: (record, at) => {
    endActive(record, at)
    Object.assign(record, {
      submittedAt: null,
      dueAt: null,
      reviewSlaDays: null,
      completedAt: null,
    })
  },
  [STATES.SUBMITTED]: (record, at) => {
    endActive(record, at)
    record.completedAt = null
  },
  [STATES.ASSIGNED]: (record) => {
    if (!activeAssignment(record))
      throw assessmentReject.coded(409, 'assessment.assignment_required')
    record.completedAt = null
  },
  [STATES.UNDER_REVIEW]: (record) => {
    if (!activeAssignment(record))
      throw assessmentReject.coded(409, 'assessment.assignment_required')
    record.completedAt = null
  },
  [STATES.COMPLETED]: (record, at) => {
    if (!record.result) throw assessmentReject.coded(409, 'assessment.result_required')
    const active = activeAssignment(record)
    if (active) Object.assign(active, { status: 'completed', endedAt: at })
    record.completedAt = at
  },
}

export function forceTransition(record, body) {
  validateTarget(body)
  overrideReason(body)
  assertNotFrozen(record)
  if (
    record.path !== ASSESSMENT_PATHS.ACCREDITED ||
    FORCE_SOURCES_BLOCKED.includes(record.status)
  ) {
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), 'force_transition')
  }
  const at = nowIso()
  const from = record.status
  TARGET_EFFECTS[body.toStatus](record, at)
  record.status = body.toStatus
  recordOverride(record, from, body.toStatus, 'force_transition', at)
  return record
}
