import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toUser, toUserStats } from './mappers/users'
import { withJsonErrorBody } from './mappers/blobError'
import { toDownload } from './mappers/download'

const EXPORT_FALLBACK_NAME = 'users.csv'

const { read, write } = domainRequests('users', 'user')

function mapUser(request) {
  return request.then((envelope) => ({ ...envelope, data: toUser(envelope.data) }))
}

function action(name, userId, verb, payload) {
  return mapUser(write(name, () => apiClient.post(`/users/${userId}/${verb}`, payload), payload))
}

export const usersService = {
  list(params = {}) {
    return read('list', () => apiClient.get('/users', { params }), params).then((envelope) => ({
      ...envelope,
      data: (envelope.data ?? []).map(toUser),
      meta: { ...envelope.meta, stats: toUserStats(envelope.meta?.stats) },
    }))
  },

  get(userId) {
    return mapUser(read('get', () => apiClient.get(`/users/${userId}`)))
  },

  create(payload) {
    return mapUser(write('create', () => apiClient.post('/users', payload), payload))
  },

  update(userId, payload) {
    return mapUser(write('update', () => apiClient.put(`/users/${userId}`, payload), payload))
  },

  suspend(userId, payload) {
    return action('suspend', userId, 'suspend', payload)
  },

  activate(userId, payload = {}) {
    return action('activate', userId, 'activate', payload)
  },

  unlock(userId, payload = {}) {
    return action('unlock', userId, 'unlock', payload)
  },

  resetMfa(userId, payload) {
    return action('resetMfa', userId, 'mfa/reset', payload)
  },

  updateAssessorQualifications(userId, payload) {
    return mapUser(
      write(
        'updateAssessorQualifications',
        () => apiClient.put(`/users/${userId}/assessor-qualifications`, payload),
        payload,
      ),
    )
  },

  updateRequestScope(userId, payload) {
    return mapUser(
      write(
        'updateRequestScope',
        () => apiClient.put(`/users/${userId}/request-scope`, payload),
        payload,
      ),
    )
  },

  sendPasswordResetLink(userId) {
    return write(
      'sendPasswordResetLink',
      () => apiClient.post(`/users/${userId}/password/reset-link`, {}),
      {},
    )
  },

  revokeSessions(userId, payload = {}) {
    return action('revokeSessions', userId, 'sessions/revoke', payload)
  },

  cancelInvitation(userId) {
    return write('cancelInvitation', () => apiClient.delete(`/users/${userId}/invitation`), {})
  },

  exportCsv(params = {}) {
    const execute = () =>
      withJsonErrorBody(apiClient.get('/users/export', { params, responseType: 'blob' })).then(
        (response) => toDownload(response, EXPORT_FALLBACK_NAME),
      )
    return read('exportCsv', execute, params)
  },

  resendInvitation(userId) {
    return write(
      'resendInvitation',
      () => apiClient.post(`/users/${userId}/invitation/resend`, {}),
      {},
    )
  },
}
