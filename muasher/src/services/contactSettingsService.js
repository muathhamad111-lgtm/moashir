import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'

const { read, write } = domainRequests('contactSettings', 'settings-contact-messages')

const DEFAULT_LIMITS = { min: 1, max: 120 }

export function toContactSettings(raw = {}) {
  return {
    retentionMonths: raw.retentionMonths ?? null,
    retentionLimits: {
      min: raw.retentionLimits?.min ?? DEFAULT_LIMITS.min,
      max: raw.retentionLimits?.max ?? DEFAULT_LIMITS.max,
    },
  }
}

function mapData(request) {
  return request.then((envelope) => ({ ...envelope, data: toContactSettings(envelope.data) }))
}

export const contactSettingsService = {
  get() {
    return mapData(read('get', () => apiClient.get('/settings/contact-messages')))
  },

  update(payload) {
    return mapData(
      write('update', () => apiClient.put('/settings/contact-messages', payload), payload),
    )
  },
}
