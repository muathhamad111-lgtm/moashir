import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toBulkOutcome, toStaffTransfer, toTransferAccount } from './mappers/bankTransfers'

const PATH = '/finance/bank-transfers'
const ACCOUNTS_PATH = `${PATH}/accounts`
const NOT_FOUND = 404

const { read, write } = domainRequests('bankTransfers', 'bank-transfer')

function withReason(payload, reason) {
  return reason ? { ...payload, reason } : payload
}

function transferEnvelope(envelope) {
  return { ...envelope, data: toStaffTransfer(envelope.data) }
}

function outcomeEnvelope(envelope) {
  return { ...envelope, data: toBulkOutcome(envelope.data) }
}

function rejectionPayload({ rejectionReason, note }, reason) {
  return withReason(note ? { rejectionReason, note } : { rejectionReason }, reason)
}

export const bankTransfersService = {
  list(params = {}) {
    return read('list', () => apiClient.get(PATH, { params }), params).then((envelope) => ({
      ...envelope,
      data: (envelope.data ?? []).map(toStaffTransfer),
    }))
  },

  listAccounts(params = {}) {
    return read('listAccounts', () => apiClient.get(ACCOUNTS_PATH, { params }), params).then(
      (envelope) => ({ ...envelope, data: (envelope.data ?? []).map(toTransferAccount) }),
    )
  },

  get(paymentId) {
    return read('get', () => apiClient.get(`${PATH}/${paymentId}`), null, [NOT_FOUND]).then(
      transferEnvelope,
    )
  },

  requestProofDownload(paymentId) {
    return write(
      'requestProofDownload',
      () => apiClient.post(`${PATH}/${paymentId}/proof/download`),
      {},
      [NOT_FOUND],
    )
  },

  approve(paymentId, reason = null) {
    const payload = withReason({}, reason)
    return write(
      'approve',
      () => apiClient.post(`${PATH}/${paymentId}/approve`, payload),
      payload,
      [NOT_FOUND],
    ).then(transferEnvelope)
  },

  reject(paymentId, rejection, reason = null) {
    const payload = rejectionPayload(rejection, reason)
    return write('reject', () => apiClient.post(`${PATH}/${paymentId}/reject`, payload), payload, [
      NOT_FOUND,
    ]).then(transferEnvelope)
  },

  bulkApprove(paymentIds, reason = null) {
    const payload = withReason({ paymentIds }, reason)
    return write(
      'bulkApprove',
      () => apiClient.post(`${PATH}/bulk-approve`, payload),
      payload,
    ).then(outcomeEnvelope)
  },

  bulkReject(paymentIds, rejection, reason = null) {
    const payload = { paymentIds, ...rejectionPayload(rejection, reason) }
    return write('bulkReject', () => apiClient.post(`${PATH}/bulk-reject`, payload), payload).then(
      outcomeEnvelope,
    )
  },
}
