import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { toInboxMessage, toInboxStats } from './mappers/contactInbox'

const PATH = '/admin/contact-messages'
const NOT_FOUND = 404

const { read, write } = domainRequests('contactInbox', 'contact-message')

function mapMessage(request) {
  return request.then((envelope) => ({ ...envelope, data: toInboxMessage(envelope.data) }))
}

function action(name, messageId, verb, payload) {
  return mapMessage(
    write(name, () => apiClient.post(`${PATH}/${messageId}/${verb}`, payload), payload, [
      NOT_FOUND,
    ]),
  )
}

export const contactInboxService = {
  list(params = {}) {
    return read('list', () => apiClient.get(PATH, { params }), params).then((envelope) => ({
      ...envelope,
      data: (envelope.data ?? []).map(toInboxMessage),
      meta: { ...envelope.meta, stats: toInboxStats(envelope.meta?.stats) },
    }))
  },

  assignees() {
    return read('assignees', () => apiClient.get(`${PATH}/assignees`))
  },

  get(messageId) {
    return mapMessage(read('get', () => apiClient.get(`${PATH}/${messageId}`), null, [NOT_FOUND]))
  },

  assign(messageId, payload) {
    return action('assign', messageId, 'assign', payload)
  },

  addNote(messageId, payload) {
    return action('addNote', messageId, 'notes', payload)
  },

  reply(messageId, payload) {
    return action('reply', messageId, 'replies', payload)
  },

  close(messageId, payload = {}) {
    return action('close', messageId, 'close', payload)
  },

  reopen(messageId, payload = {}) {
    return action('reopen', messageId, 'reopen', payload)
  },
}
