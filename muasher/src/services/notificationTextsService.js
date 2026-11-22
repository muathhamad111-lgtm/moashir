import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const { read, write } = domainRequests('notificationTexts', 'notification-text')

const NOT_FOUND = 404

function textPath(key) {
  return `/settings/notifications/texts/${encodeURIComponent(key)}`
}

export const notificationTextsService = {
  show(code) {
    return read(
      'show',
      () => apiClient.get(`/settings/notifications/types/${encodeURIComponent(code)}`),
      null,
      [NOT_FOUND],
    )
  },

  save(key, parts) {
    return write('save', () => apiClient.put(textPath(key), { parts }), { parts })
  },

  updateChannels(code, channels, audience = null) {
    const payload = audience ? { audience, channels } : { channels }
    return write(
      'updateChannels',
      () =>
        apiClient.patch(
          `/settings/notifications/types/${encodeURIComponent(code)}/channels`,
          payload,
        ),
      payload,
    )
  },

  reset(key) {
    return write('reset', () => apiClient.delete(textPath(key)), null, [NOT_FOUND])
  },
}
