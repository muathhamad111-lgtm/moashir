import { apiClient } from '@/api/client'
import { serviceRequest } from './serviceRequest'

const NOT_PUBLISHED = [404]

export const legalService = {
  getCurrent(kind) {
    return serviceRequest(
      'legal.getCurrent',
      () => apiClient.get(`/content/legal/${kind}`),
      { entity: 'legal-text', kind },
      { expectedStatuses: NOT_PUBLISHED },
    )
  },
}
