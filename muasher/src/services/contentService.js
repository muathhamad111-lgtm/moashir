import { apiClient } from '@/api/client'
import { serviceRequest } from './serviceRequest'

export const contentService = {
  getLanding() {
    return serviceRequest('content.getLanding', () => apiClient.get('/content/landing'), {
      entity: 'landing-content',
    })
  },
  publishPage(key) {
    return serviceRequest(
      'content.publishPage',
      () => apiClient.post(`/admin/content/pages/${key}/publish`),
      { entity: 'cms-page', key },
    )
  },
  unpublishPage(key) {
    return serviceRequest(
      'content.unpublishPage',
      () => apiClient.post(`/admin/content/pages/${key}/unpublish`),
      { entity: 'cms-page', key },
    )
  },
  getModelCriteria() {
    return serviceRequest(
      'content.getModelCriteria',
      () => apiClient.get('/content/model-criteria'),
      { entity: 'model-criteria' },
    )
  },
  getHome() {
    return serviceRequest('content.getHome', () => apiClient.get('/content/home'), {
      entity: 'home-content',
    })
  },
  getHomeAdmin() {
    return serviceRequest('content.getHomeAdmin', () => apiClient.get('/admin/content/home'), {
      entity: 'home-content',
    })
  },
  updateSection(id, patch) {
    return serviceRequest(
      'content.updateSection',
      () => apiClient.patch(`/admin/content/sections/${id}`, patch),
      { entity: 'home-section', sectionId: id },
    )
  },
  toggleSection(id, enabled) {
    return serviceRequest(
      'content.toggleSection',
      () => apiClient.patch(`/admin/content/sections/${id}/enabled`, { enabled }),
      { entity: 'home-section', sectionId: id },
    )
  },
  reorderSections(orderedIds) {
    return serviceRequest(
      'content.reorderSections',
      () => apiClient.patch('/admin/content/sections/reorder', { orderedIds }),
      { entity: 'home-section' },
    )
  },
  upsertItem(sectionId, item) {
    return serviceRequest(
      'content.upsertItem',
      () => apiClient.post(`/admin/content/sections/${sectionId}/items`, item),
      { entity: 'home-item', sectionId },
    )
  },
  removeItem(sectionId, itemId) {
    return serviceRequest(
      'content.removeItem',
      () => apiClient.delete(`/admin/content/sections/${sectionId}/items/${itemId}`),
      { entity: 'home-item', sectionId, itemId },
    )
  },
  reorderItems(sectionId, orderedIds) {
    return serviceRequest(
      'content.reorderItems',
      () => apiClient.patch(`/admin/content/sections/${sectionId}/items/reorder`, { orderedIds }),
      { entity: 'home-item', sectionId },
    )
  },
  getSiteSettings() {
    return serviceRequest(
      'content.getSiteSettings',
      () => apiClient.get('/admin/content/site-settings'),
      { entity: 'site-settings' },
    )
  },
  updateSiteSettings(patch) {
    return serviceRequest(
      'content.updateSiteSettings',
      () => apiClient.patch('/admin/content/site-settings', patch),
      { entity: 'site-settings' },
    )
  },
}
