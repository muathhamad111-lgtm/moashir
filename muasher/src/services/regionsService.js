import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const { read } = domainRequests('regions', 'region')

export const regionsService = {
  listRegions() {
    return read('listRegions', () => apiClient.get('/regions'))
  },
}
