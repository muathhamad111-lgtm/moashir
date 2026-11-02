import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const PATH = '/admin/entity-email-changes'

const { read, write } = domainRequests('entityEmailChanges', 'email-change')

export const entityEmailChangesService = {
  list(params = {}) {
    return read('list', () => apiClient.get(PATH, { params }), params)
  },
  approve(id) {
    return write('approve', () => apiClient.post(`${PATH}/${id}/approve`, {}))
  },
  reject(id, reason) {
    const payload = { reason }
    return write('reject', () => apiClient.post(`${PATH}/${id}/reject`, payload), payload)
  },
}
