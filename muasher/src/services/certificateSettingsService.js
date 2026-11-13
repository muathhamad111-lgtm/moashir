import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { CERTIFICATE_LEVELS } from '@/constants/certificates'

const { read, write } = domainRequests('certificateSettings', 'settings-certificates')

function toCertificateSettings(raw = {}) {
  return {
    accreditedIssuanceEnabled: raw.accreditedIssuanceEnabled === true,
    levelThresholds: Object.fromEntries(
      CERTIFICATE_LEVELS.map((level) => [level, raw.levelThresholds?.[level] ?? null]),
    ),
  }
}

function mapData(request) {
  return request.then((envelope) => ({ ...envelope, data: toCertificateSettings(envelope.data) }))
}

export const certificateSettingsService = {
  get() {
    return mapData(read('get', () => apiClient.get('/settings/certificates')))
  },

  update(payload) {
    return mapData(write('update', () => apiClient.put('/settings/certificates', payload), payload))
  },
}
