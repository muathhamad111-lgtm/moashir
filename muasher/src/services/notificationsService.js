import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toNotification, toNotificationPreference } from './mappers/notifications'

const { read, write } = domainRequests('notifications', 'notification')

function mapList(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: (envelope.data ?? []).map(mapper) }))
}

function mapItem(request, mapper) {
  return request.then((envelope) => ({ ...envelope, data: mapper(envelope.data) }))
}

export const notificationsService = {
  list(params = {}) {
    return mapList(
      read('list', () => apiClient.get('/notifications', { params }), params),
      toNotification,
    )
  },
  unreadCount() {
    return read('unreadCount', () => apiClient.get('/notifications/unread-count'))
  },
  markRead(id) {
    return mapItem(
      write('markRead', () => apiClient.patch(`/notifications/${id}/read`)),
      toNotification,
    )
  },
  archive(id) {
    return mapItem(
      write('archive', () => apiClient.patch(`/notifications/${id}/archive`)),
      toNotification,
    )
  },
  markAllRead(payload = {}) {
    return write('markAllRead', () => apiClient.post('/notifications/read-all', payload), payload)
  },
  getPreferences() {
    return mapList(
      read('getPreferences', () => apiClient.get('/me/notification-preferences')),
      toNotificationPreference,
    )
  },
  updatePreferences(preferences) {
    const payload = { preferences }
    return mapList(
      write(
        'updatePreferences',
        () => apiClient.put('/me/notification-preferences', payload),
        payload,
      ),
      toNotificationPreference,
    )
  },
}
