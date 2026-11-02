import { apiClient } from '@/api/client'
import { IDEMPOTENCY_HEADER } from '@/constants/payments'
import { domainRequests } from './domainRequest'

const NOT_FOUND = 404
const UNAVAILABLE = 503

const { read, write } = domainRequests('payments', 'payment')

export const paymentsService = {
  listMine(params = {}) {
    return read('listMine', () => apiClient.get('/payments/mine', { params }), params)
  },

  create(purpose, idempotencyKey) {
    const payload = { purpose }
    const headers = { [IDEMPOTENCY_HEADER]: idempotencyKey }
    return write('create', () => apiClient.post('/payments', payload, { headers }), payload, [UNAVAILABLE])
  },

  get(paymentId) {
    return read('get', () => apiClient.get(`/payments/${paymentId}`), null, [NOT_FOUND])
  },

  verify(paymentId, providerPaymentId) {
    const payload = { providerPaymentId }
    return write('verify', () => apiClient.post(`/payments/${paymentId}/verify`, payload), payload, [NOT_FOUND, UNAVAILABLE])
  },

  getReceipt(paymentId) {
    return read('getReceipt', () => apiClient.get(`/payments/${paymentId}/receipt`), null, [NOT_FOUND])
  },

  requestReceiptDownload(paymentId) {
    return write('requestReceiptDownload', () => apiClient.post(`/payments/${paymentId}/receipt/download`), {}, [UNAVAILABLE])
  },
}
