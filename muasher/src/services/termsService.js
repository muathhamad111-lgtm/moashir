import { apiClient } from '@/api/client'
import { serviceRequest } from './serviceRequest'

export const termsService = {
  current() {
    return serviceRequest('terms.current', () => apiClient.get('/terms/current'), {
      entity: 'terms',
    })
  },
}
