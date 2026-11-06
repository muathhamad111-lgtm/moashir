import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const { read } = domainRequests('profileChanges', 'entity-profile-change')

export const profileChangesService = {
  listMine(params = {}) {
    return read('listMine', () => apiClient.get('/entities/me/profile-changes', { params }), params)
  },
  listForEntity(entityId, params = {}) {
    return read(
      'listForEntity',
      () => apiClient.get(`/entities/${entityId}/profile-changes`, { params }),
      params,
    )
  },
}
