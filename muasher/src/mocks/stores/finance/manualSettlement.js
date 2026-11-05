import { persistFinanceState } from './financeState'
import { fieldMessage, financeReject } from './financeRejection'
import {
  OPEN_CARD_STATUSES,
  bindProvider,
  issueReceipt,
  nowIso,
  recordEvent,
  transition,
} from './paymentLifecycle'
import { announcePaymentPaid } from './paymentHooks'

const STATUSES = ['paid', 'failed']
const PROVIDER_ID_PATTERN = /^[A-Za-z0-9_-]+$/

function reasonError(reason) {
  if (!reason) return fieldMessage('required')
  if (reason.length < 20) return fieldMessage('min', { min: 20 })
  if (reason.length > 1000) return fieldMessage('max', { max: 1000 })
  return null
}

function providerIdError(value) {
  if (value === null) return null
  return value.length <= 80 && PROVIDER_ID_PATTERN.test(value) ? null : fieldMessage('invalid')
}

export function validatedSettlement(body) {
  const settlement = {
    status: body.status,
    providerPaymentId:
      typeof body.providerPaymentId === 'string' && body.providerPaymentId !== ''
        ? body.providerPaymentId
        : null,
    reason: typeof body.reason === 'string' ? body.reason.trim() : '',
  }
  const errors = {
    status: STATUSES.includes(settlement.status) ? null : fieldMessage('choice'),
    providerPaymentId: providerIdError(settlement.providerPaymentId),
    reason: reasonError(settlement.reason),
  }
  const failed = Object.entries(errors).filter(([, message]) => message)
  if (failed.length)
    throw financeReject.validation(
      Object.fromEntries(failed.map(([field, message]) => [field, [message]])),
    )
  return settlement
}

export function settleManually(payment, settlement) {
  if (payment.channel === 'bank_transfer') throw financeReject.channelNotSupported()
  if (!OPEN_CARD_STATUSES.includes(payment.status))
    throw financeReject.illegalTransition(payment.status, 'manual_settle')
  const providerPaymentId = settlement.providerPaymentId ?? payment.providerPaymentId
  if (settlement.status === 'paid' && providerPaymentId === null)
    throw financeReject.providerIdRequired()
  if (settlement.status === 'failed' && providerPaymentId === null)
    throw financeReject.illegalTransition(payment.status, 'manual_settle')
  bindProvider(payment, providerPaymentId)
  payment.manuallySettledAt = nowIso()
  if (settlement.status === 'paid') {
    transition(payment, 'paid', 'manual_settle')
    issueReceipt(payment)
  } else {
    transition(payment, 'failed', 'manual_settle')
    payment.failureReason = 'settled_manually'
  }
  recordEvent(payment, { source: 'admin', eventType: 'manual_settle', result: 'applied' })
  persistFinanceState()
  if (payment.status === 'paid') announcePaymentPaid(payment)
  return payment
}
