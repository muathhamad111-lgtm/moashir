import { delay } from 'msw'
import { MockRejection, rejectionResponse } from '../../contract/rejection'
import { interpolate } from '../../contract/locale'
import { financeFieldMessages, financeMessages } from '../../fixtures/financeMessages'

export const FINANCE_LATENCY_MS = 140

function bilingual(entry, params = {}) {
  return { ar: interpolate(entry.ar, params), en: interpolate(entry.en, params) }
}

export function fieldMessage(key, params = {}) {
  return bilingual(financeFieldMessages[key], params)
}

export function codeMessage(code, params = {}) {
  return bilingual(financeMessages[code], params)
}

function codeField(status, code, field, params = {}) {
  return new MockRejection(status, code, {
    fields: { [field]: [codeMessage(code, params)] },
    params,
  })
}

export const financeReject = {
  unauthenticated: () => new MockRejection(401, 'unauthenticated'),
  forbidden: () => new MockRejection(403, 'forbidden'),
  notFound: () => new MockRejection(404, 'not_found'),
  validation: (fields) => new MockRejection(422, 'validation_failed', { fields }),
  reasonRequired: () => codeField(422, 'reason_required', 'reason'),
  illegalTransition: (state, attempted) =>
    new MockRejection(409, 'state.transition_illegal', {
      fields: { state: [state], attempted: [attempted] },
    }),
  entityDeleted: () => new MockRejection(409, 'entity.deleted'),
  notConfigured: () =>
    new MockRejection(503, 'integration.not_configured', { meta: { kind: 'payment' } }),
  providerUnavailable: () => new MockRejection(503, 'provider_unavailable'),
  priceNotConfigured: () => new MockRejection(409, 'payment.price_not_configured'),
  entityRequired: () => new MockRejection(403, 'payment.entity_required'),
  entityNotActive: () => new MockRejection(409, 'payment.entity_not_active'),
  checkoutInProgress: () => new MockRejection(409, 'payment.checkout_in_progress'),
  bindingMismatch: () => codeField(409, 'payment.binding_mismatch', 'providerPaymentId'),
  providerPaymentNotFound: () =>
    codeField(422, 'payment.provider_payment_not_found', 'providerPaymentId'),
  verificationRejected: () => codeField(409, 'payment.verification_rejected', 'providerPaymentId'),
  providerIdRequired: () =>
    codeField(422, 'payment.provider_payment_id_required', 'providerPaymentId'),
  providerIdInUse: () => codeField(422, 'payment.provider_payment_id_in_use', 'providerPaymentId'),
  idempotencyKeyMissing: () => codeField(422, 'idempotency_key.missing', 'idempotencyKey'),
  idempotencyKeyReused: () => codeField(422, 'idempotency_key.reused', 'idempotencyKey'),
  purposeNotAvailable: () => codeField(422, 'purpose.not_available', 'purpose'),
  refundAmount: (total) =>
    new MockRejection(422, 'refund.amount_must_equal_total', {
      fields: { amount: [codeMessage('refund.amount_must_equal_total', { total })] },
      meta: { paymentTotal: total },
      params: { total },
    }),
  refundDate: () => codeField(422, 'refund.date_before_payment', 'refundedOn'),
  refundRecorded: () => new MockRejection(409, 'refund.already_recorded'),
  receiptVoided: () => new MockRejection(409, 'receipt.voided'),
  receiptNotIssued: () => new MockRejection(404, 'receipt.not_issued'),
  exportNotReady: () => new MockRejection(409, 'finance_export.not_ready'),
  fileRefused: (code, field) => codeField(422, code, field),
  fileNotClean: () => new MockRejection(409, 'file.not_clean'),
  signatureInvalid: () => new MockRejection(403, 'signature.invalid'),
  signatureExpired: () => new MockRejection(410, 'signature.expired'),
  rendererUnavailable: () => new MockRejection(503, 'document.renderer_unavailable'),
}

export function financeRoute(resolve) {
  return async (info) => {
    await delay(FINANCE_LATENCY_MS)
    try {
      return await resolve(info)
    } catch (error) {
      if (!(error instanceof MockRejection)) throw error
      error.fields = Object.fromEntries(
        Object.entries(error.fields).map(([field, entries]) => [
          field,
          entries.map((entry) => (typeof entry === 'string' ? { ar: entry, en: entry } : entry)),
        ]),
      )
      return rejectionResponse(error, info.request, financeMessages)
    }
  }
}

export async function readJson(request) {
  const text = await request.text()
  return text ? JSON.parse(text) : {}
}
