import { CRITERIA_VERSION_STATUSES } from '@/constants/criteria'
import { SELF_STATUSES } from '@/constants/assessment'
import { versionWithStatus } from '../criteriaStore'
import { findEntity } from '../entitiesStore'
import { dispatchQueuedJob } from '../mockQueue'
import { isOpen } from '../assessments/assessmentsStore'
import { ACTORS, transition } from '../assessments/statusHistory'
import { insertSelfDraft } from '../assessments/selfWorkflow'
import { assertSelfPurchasable } from '../assessments/selfStartGate'
import {
  registerPaymentPaidListener,
  registerPaymentRefundedListener,
  registerPurchaseEligibilityCheck,
} from '../finance/paymentHooks'
import { currentSessionUser } from '../sessionStore'
import { financeState } from '../finance/financeState'
import { ADVANCED_STATES, PRECEDES_SECRETARY } from '../award/advancedStates'
import { findCycle } from '../award/cycleStore'
import { assertAwardPurchasable, openPaidAwardDraft } from '../award/advancedEntity'
import { requestOfPayment } from './paidRequests'

const AWAITING_PAYMENT = 'awaiting-payment'
const REFUNDED = 'refunded'
const AWARD_REFUNDABLE = [
  ADVANCED_STATES.DRAFT,
  ...PRECEDES_SECRETARY,
  ADVANCED_STATES.COMMITTEE_DISCUSSION,
  ADVANCED_STATES.AWAITING_SECRETARY,
]

function openPaidSelf(payment, at) {
  const version = versionWithStatus(CRITERIA_VERSION_STATUSES.PUBLISHED)
  if (!version) return null
  const record = insertSelfDraft(payment.entityId, version, at, { paymentId: payment.id })
  record.transitions = [
    transition(null, AWAITING_PAYMENT, 'start', at, ACTORS.SYSTEM),
    transition(AWAITING_PAYMENT, SELF_STATUSES.DRAFT, 'open_draft', at, ACTORS.SYSTEM),
  ]
  return record
}

function openPaidAward(payment) {
  const entity = findEntity(payment.entityId)
  const cycle = findCycle(payment.awardCycleId)
  return entity && cycle ? openPaidAwardDraft(entity, cycle, payment) : null
}

const OPENERS = {
  self: (payment) => openPaidSelf(payment, new Date().toISOString()),
  award: openPaidAward,
}

export function openPaidRequest(payment) {
  const opener = OPENERS[payment.purpose]
  if (!opener || payment.status !== 'paid') return null
  return requestOfPayment(payment.id) ?? opener(payment)
}

function refundable(record) {
  if (record.path === 'self') return record.status === SELF_STATUSES.DRAFT
  return record.path === 'advanced' && AWARD_REFUNDABLE.includes(record.status)
}

function closeOnRefund(payment) {
  if (!OPENERS[payment.purpose]) return
  const record = requestOfPayment(payment.id)
  if (!record || !isOpen(record) || !refundable(record)) return
  const at = new Date().toISOString()
  record.transitions = [
    ...(record.transitions ?? []),
    transition(record.status, REFUNDED, 'refund', at, ACTORS.STAFF),
  ]
  Object.assign(record, { status: REFUNDED, refundedAt: at, updatedAt: at })
}

function assertMayBuyPath(entity, purpose, cycle) {
  if (purpose === 'self') assertSelfPurchasable(entity)
  if (purpose === 'award') assertAwardPurchasable(currentSessionUser(), entity, cycle)
}

registerPaymentPaidListener((payment) => dispatchQueuedJob(() => openPaidRequest(payment)))
registerPaymentRefundedListener(closeOnRefund)
registerPurchaseEligibilityCheck(assertMayBuyPath)

export function sweepPaidPathPayments() {
  financeState()
    .payments.filter((payment) => OPENERS[payment.purpose] && payment.status === 'paid')
    .forEach(openPaidRequest)
}
