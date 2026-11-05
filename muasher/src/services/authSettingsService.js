import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const FORBIDDEN = 403
const { read, write } = domainRequests('authSettings', 'settings-auth')

function toAuthSettings(raw = {}) {
  return {
    entitySecondFactorEnabled: raw.entitySecondFactorEnabled === true,
    entitySecondFactorChannel: raw.entitySecondFactorChannel ?? null,
    staffTotpRequired: raw.staffTotpRequired === true,
  }
}

function mapData(request) {
  return request.then((envelope) => ({ ...envelope, data: toAuthSettings(envelope.data) }))
}

export const authSettingsService = {
  get() {
    return mapData(read('get', () => apiClient.get('/settings/auth'), undefined, [FORBIDDEN]))
  },

  update(payload) {
    return mapData(
      write('update', () => apiClient.put('/settings/auth', payload), payload, [FORBIDDEN]),
    )
  },
}
