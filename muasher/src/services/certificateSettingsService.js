import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { CERTIFICATE_LEVELS, ISSUANCE_MODES } from '@/constants/certificates'

const { read, write } = domainRequests('certificateSettings', 'settings-certificates')

function toCertificateSettings(raw = {}) {
  return {
    accreditedIssuanceMode:
      raw.accreditedIssuanceMode === ISSUANCE_MODES.MANUAL
        ? ISSUANCE_MODES.MANUAL
        : ISSUANCE_MODES.AUTOMATIC,
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
