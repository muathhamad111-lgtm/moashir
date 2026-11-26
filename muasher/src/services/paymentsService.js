import { apiClient } from '@/api/client'
import { IDEMPOTENCY_HEADER, PAYMENT_CHANNELS } from '@/constants/payments'
import { domainRequests } from './domainRequest'
import { toCheckoutOptions, toOwnTransfer } from './mappers/bankTransfers'

const NOT_FOUND = 404
const UNAVAILABLE = 503

const { read, write } = domainRequests('payments', 'payment')

function withTransfer(envelope) {
  if (!envelope?.data || Array.isArray(envelope.data)) return envelope
  return {
    ...envelope,
    data: { ...envelope.data, bankTransfer: toOwnTransfer(envelope.data.bankTransfer) },
  }
}

function orderPayload({ purpose, awardCycleId = null }) {
  return awardCycleId ? { purpose, awardCycleId } : { purpose }
}

function transferForm({ purpose, awardCycleId = null, bankAccountId, proof }) {
  const form = new FormData()
  form.append('purpose', purpose)
  if (awardCycleId) form.append('awardCycleId', String(awardCycleId))
  form.append('channel', PAYMENT_CHANNELS.BANK_TRANSFER)
  form.append('bankAccountId', bankAccountId)
  form.append('proof', proof)
  return form
}

export const paymentsService = {
  listMine(params = {}) {
    return read('listMine', () => apiClient.get('/payments/mine', { params }), params)
  },

  create(order, idempotencyKey) {
    const payload = orderPayload(order)
    const headers = { [IDEMPOTENCY_HEADER]: idempotencyKey }
    return write('create', () => apiClient.post('/payments', payload, { headers }), payload, [
      NOT_FOUND,
      UNAVAILABLE,
    ])
  },

  submitTransfer(order, idempotencyKey, { onUploadProgress } = {}) {
    const headers = { [IDEMPOTENCY_HEADER]: idempotencyKey }
    const shape = {
      purpose: null,
      awardCycleId: null,
      channel: null,
      bankAccountId: null,
      proof: null,
    }
    return write(
      'submitTransfer',
      () => apiClient.post('/payments', transferForm(order), { headers, onUploadProgress }),
      shape,
    ).then(withTransfer)
  },

  checkoutOptions(order = null) {
    const params = order ? orderPayload(order) : undefined
    return read(
      'checkoutOptions',
      () => apiClient.get('/payments/checkout-options', { params }),
      params,
      [NOT_FOUND],
    ).then((envelope) => ({ ...envelope, data: toCheckoutOptions(envelope.data) }))
  },

  cancel(paymentId) {
    return write('cancel', () => apiClient.post(`/payments/${paymentId}/cancel`), {}, [
      NOT_FOUND,
      UNAVAILABLE,
    ])
  },

  get(paymentId) {
    return read('get', () => apiClient.get(`/payments/${paymentId}`), null, [NOT_FOUND]).then(
      withTransfer,
    )
  },

  requestTransferProofDownload(paymentId) {
    return write(
      'requestTransferProofDownload',
      () => apiClient.post(`/payments/${paymentId}/bank-transfer/proof/download`),
      {},
      [NOT_FOUND],
    )
  },

  verify(paymentId, providerPaymentId) {
    const payload = { providerPaymentId }
    return write(
      'verify',
      () => apiClient.post(`/payments/${paymentId}/verify`, payload),
      payload,
      [NOT_FOUND, UNAVAILABLE],
    )
  },

  getReceipt(paymentId) {
    return read('getReceipt', () => apiClient.get(`/payments/${paymentId}/receipt`), null, [
      NOT_FOUND,
    ])
  },

  requestReceiptDownload(paymentId) {
    return write(
      'requestReceiptDownload',
      () => apiClient.post(`/payments/${paymentId}/receipt/download`),
      {},
      [UNAVAILABLE],
    )
  },
}
