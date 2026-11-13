import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const NOT_FOUND = 404

const { read, write } = domainRequests('contactMessages', 'contact-message')

export const contactMessagesService = {
  submit(message) {
    return write('submit', () => apiClient.post('/contact-messages', message), message)
  },
  listMine(params = {}) {
    return read('listMine', () => apiClient.get('/me/contact-messages', { params }), params)
  },
  getMine(messageId) {
    return read('getMine', () => apiClient.get(`/me/contact-messages/${messageId}`), null, [
      NOT_FOUND,
    ])
  },
  markSeen(messageId) {
    return write('markSeen', () => apiClient.patch(`/me/contact-messages/${messageId}/seen`), {}, [
      NOT_FOUND,
    ])
  },
  reply(messageId, body) {
    const payload = { body }
    return write(
      'reply',
      () => apiClient.post(`/me/contact-messages/${messageId}/replies`, payload),
      payload,
      [NOT_FOUND],
    )
  },
}
