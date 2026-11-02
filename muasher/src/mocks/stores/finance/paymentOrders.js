import { FINANCE_YEAR } from '../../fixtures/finance'
import { findEntity } from '../entitiesStore'
import { authorizePurpose } from './financeAccess'
import { financeState, nextSequence, persistFinanceState } from './financeState'
import { fieldMessage, financeReject } from './financeRejection'
import { quoteOf } from './financeMoney'
import { gatewayRow, linkedPaths } from './checkoutObject'
import { OPEN_STATUSES, nowIso, recordEvent } from './paymentLifecycle'

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
const PURPOSES = ['accredited', 'bag']
const RETIRED_PURPOSE = 'leadership'
const PRICE_KEYS = { accredited: 'accreditedPrice', bag: 'bagPrice' }

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
  const payment = financeState().payments.find((item) => item.initiatedByUserId === user.id && item.idempotencyKey === key)
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

export function quoteFor(purpose) {
  const settings = financeState().settings
  const price = settings[PRICE_KEYS[purpose]]
  if (!price || Number(price) <= 0) throw financeReject.priceNotConfigured()
  return quoteOf(price, settings.vatRate)
}

export function openOrderOf(entityId, purpose) {
  return (
    financeState().payments.find(
      (payment) => payment.entityId === entityId && payment.purpose === purpose && OPEN_STATUSES.includes(payment.status),
    ) ?? null
  )
}

function referenceOf(sequence) {
  return `PAY-${FINANCE_YEAR}-${String(sequence).padStart(6, '0')}`
}

export function openOrder({ user, entity, purpose, quote, mode, key }) {
  const createdAt = nowIso()
  const validity = financeState().settings.orderValidityMinutes
  const payment = {
    id: crypto.randomUUID(),
    reference: referenceOf(nextSequence('payment')),
    entityId: entity.id,
    entityName: { ar: entity.name.ar, en: entity.name.en ?? null },
    initiatedByUserId: user.id,
    purpose,
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
