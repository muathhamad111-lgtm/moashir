import { apiClient } from '@/api/client'
import { integrationRequests } from './integrationRequest'

const PATH = '/settings/ai'

const { read, write } = integrationRequests('aiIntegration', 'ai-integration')

export const aiIntegrationService = {
  get() {
    return read('get', () => apiClient.get(PATH))
  },
  update(payload) {
    return write('update', () => apiClient.put(PATH, payload), payload)
  },
  listModels(provider) {
    const params = { provider }
    return write('listModels', () => apiClient.get(`${PATH}/models`, { params }), params)
  },
  testConnection(provider) {
    const payload = { provider }
    return write(
      'testConnection',
      () => apiClient.post(`${PATH}/test-connection`, payload),
      payload,
    )
  },
  testAnswer(provider) {
    const payload = { provider }
    return write('testAnswer', () => apiClient.post(`${PATH}/test-answer`, payload), payload)
  },
}
