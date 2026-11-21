import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const { read, write } = domainRequests('entityLifecycle', 'entity')

function reasonAction(action, id, verb, reason) {
  const payload = { reason }
  return write(action, () => apiClient.post(`/entities/${id}/${verb}`, payload), payload)
}

export const entityLifecycleService = {
  deactivate(id, reason) {
    return reasonAction('deactivate', id, 'deactivate', reason)
  },
  reactivate(id, reason) {
    return reasonAction('reactivate', id, 'reactivate', reason)
  },
  approve(id, reason = '') {
    return reasonAction('approve', id, 'approve', reason || null)
  },
  reject(id, reason) {
    return reasonAction('reject', id, 'reject', reason)
  },
  restore(id, reason) {
    return reasonAction('restore', id, 'restore', reason)
  },
  resendVerification(id) {
    return write('resendVerification', () =>
      apiClient.post(`/entities/${id}/verification/resend`, {}),
    )
  },
  deletionImpact(id) {
    return read('deletionImpact', () => apiClient.get(`/entities/${id}/deletion-impact`))
  },
  softDelete(id, { reason, acknowledgedRequestNumbers = [] }) {
    const payload = {
      reason,
      confirmOpenRequests: acknowledgedRequestNumbers.length > 0,
      acknowledgedRequestNumbers,
    }
    return write(
      'softDelete',
      () => apiClient.delete(`/entities/${id}`, { data: payload }),
      payload,
    )
  },
  permanentlyDelete(id, { reason, confirmation, disclaimerAcknowledged }) {
    const payload = { reason, confirmation, disclaimerAcknowledged }
    return write(
      'permanentlyDelete',
      () => apiClient.delete(`/entities/${id}/permanent`, { data: payload }),
      payload,
    )
  },
}
