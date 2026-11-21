import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const pages = domainRequests('cms', 'cms-page')
const sections = domainRequests('cms', 'cms-section')
const items = domainRequests('cms', 'cms-item')
const images = domainRequests('cms', 'cms-image')

export const cmsService = {
  getHome() {
    return pages.read('getHome', () => apiClient.get('/admin/content/home'))
  },
  listPages() {
    return pages.read('listPages', () => apiClient.get('/admin/content/pages'))
  },
  getPage(key) {
    return pages.read('getPage', () => apiClient.get(`/admin/content/pages/${key}`))
  },
  updatePage(key, payload) {
    return pages.write(
      'updatePage',
      () => apiClient.put(`/admin/content/pages/${key}`, payload),
      payload,
    )
  },
  publishPage(key) {
    return pages.write('publishPage', () => apiClient.post(`/admin/content/pages/${key}/publish`))
  },
  unpublishPage(key) {
    return pages.write('unpublishPage', () =>
      apiClient.post(`/admin/content/pages/${key}/unpublish`),
    )
  },
  createSection(pageKey, payload) {
    return sections.write(
      'createSection',
      () => apiClient.post(`/admin/content/pages/${pageKey}/sections`, payload),
      payload,
    )
  },
  updateSection(sectionId, patch) {
    return sections.write(
      'updateSection',
      () => apiClient.patch(`/admin/content/sections/${sectionId}`, patch),
      patch,
    )
  },
  toggleSection(sectionId, enabled) {
    return sections.write(
      'toggleSection',
      () => apiClient.patch(`/admin/content/sections/${sectionId}/enabled`, { enabled }),
      { enabled },
    )
  },
  removeSection(sectionId) {
    return sections.write(
      'removeSection',
      () => apiClient.delete(`/admin/content/sections/${sectionId}`),
      {},
      [404],
    )
  },
  reorderSections(orderedIds) {
    return sections.write(
      'reorderSections',
      () => apiClient.patch('/admin/content/sections/reorder', { orderedIds }),
      { orderedIds },
    )
  },
  createItem(sectionId, payload) {
    return items.write(
      'createItem',
      () => apiClient.post(`/admin/content/sections/${sectionId}/items`, payload),
      payload,
    )
  },
  updateItem(sectionId, itemId, patch) {
    return items.write(
      'updateItem',
      () => apiClient.put(`/admin/content/sections/${sectionId}/items/${itemId}`, patch),
      patch,
      [404],
    )
  },
  toggleItem(sectionId, itemId, enabled) {
    return items.write(
      'toggleItem',
      () =>
        apiClient.patch(`/admin/content/sections/${sectionId}/items/${itemId}/enabled`, {
          enabled,
        }),
      { enabled },
      [404],
    )
  },
  removeItem(sectionId, itemId) {
    return items.write(
      'removeItem',
      () => apiClient.delete(`/admin/content/sections/${sectionId}/items/${itemId}`),
      {},
      [404],
    )
  },
  reorderItems(sectionId, orderedIds) {
    return items.write(
      'reorderItems',
      () => apiClient.patch(`/admin/content/sections/${sectionId}/items/reorder`, { orderedIds }),
      { orderedIds },
    )
  },
  uploadImage(file) {
    const form = new FormData()
    form.append('file', file)
    return images.write('uploadImage', () => apiClient.post('/admin/content/images', form), {
      fileType: file?.type ?? '',
    })
  },
}
