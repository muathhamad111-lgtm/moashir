import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

export const kpisService = {
  list(params = {}) {
    return serviceRequest('kpis.list', () => apiClient.get('/kpis', { params }), {
      entity: 'kpi',
      paramsShape: payloadShape(params),
    })
  },
  getFeatured(params = {}) {
    return serviceRequest(
      'kpis.getFeatured',
      () => apiClient.get('/kpis', { params: { ...params, homepage: true } }),
      {
        entity: 'kpi',
        paramsShape: payloadShape(params),
      },
    )
  },
  adminList(params = {}) {
    return serviceRequest('kpis.adminList', () => apiClient.get('/admin/kpis', { params }), {
      entity: 'kpi',
      paramsShape: payloadShape(params),
    })
  },
  get(kpiId) {
    return serviceRequest('kpis.get', () => apiClient.get(`/admin/kpis/${kpiId}`), {
      entity: 'kpi',
    })
  },
  getBySlug(slug) {
    return serviceRequest('kpis.getBySlug', () => apiClient.get(`/kpis/${slug}`), {
      entity: 'kpi',
    })
  },
  create(payload) {
    return serviceRequest('kpis.create', () => apiClient.post('/admin/kpis', payload), {
      entity: 'kpi',
      payloadShape: payloadShape(payload),
    })
  },
  update(kpiId, payload) {
    return serviceRequest('kpis.update', () => apiClient.put(`/admin/kpis/${kpiId}`, payload), {
      entity: 'kpi',
      payloadShape: payloadShape(payload),
    })
  },
  remove(kpiId) {
    return serviceRequest('kpis.remove', () => apiClient.delete(`/admin/kpis/${kpiId}`), {
      entity: 'kpi',
    })
  },
  setPublished(kpiId, published) {
    return serviceRequest(
      'kpis.setPublished',
      () => apiClient.patch(`/admin/kpis/${kpiId}/published`, { published }),
      { entity: 'kpi' },
    )
  },
  reorder(kpiId, direction) {
    return serviceRequest(
      'kpis.reorder',
      () => apiClient.patch(`/admin/kpis/${kpiId}/reorder`, { direction }),
      { entity: 'kpi' },
    )
  },
}
