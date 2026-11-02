import { apiClient } from '@/api/client'
import { integrationRequests } from './integrationRequest'

const PATH = '/settings/email'

const { read, write } = integrationRequests('emailIntegration', 'email-integration')

export const emailIntegrationService = {
  get() {
    return read('get', () => apiClient.get(PATH))
  },
  update(payload) {
    return write('update', () => apiClient.put(PATH, payload), payload)
  },
  testConnection() {
    return write('testConnection', () => apiClient.post(`${PATH}/test-connection`))
  },
  sendTest(payload) {
    return write('sendTest', () => apiClient.post(`${PATH}/test`, payload), payload)
  },
}
