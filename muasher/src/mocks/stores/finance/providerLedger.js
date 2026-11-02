import { financeState, persistFinanceState } from './financeState'

export const SANDBOX_OUTCOMES = {
  paid: { status: 'paid', responseCode: '00', checksUntilSettled: null, settlesTo: null },
  declined: { status: 'failed', responseCode: '05', checksUntilSettled: null, settlesTo: null },
  insufficient_funds: { status: 'failed', responseCode: '51', checksUntilSettled: null, settlesTo: null },
  three_ds_completes: { status: 'initiated', responseCode: null, checksUntilSettled: 2, settlesTo: 'paid' },
  three_ds_pending: { status: 'initiated', responseCode: null, checksUntilSettled: null, settlesTo: null },
}

const SANDBOX_SOURCES = {
  creditcard: { type: 'creditcard', company: 'mada', number: '4201-32XX-XXXX-7412' },
  applepay: { type: 'applepay', company: 'visa', number: '4012-88XX-XXXX-2209' },
  stcpay: { type: 'stcpay', company: null, number: null },
}

const FAILURE_REASONS = {
  51: 'insufficient_funds',
  '05': 'declined',
  54: 'expired_card',
  41: 'lost_or_stolen',
  43: 'lost_or_stolen',
  61: 'limit_exceeded',
}

export function failureReasonOf(responseCode) {
  return FAILURE_REASONS[responseCode] ?? 'other'
}

export function methodOf(source) {
  if (source.type === 'creditcard') return source.company === 'mada' ? 'mada' : 'creditcard'
  return source.type
}

export function last4Of(source) {
  return source.number ? source.number.slice(-4) : null
}

export function fulfilIntent({ intentId, amount, currency, metadata, outcome, sourceType }) {
  const behaviour = SANDBOX_OUTCOMES[outcome] ?? SANDBOX_OUTCOMES.paid
  const id = `mock_pay_${crypto.randomUUID().replaceAll('-', '')}`
  const record = {
    id,
    intentId,
    amount,
    currency,
    metadata: { ...metadata },
    source: { ...(SANDBOX_SOURCES[sourceType] ?? SANDBOX_SOURCES.creditcard) },
    ...behaviour,
  }
  financeState().providerPayments[id] = record
  persistFinanceState()
  return record
}

function settleWhenDue(record) {
  if (record.status !== 'initiated' || record.checksUntilSettled === null) return
  record.checksUntilSettled -= 1
  if (record.checksUntilSettled > 0) return
  record.status = record.settlesTo
  record.responseCode = record.settlesTo === 'paid' ? '00' : record.responseCode
  record.checksUntilSettled = null
}

export function fetchProviderPayment(id) {
  const record = financeState().providerPayments[id]
  if (!record) return null
  settleWhenDue(record)
  persistFinanceState()
  return { ...record, metadata: { ...record.metadata }, source: { ...record.source } }
}

export function providerPaymentExists(id) {
  return Boolean(financeState().providerPayments[id])
}
