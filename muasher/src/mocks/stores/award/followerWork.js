import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { ACTORS, transition, visibleActorId } from '../assessments/statusHistory'
import { activeAssignment, nowIso, openCompletionOf, touch } from '../accredited/accreditedRecords'
import { assertNotFrozen } from '../accredited/entityWorkflow'
import { completionItem, itemErrors } from '../accredited/assessorWork'
import {
  lengthError,
  reasonError,
  throwIfErrors,
  trimmed,
} from '../accredited/accreditedValidation'
import { ADVANCED_STATES, isWithdrawable } from './advancedStates'
import { findCycle } from './cycleStore'
import { awardReject } from './awardRejection'
import { announceReturned, announceWithdrawn } from './awardNotices'
import { releaseCommitteeWork } from '../committee/committeeRelease'

const NOTES = { MIN: 10, MAX: 2000 }
const EXPIRY_OUTCOMES = ['continue_as_submitted', 'decline']

let returnSequence = 80000

function deadlineError(value) {
  const time = typeof value === 'string' ? Date.parse(value) : Number.NaN
  if (Number.isNaN(time)) return [fieldMessage('validation.required', {}, 'deadlineAt')]
  if (time <= Date.now()) return [fieldMessage('validation.after_now', {}, 'deadlineAt')]
  return null
}

function validateReturn(body) {
  const errors = {}
  const notes = lengthError(body.notes, { min: NOTES.MIN, max: NOTES.MAX, attribute: 'notes' })
  if (notes) errors.notes = notes
  itemErrors(body.items, errors)
  const deadline = deadlineError(body.deadlineAt)
  if (deadline) errors.deadlineAt = deadline
  if (!EXPIRY_OUTCOMES.includes(body.expiryOutcome))
    errors.expiryOutcome = [fieldMessage('validation.required', {}, 'expiryOutcome')]
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
  return trimmed(body.reason) || null
}

function assertBeforeCommittee(record, deadlineAt) {
  const cycle = findCycle(record.awardCycleId)
  if (new Date(deadlineAt).getTime() > new Date(cycle.committeeStart).getTime())
    throw awardReject.deadlineAfterCommittee(cycle.committeeStart)
}

export function returnForCompletion(user, record, body) {
  const reason = validateReturn(body)
  assertNotFrozen(record)
  if (user.superAdmin && !reason) throw assessmentReject.reasonRequired()
  if (record.status !== ADVANCED_STATES.AWAITING_ASSIGNMENT)
    throw assessmentReject.illegalTransition(
      toCanonicalStatus(record.status),
      'return_for_completion',
    )
  const deadlineAt = new Date(body.deadlineAt).toISOString()
  assertBeforeCommittee(record, deadlineAt)
  const items = body.items.map((item, index) => completionItem(record, item, index))
  const at = nowIso()
  returnSequence += 1
  const completion = {
    id: returnSequence,
    status: 'open',
    notes: body.notes.trim(),
    items,
    deadlineAt,
    expiryOutcome: body.expiryOutcome,
    createdAt: at,
    resolvedAt: null,
  }
  record.completionRequests.push(completion)
  record.status = ADVANCED_STATES.NEEDS_COMPLETION
  record.transitions.push(
    transition(
      ADVANCED_STATES.AWAITING_ASSIGNMENT,
      ADVANCED_STATES.NEEDS_COMPLETION,
      'return_for_completion',
      at,
      ACTORS.STAFF,
      visibleActorId(user),
    ),
  )
  touch(record)
  announceReturned(record, completion)
  return record
}

export function withdrawRequest(user, record, body) {
  const reasonFault = reasonError(body.reason, { required: true })
  if (reasonFault) throw assessmentReject.validation({ reason: reasonFault })
  if (!isWithdrawable(record.status))
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), 'withdraw')
  const at = nowIso()
  const assignment = activeAssignment(record)
  if (assignment)
    Object.assign(assignment, { status: 'ended', endedAt: at, endReason: 'withdrawn' })
  const completion = openCompletionOf(record)
  if (completion) Object.assign(completion, { status: 'cancelled', resolvedAt: at })
  const from = record.status
  Object.assign(record, {
    status: ADVANCED_STATES.WITHDRAWN,
    withdrawnAt: at,
    withdrawalReason: trimmed(body.reason),
    updatedAt: at,
  })
  record.transitions.push(
    transition(from, ADVANCED_STATES.WITHDRAWN, 'withdraw', at, ACTORS.STAFF, visibleActorId(user)),
  )
  releaseCommitteeWork(record, at)
  announceWithdrawn(record)
  return record
}
