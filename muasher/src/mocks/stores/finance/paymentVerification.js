import { persistFinanceState } from './financeState'
import { fieldMessage, financeReject } from './financeRejection'
import { toMinor } from './financeMoney'
import { failureReasonOf, fetchProviderPayment, last4Of, methodOf } from './providerLedger'
import { CONCLUDED_STATUSES, OPEN_STATUSES, bindProvider, issueReceipt, nowIso, recordEvent, transition } from './paymentLifecycle'
import { announcePaymentPaid } from './paymentHooks'

const PROVIDER_ID_PATTERN = /^[A-Za-z0-9_-]{1,80}$/

export const VERIFICATION = {
  APPLIED: 'applied',
  PENDING: 'pending',
  IGNORED: 'ignored',
  LATE_PAYMENT: 'late_payment',
}

export function validatedProviderId(body) {
  const value = body.providerPaymentId
  if (typeof value !== 'string' || !PROVIDER_ID_PATTERN.test(value)) {
    throw financeReject.validation({ providerPaymentId: [fieldMessage('invalid')] })
  }
  return value
}

function belongsTo(payment, provider) {
  return provider.metadata?.moashir_payment_id === payment.id
}

function amountMatches(payment, provider) {
  return provider.amount === toMinor(payment.total) && provider.currency === payment.currency
}

function describeCard(payment, provider) {
  payment.method = methodOf(provider.source)
  payment.cardLast4 = last4Of(provider.source)
}

function settle(payment, provider) {
  bindProvider(payment, provider.id)
  describeCard(payment, provider)
  payment.verifiedAt = nowIso()
  if (!amountMatches(payment, provider)) {
    transition(payment, 'failed', 'fail')
    payment.failureReason = 'amount_mismatch'
    return VERIFICATION.APPLIED
  }
  transition(payment, 'paid', 'settle')
  issueReceipt(payment)
  announcePaymentPaid(payment)
  return VERIFICATION.APPLIED
}

function fail(payment, provider) {
  bindProvider(payment, provider.id)
  describeCard(payment, provider)
  payment.verifiedAt = nowIso()
  transition(payment, 'failed', 'fail')
  payment.failureReason = failureReasonOf(provider.responseCode)
  payment.failureCode = provider.responseCode
  payment.failureMessage = provider.responseCode === '51' ? 'Insufficient funds' : 'Declined'
  return VERIFICATION.APPLIED
}

function whileOpen(payment, provider) {
  if (provider.status === 'initiated') {
    bindProvider(payment, provider.id)
    return VERIFICATION.PENDING
  }
  if (provider.status === 'paid') return settle(payment, provider)
  if (provider.status === 'failed') return fail(payment, provider)
  return VERIFICATION.IGNORED
}

function afterCancellation(payment, provider) {
  if (provider.status !== 'paid') return VERIFICATION.IGNORED
  if (!payment.latePaidAt) {
    payment.providerPaymentId = payment.providerPaymentId ?? provider.id
    payment.latePaidAt = nowIso()
    payment.updatedAt = payment.latePaidAt
  }
  return VERIFICATION.LATE_PAYMENT
}

function applied(payment, provider) {
  if (payment.status === 'cancelled') return afterCancellation(payment, provider)
  if (OPEN_STATUSES.includes(payment.status)) return whileOpen(payment, provider)
  return VERIFICATION.IGNORED
}

export function verifyPayment(payment, providerPaymentId) {
  if (payment.providerPaymentId && payment.providerPaymentId !== providerPaymentId) throw financeReject.bindingMismatch()
  if (CONCLUDED_STATUSES.includes(payment.status)) return VERIFICATION.IGNORED
  const provider = fetchProviderPayment(providerPaymentId)
  if (!provider) throw financeReject.providerPaymentNotFound()
  if (!belongsTo(payment, provider)) {
    recordEvent(payment, { source: 'verification', eventType: 'payment_fetched', result: 'rejected', errorCode: 'payment.verification_rejected', providerPaymentId })
    persistFinanceState()
    throw financeReject.verificationRejected()
  }
  const result = applied(payment, provider)
  recordEvent(payment, { source: 'verification', eventType: 'payment_fetched', result, providerPaymentId })
  persistFinanceState()
  return result
}
