import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const { read, write } = domainRequests('settings.finance', 'settings-finance')

export const financeSettingsService = {
  get() {
    return read('get', () => apiClient.get('/settings/finance'))
  },

  update(payload) {
    return write('update', () => apiClient.put('/settings/finance', payload), payload)
  },
}
