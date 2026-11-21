import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const requests = domainRequests('siteSettings', 'site-settings')

export const siteSettingsService = {
  get() {
    return requests.read('get', () => apiClient.get('/admin/content/site-settings'))
  },
  update(patch) {
    return requests.write(
      'update',
      () => apiClient.patch('/admin/content/site-settings', patch),
      patch,
    )
  },
}
