import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

export const notificationsService = {
  list(params = {}) {
    return serviceRequest('notifications.list', () => apiClient.get('/notifications', { params }), {
      entity: 'notification',
      paramsShape: payloadShape(params),
    })
  },
  unreadCount(params = {}) {
    return serviceRequest(
      'notifications.unreadCount',
      () => apiClient.get('/notifications/unread-count', { params }),
      { entity: 'notification', paramsShape: payloadShape(params) },
    )
  },
  getAudit(id) {
    return serviceRequest(
      'notifications.getAudit',
      () => apiClient.get(`/notifications/${id}/audit`),
      { entity: 'notification' },
    )
  },
  markRead(id) {
    return serviceRequest(
      'notifications.markRead',
      () => apiClient.patch(`/notifications/${id}/read`),
      { entity: 'notification' },
    )
  },
  markAllRead(payload = {}) {
    return serviceRequest(
      'notifications.markAllRead',
      () => apiClient.post('/notifications/read-all', payload),
      { entity: 'notification', payloadShape: payloadShape(payload) },
    )
  },
}
