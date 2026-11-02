import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const PATH = '/privacy/requests'

const { read, write } = domainRequests('privacyRequests', 'privacy-request')

export const privacyRequestsService = {
  lookupEntities(params = {}) {
    return read('lookupEntities', () => apiClient.get('/privacy/entities', { params }), params)
  },
  list(params = {}) {
    return read('list', () => apiClient.get(PATH, { params }), params)
  },
  get(id) {
    return read('get', () => apiClient.get(`${PATH}/${id}`))
  },
  create(payload) {
    return write('create', () => apiClient.post(PATH, payload), payload)
  },
  fulfil(id, note = null) {
    const payload = note ? { reason: note } : {}
    return write('fulfil', () => apiClient.post(`${PATH}/${id}/fulfil`, payload), payload)
  },
  reject(id, reason) {
    const payload = { reason }
    return write('reject', () => apiClient.post(`${PATH}/${id}/reject`, payload), payload)
  },
}
