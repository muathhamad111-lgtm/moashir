import { apiClient } from '@/api/client'
import { integrationRequests } from './integrationRequest'

const { read } = integrationRequests('integrations', 'integrations-overview')

export const integrationsOverviewService = {
  list() {
    return read('list', () => apiClient.get('/settings/integrations'))
  },
}
