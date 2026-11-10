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
import { ACTORS, actorOf, transition, visibleActorId } from '../assessments/statusHistory'
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
import { cycleFeePricing } from './awardPricing'
import { assertAvailable, pathReject } from '../finance/pathRejection'
import { openOrderFor, paidWithoutRequest } from '../periodic/paidRequests'

const ADVANCED_PREFIX = 'ADV'
const AWAITING_PAYMENT = 'awaiting-payment'
const ACTIVE_ENTITY = 'active'
const AWARD = 'award'

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

function insertAdvanced(entity, cycle, status, { conditionFailure = null, paymentId = null }) {
  const at = nowIso()
  return insertAssessment({
    number: nextRequestNumber(new Date(at), ADVANCED_PREFIX),
    entityId: entity.id,
    path: ASSESSMENT_PATHS.ADVANCED,
    status,
    awardCycleId: cycle.id,
    paymentId,
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
    committeeAverageId: null,
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
}

function newRequest(user, entity, cycle, status, conditionFailure) {
  const record = insertAdvanced(entity, cycle, status, { conditionFailure })
  const verb = status === ADVANCED_STATES.DRAFT ? 'start' : 'reject_conditions'
  record.transitions = [
    transition(null, status, verb, record.createdAt, actorOf(user, record), visibleActorId(user)),
  ]
  return record
}

export function openPaidAwardDraft(entity, cycle, payment) {
  const record = insertAdvanced(entity, cycle, ADVANCED_STATES.DRAFT, { paymentId: payment.id })
  record.transitions = [
    transition(null, AWAITING_PAYMENT, 'start', record.createdAt, ACTORS.SYSTEM),
    transition(
      AWAITING_PAYMENT,
      ADVANCED_STATES.DRAFT,
      'open_draft',
      record.createdAt,
      ACTORS.SYSTEM,
    ),
  ]
  return record
}

function recordRejection(user, entity, cycle, failures, existing) {
  if (existing.some((record) => record.status === ADVANCED_STATES.REJECTED_CONDITIONS)) return
  const record = newRequest(user, entity, cycle, ADVANCED_STATES.REJECTED_CONDITIONS, { failures })
  announceConditionsRejected(record)
}

function assertApplicable(entity, cycle) {
  if (!cycle || !acceptsApplications(cycle))
    throw assessmentReject.coded(409, 'cycle.submission_closed')
}

function assertConditions(user, entity, cycle, existing) {
  const failures = failuresOf(eligibilityOf(entity, cycle))
  if (!failures.length) return
  recordRejection(user, entity, cycle, failures, existing)
  throw conditionsRejection(failures)
}

function liveRequestOf(existing) {
  return existing.find((record) => record.status !== ADVANCED_STATES.REJECTED_CONDITIONS)
}

export function assertAwardPurchasable(user, entity, cycle) {
  assertApplicable(entity, cycle)
  const existing = inCycle(entityRecords(entity.id), cycle)
  const request = liveRequestOf(existing)
  if (request) throw awardReject.requestExists(request.id)
  assertConditions(user, entity, cycle, existing)
  if (paidWithoutRequest(entity.id, AWARD, cycle.id))
    throw pathReject.beingProcessed('advanced.payment_being_processed')
}

function assertFeePaid(entity, cycle) {
  const pricing = cycleFeePricing(cycle)
  if (pricing.isFree) return
  if (paidWithoutRequest(entity.id, AWARD, cycle.id))
    throw pathReject.beingProcessed('advanced.payment_being_processed')
  throw pathReject.paymentRequired({ path: AWARD, purpose: AWARD, awardCycleId: cycle.id, pricing })
}

export function applyForAdvanced(user, body) {
  if (!isPositiveInteger(body.awardCycleId))
    throwIfErrors({ awardCycleId: [fieldMessage('validation.required', {}, 'awardCycleId')] })
  const entity = findEntity(user.entityId)
  if (!entity) throw assessmentReject.entityRequired()
  if (entity.status !== ACTIVE_ENTITY) throw assessmentReject.entityNotActive()
  assertAvailable(AWARD, cycleFeePricing(runningCycle()))
  const cycle = findCycle(body.awardCycleId)
  assertApplicable(entity, cycle)
  const existing = inCycle(entityRecords(entity.id), cycle)
  const request = liveRequestOf(existing)
  if (request?.status === ADVANCED_STATES.DRAFT) return { record: request, created: false }
  if (request) throw awardReject.requestExists(request.id)
  const order = openOrderFor(entity.id, AWARD, cycle.id)
  if (order) throw pathReject.openOrderExists(order)
  assertConditions(user, entity, cycle, existing)
  assertFeePaid(entity, cycle)
  return { record: newRequest(user, entity, cycle, ADVANCED_STATES.DRAFT, null), created: true }
}

export function deleteAdvancedDraft(user, record, body) {
  const reason = optionalReason(body)
  if (user.superAdmin && !reason) throw assessmentReject.reasonRequired()
  if (record.status !== ADVANCED_STATES.DRAFT)
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), 'delete_draft')
  removeAssessment(record.id)
}
