import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const { read, write } = domainRequests('settings.insights', 'settings-insights')

export const insightsSettingsService = {
  get() {
    return read('get', () => apiClient.get('/settings/insights'))
  },

  update(payload) {
    return write('update', () => apiClient.put('/settings/insights', payload), payload)
  },
}
