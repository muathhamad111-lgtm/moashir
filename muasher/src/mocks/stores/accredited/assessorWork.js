import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { boundTree, isOpen } from '../assessments/assessmentsStore'
import { ACTORS, transition, visibleActorId } from '../assessments/statusHistory'
import { parseScoreSheet, validateScoreRequest } from '../assessments/scoreInput'
import { scorableUnits, unitKey } from '../assessments/selfScoring'
import { STATES } from './accreditedStates'
import { activeAssignment, nowIso, subOfId, touch } from './accreditedRecords'
import { assertNotFrozen } from './entityWorkflow'
import { isAdvanced, stagesOf } from '../assessments/reviewedPaths'
import { announceInitialCompleted } from '../award/awardNotices'
import { recordSnapshot } from './resultSnapshots'
import { writeSummary } from '../insights/assessorSummaries'
import {
  isPlainObject,
  isPositiveInteger,
  lengthError,
  optionalReason,
  reasonError,
  throwIfErrors,
  trimmed,
} from './accreditedValidation'

const FEEDBACK_FIELDS = ['strengths', 'improvementPoints', 'notes']
const FEEDBACK_MAX = 5000
const SUMMARY_MAX = 2000
const FEEDBACK_MAX_ITEMS = 500
const NOTES = { MIN: 10, MAX: 2000 }
const ITEMS_MAX = 100
const ITEM_NOTE_MAX = 500
const KINDS = ['cv', 'evidence']
const EXPIRY_OUTCOMES = ['continue_as_submitted', 'decline']
const DEADLINE_MAX_DAYS = 60
const DAY_MS = 24 * 60 * 60 * 1000

let completionSequence = 70000

export function reviewModeOf(user, record) {
  const assignee = activeAssignment(record)?.assessorId === user.id
  return isOpen(record) && (user.superAdmin || assignee) ? 'edit' : 'read_only'
}

function enterReviewGate(user, record, { verb, reason, states }) {
  assertNotFrozen(record)
  if (user.superAdmin && !reason) throw assessmentReject.reasonRequired()
  if (!states.includes(record.status))
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), verb)
}

export function startReview(user, record, body) {
  const reason = optionalReason(body)
  const stages = stagesOf(record)
  if (record.status === stages.reviewing) return record
  enterReviewGate(user, record, { verb: 'start_review', reason, states: [stages.assigned] })
  const at = nowIso()
  activeAssignment(record).startedAt = at
  record.status = stages.reviewing
  record.transitions.push(
    transition(
      stages.assigned,
      stages.reviewing,
      'start_review',
      at,
      ACTORS.STAFF,
      visibleActorId(user),
    ),
  )
  touch(record)
  return record
}

function clearedKeys(scores) {
  return ['subs', 'points'].flatMap((group) =>
    Object.entries(scores[group] ?? {})
      .filter(([, value]) => value === null)
      .map(([id]) => unitKey(group === 'subs' ? 'sub' : 'point', id)),
  )
}

export function saveReviewScores(user, record, body) {
  const input = validateScoreRequest(body, record, 'present')
  enterReviewGate(user, record, {
    verb: 'score',
    reason: input.reason,
    states: [stagesOf(record).reviewing],
  })
  const changes = parseScoreSheet(input.scores, boundTree(record), record.scaleKind)
  const merged = { ...record.scores, ...Object.fromEntries(changes) }
  clearedKeys(input.scores).forEach((key) => delete merged[key])
  record.scores = merged
  touch(record)
  return record
}

function validateFeedback(body) {
  const errors = {}
  const entries = isPlainObject(body.feedback) ? Object.entries(body.feedback) : []
  if (!entries.length || entries.length > FEEDBACK_MAX_ITEMS)
    errors.feedback = [fieldMessage('validation.required', {}, 'feedback')]
  entries.forEach(([subId, fields]) => {
    if (
      !isPlainObject(fields) ||
      Object.keys(fields).some((field) => !FEEDBACK_FIELDS.includes(field))
    ) {
      errors[`feedback.${subId}`] = [fieldMessage('validation.invalid', {}, 'feedback')]
      return
    }
    Object.entries(fields).forEach(([field, text]) => {
      if (text !== null && (typeof text !== 'string' || text.length > FEEDBACK_MAX)) {
        errors[`feedback.${subId}.${field}`] = [
          fieldMessage('validation.max_chars', { max: FEEDBACK_MAX }, 'feedback'),
        ]
      }
    })
  })
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
  return entries
}

function applyFeedback(record, sub, fields) {
  const current = { ...record.feedback[sub.code] }
  Object.entries(fields).forEach(([field, text]) => {
    current[field] = typeof text === 'string' && text.trim() ? text : null
  })
  if (FEEDBACK_FIELDS.every((field) => !current[field])) delete record.feedback[sub.code]
  else record.feedback[sub.code] = current
}

export function saveFeedbackItems(user, record, body) {
  const entries = validateFeedback(body)
  enterReviewGate(user, record, {
    verb: 'feedback',
    reason: trimmed(body.reason) || null,
    states: [stagesOf(record).reviewing],
  })
  const targets = entries.map(([subId, fields]) => {
    const sub = subOfId(record, subId)
    if (!sub)
      throw assessmentReject.coded(422, 'assessment.sub_not_in_version', `feedback.${subId}`)
    return { sub, fields }
  })
  targets.forEach(({ sub, fields }) => applyFeedback(record, sub, fields))
  touch(record)
  return record
}

