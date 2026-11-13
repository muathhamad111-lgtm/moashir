import { apiClient } from '@/api/client'
import { serviceRequest } from './serviceRequest'

const PAGE_NOT_FOUND = [404]

export const contentService = {
  getStatus() {
    return serviceRequest('content.getStatus', () => apiClient.get('/content/status'), {
      entity: 'site-status',
    })
  },
  getHome() {
    return serviceRequest('content.getHome', () => apiClient.get('/content/home'), {
      entity: 'home-content',
    })
  },
  getPage(key) {
    return serviceRequest(
      'content.getPage',
      () => apiClient.get(`/content/pages/${key}`),
      { entity: 'cms-page', key },
      { expectedStatuses: PAGE_NOT_FOUND },
    )
  },
  getCertificateLevels() {
    return serviceRequest(
      'content.getCertificateLevels',
      () => apiClient.get('/content/certificate-levels'),
      { entity: 'certificate-levels' },
    )
  },
  getPathGuide(path) {
    return serviceRequest(
      'content.getPathGuide',
      () => apiClient.get(`/content/assessment-paths/${path}`),
      { entity: 'path-guide', path },
    )
  },
  getModelCriteria() {
    return serviceRequest(
      'content.getModelCriteria',
      () => apiClient.get('/content/model-criteria'),
      { entity: 'model-criteria' },
    )
  },
}
