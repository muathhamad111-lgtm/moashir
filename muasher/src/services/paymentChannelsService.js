import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toBankAccount, toPaymentChannels } from './mappers/bankTransfers'

const CHANNELS_PATH = '/settings/payment/channels'
const ACCOUNTS_PATH = '/settings/payment/bank-accounts'
const NOT_FOUND = 404

const { read, write } = domainRequests('paymentChannels', 'payment-channels')

function withReason(payload, reason) {
  return reason ? { ...payload, reason } : payload
}

function channelsEnvelope(envelope) {
  return { ...envelope, data: toPaymentChannels(envelope.data) }
}

function accountEnvelope(envelope) {
  return { ...envelope, data: toBankAccount(envelope.data) }
}

function accountsEnvelope(envelope) {
  return { ...envelope, data: (envelope.data ?? []).map(toBankAccount) }
}

export const paymentChannelsService = {
  getChannels() {
    return read('getChannels', () => apiClient.get(CHANNELS_PATH)).then(channelsEnvelope)
  },

  updateChannels(channels, reason = null) {
    const payload = withReason({ channels }, reason)
    return write('updateChannels', () => apiClient.put(CHANNELS_PATH, payload), payload).then(
      channelsEnvelope,
    )
  },

  listAccounts(params = {}) {
    return read('listAccounts', () => apiClient.get(ACCOUNTS_PATH, { params }), params).then(
      accountsEnvelope,
    )
  },

  createAccount(account, reason = null) {
    const payload = withReason(account, reason)
    return write('createAccount', () => apiClient.post(ACCOUNTS_PATH, payload), payload).then(
      accountEnvelope,
    )
  },

  updateNote(accountId, payerNote, reason = null) {
    const payload = withReason({ payerNote }, reason)
    return write(
      'updateNote',
      () => apiClient.patch(`${ACCOUNTS_PATH}/${accountId}`, payload),
      payload,
      [NOT_FOUND],
    ).then(accountEnvelope)
  },

  activate(accountId, reason = null) {
    const payload = withReason({}, reason)
    return write(
      'activate',
      () => apiClient.post(`${ACCOUNTS_PATH}/${accountId}/activate`, payload),
      payload,
      [NOT_FOUND],
    ).then(accountEnvelope)
  },

  deactivate(accountId, reason = null) {
    const payload = withReason({}, reason)
    return write(
      'deactivate',
      () => apiClient.post(`${ACCOUNTS_PATH}/${accountId}/deactivate`, payload),
      payload,
      [NOT_FOUND],
    ).then(accountEnvelope)
  },

  remove(accountId, reason = null) {
    const payload = withReason({}, reason)
    return write(
      'remove',
      () => apiClient.delete(`${ACCOUNTS_PATH}/${accountId}`, { data: payload }),
      payload,
      [NOT_FOUND],
    )
  },

  reorder(orderedIds, reason = null) {
    const payload = withReason({ orderedIds }, reason)
    return write('reorder', () => apiClient.put(`${ACCOUNTS_PATH}/order`, payload), payload).then(
      accountsEnvelope,
    )
  },
}
