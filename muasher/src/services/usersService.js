import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

export const usersService = {
  list(params = {}) {
    return serviceRequest('users.list', () => apiClient.get('/users', { params }), {
      entity: 'user',
      paramsShape: payloadShape(params),
    })
  },
  get(userId) {
    return serviceRequest('users.get', () => apiClient.get(`/users/${userId}`), {
      entity: 'user',
    })
  },
  create(payload) {
    return serviceRequest('users.create', () => apiClient.post('/users', payload), {
      entity: 'user',
      payloadShape: payloadShape(payload),
    })
  },
  update(userId, payload) {
    return serviceRequest('users.update', () => apiClient.put(`/users/${userId}`, payload), {
      entity: 'user',
      payloadShape: payloadShape(payload),
    })
  },
  activate(userId) {
    return serviceRequest(
      'users.activate',
      () => apiClient.patch(`/users/${userId}/status`, { status: 'active' }),
      { entity: 'user' },
    )
  },
  deactivate(userId) {
    return serviceRequest(
      'users.deactivate',
      () => apiClient.patch(`/users/${userId}/status`, { status: 'inactive' }),
      { entity: 'user' },
    )
  },
}
