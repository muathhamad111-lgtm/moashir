import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const FORBIDDEN = 403
const { read, write } = domainRequests('identity', 'identity')

export const identityService = {
  provideNationalId(nationalId) {
    const payload = { nationalId }
    return write('provideNationalId', () => apiClient.post('/me/national-id', payload), payload)
  },
  registrationOptions() {
    return read('registrationOptions', () => apiClient.get('/auth/register/options'))
  },
  myChanges() {
    return read('myChanges', () => apiClient.get('/me/identity-changes'))
  },
  submitChange(payload) {
    return write('submitChange', () => apiClient.post('/me/identity-changes', payload), payload)
  },
  cancelChange(id) {
    return write('cancelChange', () => apiClient.post(`/me/identity-changes/${id}/cancel`, {}))
  },
  reviewQueue(params = {}) {
    return read('reviewQueue', () => apiClient.get('/identity-changes', { params }), params, [
      FORBIDDEN,
    ])
  },
  reviewDetail(id) {
    return read('reviewDetail', () => apiClient.get(`/identity-changes/${id}`), undefined, [
      FORBIDDEN,
    ])
  },
  approve(id, note) {
    const payload = note ? { note } : {}
    return write(
      'approve',
      () => apiClient.post(`/identity-changes/${id}/approve`, payload),
      payload,
      [FORBIDDEN],
    )
  },
  reject(id, note) {
    const payload = { note }
    return write(
      'reject',
      () => apiClient.post(`/identity-changes/${id}/reject`, payload),
      payload,
      [FORBIDDEN],
    )
  },
  userHistory(userId, params = {}) {
    return read(
      'userHistory',
      () => apiClient.get(`/users/${userId}/identity-history`, { params }),
      params,
      [FORBIDDEN],
    )
  },
  correctName(userId, payload) {
    return write(
      'correctName',
      () => apiClient.post(`/users/${userId}/name-correction`, payload),
      payload,
      [FORBIDDEN],
    )
  },
}
