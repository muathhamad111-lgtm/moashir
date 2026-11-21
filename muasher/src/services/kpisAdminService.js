import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const requests = domainRequests('kpisAdmin', 'kpi')

export const kpisAdminService = {
  list(params = {}) {
    return requests.read('list', () => apiClient.get('/admin/kpis', { params }), params)
  },
  get(kpiId) {
    return requests.read('get', () => apiClient.get(`/admin/kpis/${kpiId}`))
  },
  create(payload) {
    return requests.write('create', () => apiClient.post('/admin/kpis', payload), payload)
  },
  update(kpiId, payload) {
    return requests.write('update', () => apiClient.put(`/admin/kpis/${kpiId}`, payload), payload)
  },
  archive(kpiId) {
    return requests.write('archive', () => apiClient.delete(`/admin/kpis/${kpiId}`), {}, [404])
  },
  setPublished(kpiId, published) {
    return requests.write(
      'setPublished',
      () => apiClient.patch(`/admin/kpis/${kpiId}/published`, { published }),
      { published },
    )
  },
  move(kpiId, direction) {
    return requests.write(
      'move',
      () => apiClient.patch(`/admin/kpis/${kpiId}/reorder`, { direction }),
      {
        direction,
      },
    )
  },
  reorder(orderedIds) {
    return requests.write('reorder', () => apiClient.patch('/admin/kpis/reorder', { orderedIds }), {
      orderedIds,
    })
  },
  listValues(kpiId) {
    return requests.read('listValues', () => apiClient.get(`/admin/kpis/${kpiId}/values`))
  },
  recordValue(kpiId, measurement) {
    return requests.write(
      'recordValue',
      () => apiClient.post(`/admin/kpis/${kpiId}/values`, measurement),
      measurement,
    )
  },
  uploadImage(file) {
    const form = new FormData()
    form.append('file', file)
    return requests.write('uploadImage', () => apiClient.post('/admin/kpis/images', form), {
      fileType: file?.type ?? '',
    })
  },
}
