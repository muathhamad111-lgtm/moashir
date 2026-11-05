import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import {
  allAssessments,
  insertAssessment,
  nextRequestNumber,
  removeAssessment,
} from '../assessments/assessmentsStore'
import { assessmentResource } from '../assessments/selfSerializers'
import { actorOf, transition, visibleActorId } from '../assessments/statusHistory'
import { findEntity } from '../entitiesStore'
import { nowIso } from '../accredited/accreditedRecords'
import {
  isPositiveInteger,
  optionalReason,
  throwIfErrors,
} from '../accredited/accreditedValidation'
import { ADVANCED_STATES } from './advancedStates'
import { acceptsApplications, findCycle, runningCycle } from './cycleStore'
import { eligibilityOf, eligibilityResource, failuresOf } from './eligibility'
import { publicCycleResource } from './cycleSerializers'
import { awardReject, conditionsRejection } from './awardRejection'
import { announceConditionsRejected } from './awardNotices'

const ADVANCED_PREFIX = 'ADV'
const ACTIVE_ENTITY = 'active'

function entityRecords(entityId) {
  return allAssessments()
    .filter(
      (record) => record.path === ASSESSMENT_PATHS.ADVANCED && record.entityId === Number(entityId),
    )
    .sort((a, b) => b.id - a.id)
}

function present(record) {
  return record
    ? { ...assessmentResource(record), conditionFailure: record.conditionFailure }
    : null
}

function inCycle(records, cycle) {
  return records.filter((record) => record.awardCycleId === cycle.id)
}

export function advancedCurrentResource(user) {
  const entity = findEntity(user.entityId)
  if (!entity) throw assessmentReject.entityRequired()
  const cycle = runningCycle()
  const eligibility = eligibilityOf(entity, cycle)
  const records = entityRecords(entity.id)
  const current = cycle ? inCycle(records, cycle) : []
  const request =
    current.find((record) => record.status !== ADVANCED_STATES.REJECTED_CONDITIONS) ??
    current[0] ??
    null
  const previous = records.find((record) => record.awardCycleId !== cycle?.id) ?? null
  return {
    cycle: cycle ? publicCycleResource(cycle) : null,
    eligibility: eligibilityResource(eligibility),
    request: present(request),
    previous: present(previous),
  }
}

function newRequest(user, entity, cycle, status, conditionFailure) {
  const at = nowIso()
  const record = insertAssessment({
    number: nextRequestNumber(new Date(at), ADVANCED_PREFIX),
    entityId: entity.id,
    path: ASSESSMENT_PATHS.ADVANCED,
    status,
    awardCycleId: cycle.id,
    regionId: entity.regionId,
    conditionFailure,
    criteriaVersionId: cycle.criteriaVersionId,
    scaleKind: cycle.scaleKind,
    scores: {},
    result: null,
    cv: {},
    evidence: [],
    feedback: {},
    assignments: [],
    completionRequests: [],
    filteringDecisions: [],
    bundles: [],
    createdAt: at,
    updatedAt: at,
    submittedAt: null,
    completedAt: null,
    initialAssessedAt: null,
    withdrawnAt: null,
    withdrawalReason: null,
    dueAt: null,
    reviewSlaDays: null,
    overdueNotifiedAt: null,
    criteriaUpdatePendingSince: null,
    criteriaMoves: [],
  })
  const verb = status === ADVANCED_STATES.DRAFT ? 'start' : 'reject_conditions'
  record.transitions = [
    transition(null, status, verb, at, actorOf(user, record), visibleActorId(user)),
  ]
  return record
}

function recordRejection(user, entity, cycle, failures, existing) {
  if (existing.some((record) => record.status === ADVANCED_STATES.REJECTED_CONDITIONS)) return
  const record = newRequest(user, entity, cycle, ADVANCED_STATES.REJECTED_CONDITIONS, { failures })
  announceConditionsRejected(record)
}

export function applyForAdvanced(user, body) {
  if (!isPositiveInteger(body.awardCycleId))
    throwIfErrors({ awardCycleId: [fieldMessage('validation.required', {}, 'awardCycleId')] })
  const entity = findEntity(user.entityId)
  if (!entity) throw assessmentReject.entityRequired()
  if (entity.status !== ACTIVE_ENTITY) throw assessmentReject.entityNotActive()
  const cycle = findCycle(body.awardCycleId)
  if (!cycle || !acceptsApplications(cycle))
    throw assessmentReject.coded(409, 'cycle.submission_closed')
  const existing = inCycle(entityRecords(entity.id), cycle)
  const request = existing.find((record) => record.status !== ADVANCED_STATES.REJECTED_CONDITIONS)
  if (request?.status === ADVANCED_STATES.DRAFT) return { record: request, created: false }
  if (request) throw awardReject.requestExists(request.id)
  const failures = failuresOf(eligibilityOf(entity, cycle))
  if (failures.length) {
    recordRejection(user, entity, cycle, failures, existing)
    throw conditionsRejection(failures)
  }
  return { record: newRequest(user, entity, cycle, ADVANCED_STATES.DRAFT, null), created: true }
}

export function deleteAdvancedDraft(user, record, body) {
  const reason = optionalReason(body)
  if (user.superAdmin && !reason) throw assessmentReject.reasonRequired()
  if (record.status !== ADVANCED_STATES.DRAFT)
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), 'delete_draft')
  removeAssessment(record.id)
}