function validateSummary(body) {
  const summary = body.summary
  const errors = {}
  if (summary !== null && summary !== undefined && typeof summary !== 'string')
    errors.summary = [fieldMessage('validation.invalid', {}, 'summary')]
  else if (typeof summary === 'string' && summary.trim().length > SUMMARY_MAX)
    errors.summary = [fieldMessage('validation.max_chars', { max: SUMMARY_MAX }, 'summary')]
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
}

export function saveAssessorSummary(user, record, body) {
  if (isAdvanced(record)) throw assessmentReject.coded(409, 'assessment.summary_not_applicable')
  validateSummary(body)
  enterReviewGate(user, record, {
    verb: 'summary',
    reason: trimmed(body.reason) || null,
    states: [stagesOf(record).reviewing],
  })
  const saved = writeSummary(record.id, body.summary)
  touch(record)
  return saved
}

function deadlineError(value) {
  const time = typeof value === 'string' ? Date.parse(value) : Number.NaN
  if (Number.isNaN(time)) return [fieldMessage('validation.required', {}, 'deadlineAt')]
  if (time <= Date.now()) return [fieldMessage('validation.after_now', {}, 'deadlineAt')]
  if (time > Date.now() + DEADLINE_MAX_DAYS * DAY_MS)
    return [fieldMessage('validation.before_or_equal', { days: DEADLINE_MAX_DAYS }, 'deadlineAt')]
  return null
}

export function itemErrors(items, errors) {
  if (!Array.isArray(items) || !items.length || items.length > ITEMS_MAX) {
    errors.items = [fieldMessage('validation.required', {}, 'items')]
    return
  }
  items.forEach((item, index) => {
    if (!isPositiveInteger(item?.subCriterionId))
      errors[`items.${index}.subCriterionId`] = [
        fieldMessage('validation.required', {}, 'subCriterionId'),
      ]
    if (!KINDS.includes(item?.kind))
      errors[`items.${index}.kind`] = [fieldMessage('validation.invalid', {}, 'items')]
    if (item?.note && (typeof item.note !== 'string' || item.note.length > ITEM_NOTE_MAX)) {
      errors[`items.${index}.note`] = [
        fieldMessage('validation.max_chars', { max: ITEM_NOTE_MAX }, 'notes'),
      ]
    }
  })
}

function validateCompletion(body) {
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
}

export function completionItem(record, item, index) {
  const sub = subOfId(record, item.subCriterionId)
  if (!sub)
    throw assessmentReject.coded(
      422,
      'assessment.sub_not_in_version',
      `items.${index}.subCriterionId`,
    )
  if (item.kind === 'cv' && !sub.hasCv)
    throw assessmentReject.coded(422, 'cv.not_allowed', `items.${index}.kind`)
  return { subCode: sub.code, kind: item.kind, note: trimmed(item.note) || null }
}

export function requestCompletion(user, record, body) {
  validateCompletion(body)
  const states = isAdvanced(record) ? [] : [STATES.UNDER_REVIEW]
  enterReviewGate(user, record, {
    verb: 'request_completion',
    reason: trimmed(body.reason) || null,
    states,
  })
  const items = body.items.map((item, index) => completionItem(record, item, index))
  const at = nowIso()
  completionSequence += 1
  record.completionRequests.push({
    id: completionSequence,
    status: 'open',
    notes: body.notes.trim(),
    items,
    deadlineAt: new Date(body.deadlineAt).toISOString(),
    expiryOutcome: body.expiryOutcome,
    createdAt: at,
    resolvedAt: null,
  })
  record.status = STATES.NEEDS_COMPLETION
  record.transitions.push(
    transition(
      STATES.UNDER_REVIEW,
      STATES.NEEDS_COMPLETION,
      'request_completion',
      at,
      ACTORS.STAFF,
      visibleActorId(user),
    ),
  )
  touch(record)
  return record
}

function missingUnits(record) {
  return scorableUnits(boundTree(record)).filter(
    (unit) => record.scores[unitKey(unit.level, unit.id)] === undefined,
  )
}

function finishReview(record, at) {
  if (!isAdvanced(record)) {
    Object.assign(record, { status: STATES.COMPLETED, completedAt: at, updatedAt: at })
    return
  }
  Object.assign(record, { status: stagesOf(record).done, initialAssessedAt: at, updatedAt: at })
  announceInitialCompleted(record)
}

export function completeReview(user, record, body) {
  const reason = optionalReason(body)
  const stages = stagesOf(record)
  enterReviewGate(user, record, { verb: 'complete', reason, states: [stages.reviewing] })
  if (record.criteriaUpdatePendingSince !== null) throw assessmentReject.criteriaUpdateInProgress()
  const missing = missingUnits(record)
  if (missing.length) throw assessmentReject.scoresIncomplete(missing)
  const at = nowIso()
  recordSnapshot(record)
  Object.assign(activeAssignment(record), { status: 'completed', endedAt: at })
  record.transitions.push(
    transition(stages.reviewing, stages.done, 'complete', at, ACTORS.STAFF, visibleActorId(user)),
  )
  finishReview(record, at)
  return record
}
