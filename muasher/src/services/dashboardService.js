import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

export const dashboardService = {
  getSummary(role, params = {}) {
    const query = { role, ...params }
    return serviceRequest(
      'dashboard.getSummary',
      () => apiClient.get('/dashboard/summary', { params: query }),
      {
        entity: 'dashboard',
        paramsShape: payloadShape(query),
      },
    )
  },
}
