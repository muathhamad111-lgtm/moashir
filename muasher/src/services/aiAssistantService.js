import { apiClient } from '@/api/client'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'

export const aiAssistantService = {
  getAssistant() {
    return serviceRequest('ai.getAssistant', () => apiClient.get('/ai/assistant'), {
      entity: 'ai-assistant',
    })
  },
  query(question) {
    return serviceRequest('ai.query', () => apiClient.post('/ai/query', { question }), {
      entity: 'ai-query',
      payloadShape: payloadShape({ question }),
    })
  },
}
