import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const { read, write } = domainRequests('regions', 'region')

export const regionsService = {
  listRegions() {
    return read('listRegions', () => apiClient.get('/regions'))
  },
  listCities(params = {}) {
    return read('listCities', () => apiClient.get('/cities', { params }), params)
  },
  createCity(regionId, payload) {
    return write('createCity', () => apiClient.post(`/regions/${regionId}/cities`, payload), payload)
  },
  updateCity(id, payload) {
    return write('updateCity', () => apiClient.put(`/cities/${id}`, payload), payload)
  },
  setCityActive(id, active) {
    const payload = { active }
    return write('setCityActive', () => apiClient.patch(`/cities/${id}/active`, payload), payload)
  },
}
