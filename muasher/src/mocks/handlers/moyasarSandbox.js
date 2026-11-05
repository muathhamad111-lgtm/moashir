import { HttpResponse, http } from 'msw'
import { MOYASAR_FORM_ASSETS } from '@/constants/payments'
import { SANDBOX_OUTCOME_ORDER, moyasarSandboxTexts } from '../fixtures/moyasarSandbox'
import { fakeMoyasarScriptSource } from '../moyasar/fakeMoyasarForm'
import { paymentState } from '../stores/integrations/integrationsState'
import { financeState } from '../stores/finance/financeState'
import { clientSecretOf } from '../stores/finance/checkoutObject'
import { toMinor } from '../stores/finance/financeMoney'
import { SANDBOX_OUTCOMES, fulfilIntent } from '../stores/finance/providerLedger'

const FULFIL_PATH = 'https://api.moyasar.com/v1/payment_intents/:intent/fulfill'

function providerError(status, type, message) {
  return HttpResponse.json({ type, message, errors: null }, { status })
}

function intentPayment(intentId) {
  return financeState().payments.find((payment) => payment.providerIntentId === intentId) ?? null
}

function intentConsumed(intentId) {
  return Object.values(financeState().providerPayments).some(
    (record) => record.intentId === intentId,
  )
}

function fulfilmentError(payment, body) {
  if (!payment || body.client_secret !== clientSecretOf(payment.providerIntentId))
    return providerError(401, 'authentication_error', 'Invalid client secret')
  if (body.publishable_api_key !== paymentState().rows[payment.mode]?.publishableKey)
    return providerError(401, 'authentication_error', 'Invalid key')
  if (payment.status !== 'created' || new Date(payment.expiresAt).getTime() <= Date.now())
    return providerError(400, 'invalid_request_error', 'Intent is not payable')
  if (intentConsumed(payment.providerIntentId))
    return providerError(400, 'invalid_request_error', 'Intent already fulfilled')
  return null
}

function providerPaymentResource(record, payment) {
  return {
    id: record.id,
    status: record.status,
    amount: record.amount,
    currency: record.currency,
    description: `Moashir ${payment.reference}`,
    metadata: record.metadata,
    source: {
      ...record.source,
      message: record.status === 'failed' ? 'DECLINED' : 'APPROVED',
      transaction_url: null,
    },
  }
}

export const moyasarSandboxHandlers = [
  http.get(
    MOYASAR_FORM_ASSETS.scriptUrl,
    () =>
      new HttpResponse(fakeMoyasarScriptSource(moyasarSandboxTexts, SANDBOX_OUTCOME_ORDER), {
        headers: { 'Content-Type': 'application/javascript; charset=utf-8' },
      }),
  ),

  http.get(
    MOYASAR_FORM_ASSETS.stylesheetUrl,
    () => new HttpResponse('', { headers: { 'Content-Type': 'text/css' } }),
  ),

  http.post(FULFIL_PATH, async ({ params, request }) => {
    const body = await request.json()
    const payment = intentPayment(params.intent)
    const error = fulfilmentError(payment, body)
    if (error) return error
    const outcome = SANDBOX_OUTCOMES[body.source?.sandbox_outcome]
      ? body.source.sandbox_outcome
      : 'paid'
    const record = fulfilIntent({
      intentId: params.intent,
      amount: toMinor(payment.total),
      currency: payment.currency,
      metadata: body.metadata ?? {},
      outcome,
      sourceType: body.source?.type,
    })
    return HttpResponse.json(providerPaymentResource(record, payment), { status: 201 })
  }),
]
