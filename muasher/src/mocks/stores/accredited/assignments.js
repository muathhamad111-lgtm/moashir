import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { ACTORS, transition, visibleActorId } from '../assessments/statusHistory'
import { findAssessment } from '../assessments/assessmentsStore'
import { STATES } from './accreditedStates'
import { activeAssignment, nowIso, reviewedRecords, touch } from './accreditedRecords'
import { addDays } from './dates'
import { isAdvanced, pathRules, stagesOf } from '../assessments/reviewedPaths'
import { findCycle, isInitialAssessor, isMember } from '../award/cycleStore'
import { announceInitialAssessorAssigned } from '../award/awardNotices'
import { activeAssessors, isActiveAssessor } from './staffDirectory'
import { assertNotFrozen } from './entityWorkflow'
import { isPositiveInteger, reasonError, throwIfErrors, trimmed } from './accreditedValidation'
import { qualificationsOf } from '../staffUsers'

const QUALIFICATION_FOR = { accredited: 'accredited', advanced: 'advanced_initial' }

function isQualified(assessorId, path) {
  return qualificationsOf(assessorId).includes(QUALIFICATION_FOR[path])
}

const SEARCH_MAX = 100
const LIST_MAX = 100

let assignmentSequence = 50000

function openRequestsOf(assessorId) {
  return reviewedRecords().filter(
    (record) =>
      pathRules(record.path).isOpen(record.status) &&
      activeAssignment(record)?.assessorId === assessorId,
  ).length
}

function cycleOf(record) {
  return record && isAdvanced(record) ? findCycle(record.awardCycleId) : null
}

function candidatesFor(record, path) {
  const qualified = activeAssessors().filter((staff) => isQualified(staff.id, path))
  const cycle = cycleOf(record)
  if (!cycle) return qualified
  return qualified.filter(
    (staff) => isInitialAssessor(cycle, staff.id) && !isMember(cycle, staff.id),
  )
}

function scopedRecordOf(assessmentId, followed) {
  const record = assessmentId ? findAssessment(assessmentId) : null
  if (assessmentId && (!record || !followed.includes(record.path)))
    throw assessmentReject.validation({
      assessmentId: [fieldMessage('validation.invalid', {}, 'assessorId')],
    })
  return record
}

export function eligibleAssessors(user, query, followed) {
  const errors = {}
  const assessmentId = query.get('assessmentId')
  const path = query.get('path')
  if (assessmentId && !isPositiveInteger(assessmentId))
    errors.assessmentId = [fieldMessage('validation.integer', {}, 'assessorId')]
  if (path && !followed.includes(path))
    errors.path = [fieldMessage('validation.invalid', {}, 'filter')]
  if ((query.get('search') ?? '').length > SEARCH_MAX)
    errors.search = [fieldMessage('validation.max_chars', { max: SEARCH_MAX }, 'filter')]
  throwIfErrors(errors)
  const record = scopedRecordOf(assessmentId, followed)
  const currentId = record ? (activeAssignment(record)?.assessorId ?? null) : null
  const needle = (query.get('search') ?? '').trim().toLowerCase()
  return candidatesFor(record, record?.path ?? path ?? followed[0])
    .filter((staff) => staff.id !== user.id && staff.id !== currentId)
    .filter((staff) => !needle || staff.name.toLowerCase().includes(needle))
    .slice(0, LIST_MAX)
    .map((staff) => ({ id: staff.id, name: staff.name, openRequests: openRequestsOf(staff.id) }))
}

function validateAssignment(body, { reasonRequired }) {
  const errors = {}
  if (!isPositiveInteger(body.assessorId))
    errors.assessorId = [fieldMessage('validation.required', {}, 'assessorId')]
  const reason = reasonError(body.reason, { required: reasonRequired })
  if (reason) errors.reason = reason
  throwIfErrors(errors)
  return { assessorId: Number(body.assessorId), reason: trimmed(body.reason) || null }
}

function assertAssessor(user, record, assessorId) {
  if (assessorId === user.id) throw assessmentReject.coded(422, 'sod_self_assignment', 'assessorId')
  if (!isActiveAssessor(assessorId))
    throw assessmentReject.coded(422, 'assessor.invalid', 'assessorId')
  if (!isQualified(assessorId, record.path))
    throw assessmentReject.notQualified(QUALIFICATION_FOR[record.path], record.path)
  const cycle = cycleOf(record)
  if (!cycle) return
  if (isMember(cycle, assessorId)) throw assessmentReject.coded(422, 'sod_conflict', 'assessorId')
  if (!isInitialAssessor(cycle, assessorId))
    throw assessmentReject.coded(422, 'assessor.not_in_cycle', 'assessorId')
}

function newAssignment(user, record, { assessorId, reason }, at) {
  assignmentSequence += 1
  const started =
    record.status === stagesOf(record).reviewing || record.status === STATES.NEEDS_COMPLETION
  const assignment = {
    id: assignmentSequence,
    assessorId,
    assignedById: user.superAdmin ? null : user.id,
    status: 'active',
    reason,
    startedAt: started ? at : null,
    endedAt: null,
    endReason: null,
    createdAt: at,
  }
  record.assignments.push(assignment)
  return assignment
}

export function assignAssessor(user, record, body) {
  const input = validateAssignment(body, { reasonRequired: false })
  assertNotFrozen(record)
  if (user.superAdmin && !input.reason) throw assessmentReject.reasonRequired()
  const stages = stagesOf(record)
  if (record.status !== stages.awaiting)
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), 'assign')
  assertAssessor(user, record, input.assessorId)
  const at = nowIso()
  newAssignment(user, record, input, at)
  record.status = stages.assigned
  record.transitions.push(
    transition(stages.awaiting, stages.assigned, 'assign', at, ACTORS.STAFF, visibleActorId(user)),
  )
  const cycle = cycleOf(record)
  if (cycle) {
    Object.assign(record, {
      dueAt: addDays(at, cycle.initialReviewSlaDays),
      reviewSlaDays: cycle.initialReviewSlaDays,
    })
    announceInitialAssessorAssigned(record)
  }
  touch(record)
  return record
}

export function reassignAssessor(user, record, body) {
  const input = validateAssignment(body, { reasonRequired: true })
  assertNotFrozen(record)
  if (!pathRules(record.path).hasAssessor(record.status))
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), 'reassign')
  const current = activeAssignment(record)
  if (!current) throw assessmentReject.coded(409, 'assessment.assignment_required')
  if (current.assessorId === input.assessorId)
    throw assessmentReject.coded(422, 'assessor.same', 'assessorId')
  assertAssessor(user, record, input.assessorId)
  const at = nowIso()
  Object.assign(current, { status: 'reassigned', endedAt: at })
  newAssignment(user, record, input, at)
  record.transitions.push(
    transition(record.status, record.status, 'reassign', at, ACTORS.STAFF, visibleActorId(user)),
  )
  touch(record)
  return record
}
