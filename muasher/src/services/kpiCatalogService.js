import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const CATALOG_PATHS = Object.freeze({
  categories: '/admin/kpi-categories',
  types: '/admin/kpi-types',
})

function catalogRequests(kind) {
  const path = CATALOG_PATHS[kind]
  const requests = domainRequests(`kpiCatalog.${kind}`, `kpi-${kind}`)
  return {
    list() {
      return requests.read('list', () => apiClient.get(path))
    },
    create(payload) {
      return requests.write('create', () => apiClient.post(path, payload), payload)
    },
    update(entryId, payload) {
      return requests.write('update', () => apiClient.put(`${path}/${entryId}`, payload), payload)
    },
    remove(entryId) {
      return requests.write('remove', () => apiClient.delete(`${path}/${entryId}`), {}, [404])
    },
  }
}

export const kpiCatalogService = {
  categories: catalogRequests('categories'),
  types: catalogRequests('types'),
}
