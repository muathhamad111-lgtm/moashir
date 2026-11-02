import { apiClient } from '@/api/client'
import { serviceRequest } from './serviceRequest'
import { toSessionUser } from './mappers/session'

export const demoSessionService = {
  async start(persona) {
    const envelope = await serviceRequest(
      'dev.demoSession.start',
      () => apiClient.post('/dev/demo-session', { persona }, { skipSessionExpiry: true }),
      { entity: 'demo-session' },
    )
    return { ...envelope, data: toSessionUser(envelope.data ?? {}) }
  },
}
