import { CRITERIA_VERSION_STATUSES } from '@/constants/criteria'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { ACCREDITED_REQUEST_PREFIX } from '@/constants/accredited'
import { versionWithStatus } from '../criteriaStore'
import { insertAssessment, isOpen, nextRequestNumber } from '../assessments/assessmentsStore'
import { assessmentReject } from '../assessments/assessmentRejection'
import { ACTORS, transition } from '../assessments/statusHistory'
import { dispatchQueuedJob } from '../mockQueue'
import { financeState } from '../finance/financeState'
import { findEntity, isDeleted } from '../entitiesStore'
import { pathPricingOf } from '../finance/priceVersions'
import { assertAvailable, pathReject } from '../finance/pathRejection'
import { openOrderFor } from '../periodic/paidRequests'
import {
  registerPaymentPaidListener,
  registerPaymentRefundedListener,
  registerPurchaseEligibilityCheck,
} from '../finance/paymentHooks'
import { STATES, isRefundable } from './accreditedStates'
import {
  activeAssignment,
  nowIso,
  openAccreditedOf,
  openCompletionOf,
  recordOfPayment,
} from './accreditedRecords'

const ACCREDITED = 'accredited'
const duplicatePayments = new Set()

function paidAccreditedPayments(entityId = null) {
  return financeState().payments.filter(
    (payment) =>
      payment.purpose === ACCREDITED &&
      payment.status === 'paid' &&
      (entityId === null || payment.entityId === entityId),
  )
}

function insertAccreditedDraft(entityId, paymentId, version, at) {
  return insertAssessment({
    number: nextRequestNumber(new Date(at), ACCREDITED_REQUEST_PREFIX),
    entityId,
    path: ASSESSMENT_PATHS.ACCREDITED,
    status: STATES.DRAFT,
    paymentId,
    criteriaVersionId: version.id,
    scaleKind: version.scaleKind,
    scores: {},
    result: null,
    cv: {},
    evidence: [],
    feedback: {},
    assignments: [],
    completionRequests: [],
    bundles: [],
    createdAt: at,
    updatedAt: at,
    submittedAt: null,
    completedAt: null,
    refundedAt: null,
    dueAt: null,
    reviewSlaDays: null,
    overdueNotifiedAt: null,
    criteriaUpdatePendingSince: null,
    criteriaMoves: [],
  })
}

function openedRecord(payment, version, at) {
  const record = insertAccreditedDraft(payment.entityId, payment.id, version, at)
  record.transitions = [
    transition(null, STATES.AWAITING_PAYMENT, 'start', at, ACTORS.SYSTEM),
    transition(STATES.AWAITING_PAYMENT, STATES.DRAFT, 'open_draft', at, ACTORS.SYSTEM),
  ]
  return record
}

function openPaidAccreditedRequest(payment) {
  if (payment.purpose !== ACCREDITED || payment.status !== 'paid') return null
  const existing = recordOfPayment(payment.id)
  if (existing) return existing
  if (duplicatePayments.has(payment.id)) return null
  if (openAccreditedOf(payment.entityId)) {
    duplicatePayments.add(payment.id)
    return null
  }
  const version = versionWithStatus(CRITERIA_VERSION_STATUSES.PUBLISHED)
  return version ? openedRecord(payment, version, nowIso()) : null
}

export function sweepPaidAccreditedPayments(entityId = null) {
  paidAccreditedPayments(entityId).forEach(openPaidAccreditedRequest)
}

export function resetAccreditedOpening() {
  duplicatePayments.clear()
}

function assertNoOpenRequest(entity) {
  const open = openAccreditedOf(entity.id)
  if (open) throw assessmentReject.requestOpen(open.id)
}

function assertNoPaidPending(entity) {
  if (paidAccreditedPayments(entity.id).some((payment) => !recordOfPayment(payment.id)))
    throw assessmentReject.paymentBeingProcessed()
}

function assertMayBuyAccredited(entity, purpose) {
  if (purpose !== ACCREDITED) return
  assertNoOpenRequest(entity)
  assertNoPaidPending(entity)
}

function startableEntity(user) {
  const entity = user.entityId === null ? null : findEntity(user.entityId)
  if (!entity) throw assessmentReject.entityRequired()
  if (isDeleted(entity)) throw assessmentReject.entityDeleted()
  if (entity.status !== 'active') throw assessmentReject.entityNotActive()
  return entity
}

function assertFreeAndAvailable() {
  const pricing = pathPricingOf(ACCREDITED)
  assertAvailable(ACCREDITED, pricing)
  if (!pricing.isFree)
    throw pathReject.paymentRequired({ path: ACCREDITED, purpose: ACCREDITED, pricing })
}

export function startFreeAccredited(user) {
  const entity = startableEntity(user)
  assertFreeAndAvailable()
  assertNoOpenRequest(entity)
  const order = openOrderFor(entity.id, ACCREDITED)
  if (order) throw pathReject.openOrderExists(order)
  assertNoPaidPending(entity)
  const version = versionWithStatus(CRITERIA_VERSION_STATUSES.PUBLISHED)
  if (!version) throw assessmentReject.noPublishedVersion()
  const at = nowIso()
  const record = insertAccreditedDraft(entity.id, null, version, at)
  record.transitions = [
    transition(null, STATES.DRAFT, 'start', at, ACTORS.ENTITY, user.superAdmin ? null : user.id),
  ]
  return record
}

function closeOnRefund(payment, refund) {
  const record = recordOfPayment(payment.id)
  if (!record || !isOpen(record) || !isRefundable(record.status)) return
  const at = nowIso()
  const active = activeAssignment(record)
  if (active) Object.assign(active, { status: 'ended', endedAt: at, endReason: 'refunded' })
  const completion = openCompletionOf(record)
  if (completion) Object.assign(completion, { status: 'cancelled', resolvedAt: at })
  record.transitions.push(
    transition(
      record.status,
      STATES.REFUNDED,
      'refund',
      at,
      ACTORS.STAFF,
      refund.recordedBy?.id ?? null,
    ),
  )
  Object.assign(record, { status: STATES.REFUNDED, refundedAt: at, updatedAt: at })
}

registerPaymentPaidListener((payment) =>
  dispatchQueuedJob(() => openPaidAccreditedRequest(payment)),
)
registerPaymentRefundedListener(closeOnRefund)
registerPurchaseEligibilityCheck(assertMayBuyAccredited)
