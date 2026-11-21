import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { serviceRequest } from './serviceRequest'

const requests = domainRequests('kpis', 'kpi')

export const kpisService = {
  list(params = {}) {
    return requests.read('list', () => apiClient.get('/kpis', { params }), params, [422])
  },
  getFeatured() {
    return requests.read('getFeatured', () => apiClient.get('/kpis/featured'))
  },
  getBySlug(slug) {
    return serviceRequest(
      'kpis.getBySlug',
      () => apiClient.get(`/kpis/${slug}`),
      { entity: 'kpi' },
      { expectedStatuses: [404] },
    )
  },
  listCategories() {
    return requests.read('listCategories', () => apiClient.get('/kpi-categories'))
  },
  listTypes() {
    return requests.read('listTypes', () => apiClient.get('/kpi-types'))
  },
}
