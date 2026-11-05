import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { entityOf } from '../assessments/assessmentsStore'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { ACTORS, actorOf, transition, visibleActorId } from '../assessments/statusHistory'
import { isDeleted } from '../entitiesStore'
import { assessmentSettings } from './assessmentSettingsStore'
import { STATES, acceptsEntityContent } from './accreditedStates'
import { addDays } from './dates'
import { gapsOf, nowIso, openCompletionOf, subOfId, touch } from './accreditedRecords'
import { isPlainObject, optionalReason, reasonError, throwIfErrors } from './accreditedValidation'
import { isAdvanced } from '../assessments/reviewedPaths'
import { ADVANCED_STATES } from '../award/advancedStates'
import { acceptsApplications, findCycle } from '../award/cycleStore'
import { eligibilityOf, failuresOf } from '../award/eligibility'
import { announceSubmitted } from '../award/awardNotices'
import { conditionsRejection } from '../award/awardRejection'

const CV_MAX = 10000
const CV_MAX_ITEMS = 500
const ACTIVE_ENTITY = 'active'

export function assertNotFrozen(record) {
  if (isDeleted(entityOf(record))) throw assessmentReject.entityDeleted()
}

export function enterEntityGate(user, record, { verb, reason }) {
  assertNotFrozen(record)
  if (entityOf(record).status !== ACTIVE_ENTITY) throw assessmentReject.entityNotActive()
  if (user.superAdmin && !reason) throw assessmentReject.reasonRequired()
  if (!acceptsEntityContent(record.status))
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), verb)
  if (deadlinePassed(record)) throw assessmentReject.coded(409, 'completion.deadline_passed')
  if (
    isAdvanced(record) &&
    record.status === STATES.DRAFT &&
    !acceptsApplications(findCycle(record.awardCycleId))
  ) {
    throw assessmentReject.coded(409, 'cycle.submission_closed')
  }
}

function deadlinePassed(record) {
  const completion = record.status === STATES.NEEDS_COMPLETION ? openCompletionOf(record) : null
  return Boolean(completion) && new Date(completion.deadlineAt).getTime() <= Date.now()
}

export function isRequestedItem(record, sub, kind) {
  if (record.status !== STATES.NEEDS_COMPLETION) return true
  const completion = openCompletionOf(record)
  return Boolean(completion?.items.some((item) => item.subCode === sub.code && item.kind === kind))
}

function validateCvBody(body) {
  const errors = {}
  const entries = isPlainObject(body.cv) ? Object.entries(body.cv) : null
  if (!entries || !entries.length || entries.length > CV_MAX_ITEMS)
    errors.cv = [fieldMessage('validation.required', {}, 'cv')]
  ;(entries ?? []).forEach(([subId, text]) => {
    if (text !== null && (typeof text !== 'string' || text.length > CV_MAX)) {
      errors[`cv.${subId}`] = [fieldMessage('validation.max_chars', { max: CV_MAX }, 'cv')]
    }
  })
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
  return entries
}

function cvChange(record, subId, text) {
  const sub = subOfId(record, subId)
  const field = `cv.${subId}`
  if (!sub) throw assessmentReject.coded(422, 'assessment.sub_not_in_version', field)
  if (!sub.hasCv) throw assessmentReject.coded(422, 'cv.not_allowed', field)
  if (!isRequestedItem(record, sub, 'cv'))
    throw assessmentReject.coded(422, 'completion.item_not_requested', field)
  return { code: sub.code, text: typeof text === 'string' && text.trim() ? text : null }
}

export function saveAccreditedCv(user, record, body) {
  const entries = validateCvBody(body)
  const reason = optionalReason(body)
  enterEntityGate(user, record, { verb: 'save_cv', reason })
  const changes = entries.map(([subId, text]) => cvChange(record, subId, text))
  changes.forEach(({ code, text }) => {
    if (text === null) delete record.cv[code]
    else record.cv[code] = text
  })
  touch(record)
  return record
}

function submissionFields(gaps) {
  return {
    ...Object.fromEntries(
      gaps.missingCv.map((item) => [
        `cv.${item.subCriterionId}`,
        [fieldMessage('submission.cv_missing', { code: item.code })],
      ]),
    ),
    ...Object.fromEntries(
      gaps.missingAttachments.map((item) => [
        `attachments.${item.subCriterionId}.${item.attachmentKey}`,
        [fieldMessage('submission.attachment_missing', { code: item.code })],
      ]),
    ),
  }
}

function firstSubmission(user, record, at) {
  const slaDays = assessmentSettings().accreditedReviewSlaDays
  Object.assign(record, {
    status: STATES.SUBMITTED,
    submittedAt: at,
    dueAt: addDays(at, slaDays),
    reviewSlaDays: slaDays,
    updatedAt: at,
  })
  record.transitions.push(
    transition(
      STATES.DRAFT,
      STATES.SUBMITTED,
      'submit',
      at,
      actorOf(user, record),
      visibleActorId(user),
    ),
  )
}

function resubmission(user, record, at) {
  const completion = openCompletionOf(record)
  completion.status = 'resubmitted'
  completion.resolvedAt = at
  Object.assign(record, { status: STATES.UNDER_REVIEW, updatedAt: at })
  record.transitions.push(
    transition(
      STATES.NEEDS_COMPLETION,
      STATES.UNDER_REVIEW,
      'resubmit',
      at,
      user.superAdmin ? ACTORS.STAFF : ACTORS.ENTITY,
      visibleActorId(user),
    ),
  )
}

function advancedSubmission(user, record, at) {
  const from = record.status
  const entity = entityOf(record)
  const completion = openCompletionOf(record)
  if (completion) Object.assign(completion, { status: 'resubmitted', resolvedAt: at })
  Object.assign(record, {
    status: ADVANCED_STATES.AWAITING_ASSIGNMENT,
    submittedAt: from === STATES.DRAFT ? at : record.submittedAt,
    regionId: entity.regionId,
    updatedAt: at,
  })
  const verb = from === STATES.DRAFT ? 'submit' : 'resubmit'
  record.transitions.push(
    transition(
      from,
      ADVANCED_STATES.AWAITING_ASSIGNMENT,
      verb,
      at,
      actorOf(user, record),
      visibleActorId(user),
    ),
  )
  announceSubmitted(record, { resubmission: from !== STATES.DRAFT })
}

function assertAdvancedConditions(record) {
  if (record.status !== STATES.DRAFT) return
  const failures = failuresOf(eligibilityOf(entityOf(record), findCycle(record.awardCycleId)))
  if (failures.length) throw conditionsRejection(failures)
}

export function submitReviewedRequest(user, record, body) {
  const reason = optionalReason(body)
  enterEntityGate(user, record, { verb: 'submit', reason })
  if (record.criteriaUpdatePendingSince !== null) throw assessmentReject.criteriaUpdateInProgress()
  if (isAdvanced(record)) assertAdvancedConditions(record)
  const gaps = gapsOf(record)
  if (gaps.missingCv.length || gaps.missingAttachments.length)
    throw assessmentReject.submissionIncomplete(gaps, submissionFields(gaps))
  const at = nowIso()
  if (isAdvanced(record)) advancedSubmission(user, record, at)
  else if (record.status === STATES.DRAFT) firstSubmission(user, record, at)
  else resubmission(user, record, at)
  return record
}
