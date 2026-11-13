import { FINANCE_YEAR } from '../../fixtures/finance'
import { findEntity } from '../entitiesStore'
import { authorizePurpose } from './financeAccess'
import { financeState, nextSequence, persistFinanceState } from './financeState'
import { fieldMessage, financeReject } from './financeRejection'
import { gatewayRow, linkedPaths } from './checkoutObject'
import { OPEN_STATUSES, nowIso, recordEvent } from './paymentLifecycle'
import { pathPricingOf } from './priceVersions'
import { assertAvailable, pathReject } from './pathRejection'
import { cycleFeePricing } from '../award/awardPricing'
import { findCycle } from '../award/cycleStore'

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
const PURPOSES = ['self', 'accredited', 'award', 'bag']
const RETIRED_PURPOSE = 'leadership'
const AWARD = 'award'

export function idempotencyKeyOf(request) {
  const key = request.headers.get('idempotency-key')
  if (!key || !UUID_PATTERN.test(key)) throw financeReject.idempotencyKeyMissing()
  return key.toLowerCase()
}

export function validatedPurpose(body) {
  if (typeof body.purpose !== 'string' || ![...PURPOSES, RETIRED_PURPOSE].includes(body.purpose)) {
    throw financeReject.validation({ purpose: [fieldMessage('choice')] })
  }
  if (body.purpose === RETIRED_PURPOSE) throw financeReject.purposeNotAvailable()
  return body.purpose
}

export function authorizeKnownPurpose(user, body) {
  if (PURPOSES.includes(body.purpose)) authorizePurpose(user, body.purpose)
}

export function replayOf(user, purpose, key) {
  const payment = financeState().payments.find(
    (item) => item.initiatedByUserId === user.id && item.idempotencyKey === key,
  )
  if (payment && payment.purpose !== purpose) throw financeReject.idempotencyKeyReused()
  return payment ?? null
}

export function payingEntity(user) {
  const entity = user.entityId === null ? null : findEntity(user.entityId)
  if (!entity) throw financeReject.entityRequired()
  if (entity.deletedAt) throw financeReject.entityDeleted()
  if (entity.status !== 'active') throw financeReject.entityNotActive()
  return entity
}

export function gatewayMode() {
  const configured = gatewayRow()
  if (!configured) throw financeReject.notConfigured()
  return configured.mode
}

export function assertLinkedPath(purpose) {
  if (!linkedPaths().includes(purpose)) throw financeReject.purposeNotAvailable()
}

function hasValue(value) {
  return value !== undefined && value !== null && value !== ''
}

export function validatedCycle(purpose, body) {
  const given = hasValue(body.awardCycleId)
  if (purpose === AWARD && !given)
    throw financeReject.validation({ awardCycleId: [fieldMessage('award_cycle_required')] })
  if (purpose !== AWARD && given)
    throw financeReject.validation({ awardCycleId: [fieldMessage('award_cycle_prohibited')] })
  if (!given) return null
  const cycle = findCycle(Number(body.awardCycleId))
  if (!cycle) throw pathReject.cycleNotFound()
  return cycle
}

export function orderPricingOf(purpose, cycle) {
  return purpose === AWARD ? cycleFeePricing(cycle) : pathPricingOf(purpose)
}

export function assertOrderable(purpose, cycle) {
  const pricing = orderPricingOf(purpose, cycle)
  assertAvailable(purpose, pricing)
  return pricing
}

export function quoteFor(purpose, pricing) {
  if (pricing.isFree) throw pathReject.notRequired(purpose)
  return { ...pricing.price }
}

export function openOrderOf(entityId, purpose, cycle = null) {
  return (
    financeState().payments.find(
      (payment) =>
        payment.entityId === entityId &&
        payment.purpose === purpose &&
        (cycle === null || payment.awardCycleId === cycle.id) &&
        OPEN_STATUSES.includes(payment.status),
    ) ?? null
  )
}

function referenceOf(sequence) {
  return `PAY-${FINANCE_YEAR}-${String(sequence).padStart(6, '0')}`
}

export function openOrder({ user, entity, purpose, quote, mode, key, cycle = null }) {
  const createdAt = nowIso()
  const validity = financeState().settings.orderValidityMinutes
  const payment = {
    id: crypto.randomUUID(),
    reference: referenceOf(nextSequence('payment')),
    entityId: entity.id,
    entityName: { ar: entity.name.ar, en: entity.name.en ?? null },
    initiatedByUserId: user.id,
    purpose,
    awardCycleId: cycle?.id ?? null,
    channel: 'electronic',
    mode,
    idempotencyKey: key,
    providerIntentId: `mock_pi_${crypto.randomUUID().replaceAll('-', '').slice(0, 20)}`,
    providerPaymentId: null,
    method: null,
    cardLast4: null,
    ...quote,
    status: 'created',
    expiresAt: new Date(Date.now() + validity * 60000).toISOString(),
    createdAt,
    updatedAt: createdAt,
    boundAt: null,
    verifiedAt: null,
    paidAt: null,
    failedAt: null,
    cancelledAt: null,
    rejectedAt: null,
    refundedAt: null,
    latePaidAt: null,
    manuallySettledAt: null,
    failureReason: null,
    failureCode: null,
    failureMessage: null,
  }
  financeState().payments.push(payment)
  recordEvent(payment, { source: 'checkout', eventType: 'intent_created', result: 'applied' })
  persistFinanceState()
  return payment
}
