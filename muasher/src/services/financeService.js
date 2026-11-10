import { apiClient } from '@/api/client'
import { fromWire } from '@/api/caseMapping'
import { domainRequests } from './domainRequest'
import { withJsonErrorBody } from './mappers/blobError'
import { toDownload } from './mappers/download'
import { toLinkedRequest } from './mappers/bankTransfers'

const EXPORT_FALLBACK_NAME = 'transactions.csv'
const QUEUED = 202
const NOT_FOUND = 404
const UNAVAILABLE = 503

const { read, write } = domainRequests('finance', 'finance')

function transactionEnvelope(envelope) {
  if (!envelope?.data) return envelope
  return {
    ...envelope,
    data: { ...envelope.data, linkedRequest: toLinkedRequest(envelope.data.linkedRequest) },
  }
}

async function exportResult(response) {
  if (response.status !== QUEUED)
    return { data: { kind: 'file', ...toDownload(response, EXPORT_FALLBACK_NAME).data } }
  const envelope = fromWire(JSON.parse(await response.data.text()))
  return { data: { kind: 'queued', export: envelope.data } }
}

export const financeService = {
  getSummary(params = {}) {
    return read('getSummary', () => apiClient.get('/finance/summary', { params }), params)
  },

  listTransactions(params = {}) {
    return read(
      'listTransactions',
      () => apiClient.get('/finance/transactions', { params }),
      params,
    )
  },

  getTransaction(paymentId) {
    return read('getTransaction', () => apiClient.get(`/finance/transactions/${paymentId}`), null, [
      NOT_FOUND,
    ]).then(transactionEnvelope)
  },

  exportTransactions(params = {}) {
    const execute = () =>
      withJsonErrorBody(
        apiClient.post('/finance/transactions/export', null, { params, responseType: 'blob' }),
      ).then(exportResult)
    return write('exportTransactions', execute, params)
  },

  getExport(exportId) {
    return read('getExport', () => apiClient.get(`/finance/exports/${exportId}`), null, [NOT_FOUND])
  },

  requestExportDownload(exportId) {
    return write(
      'requestExportDownload',
      () => apiClient.post(`/finance/exports/${exportId}/download`),
      {},
    )
  },

  recordRefund(paymentId, formData) {
    const shape = Object.fromEntries([...formData.keys()].map((key) => [key, null]))
    return write(
      'recordRefund',
      () => apiClient.post(`/finance/payments/${paymentId}/refunds`, formData),
      shape,
    )
  },

  listRefunds(params = {}) {
    return read('listRefunds', () => apiClient.get('/finance/refunds', { params }), params)
  },

  requestEvidenceDownload(refundId) {
    return write(
      'requestEvidenceDownload',
      () => apiClient.post(`/finance/refunds/${refundId}/evidence/download`),
      {},
    )
  },

  requestReceiptDownload(receiptId) {
    return write(
      'requestReceiptDownload',
      () => apiClient.post(`/finance/receipts/${receiptId}/download`),
      {},
      [UNAVAILABLE],
    )
  },

  reissueReceipt(receiptId, reason) {
    const payload = { reason }
    return write(
      'reissueReceipt',
      () => apiClient.post(`/finance/receipts/${receiptId}/reissue`, payload),
      payload,
    )
  },

  manualSettle(paymentId, payload) {
    return write(
      'manualSettle',
      () => apiClient.post(`/finance/payments/${paymentId}/manual-settle`, payload),
      payload,
    ).then(transactionEnvelope)
  },

  listPaymentEvents(params = {}) {
    return read(
      'listPaymentEvents',
      () => apiClient.get('/finance/payment-events', { params }),
      params,
    )
  },
}
