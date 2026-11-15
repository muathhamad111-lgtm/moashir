import { apiClient } from '@/api/client'
import { SMS_STATUSES, SMS_STATUS_VALUES } from '@/constants/signIn'
import { normalizedCodeChannels } from '@/utils/auth/codeChannels'
import { domainRequests } from './domainRequest'

const FORBIDDEN = 403
const { read, write } = domainRequests('authSettings', 'settings-auth')

function smsStatusOf(raw) {
  return SMS_STATUS_VALUES.includes(raw) ? raw : SMS_STATUSES.UNAVAILABLE
}

function toAuthSettings(raw = {}) {
  return {
    entitySignInCodeChannels: normalizedCodeChannels(raw.entitySignInCodeChannels),
    generalEmailNotifications: raw.generalEmailNotifications !== false,
    entityRegistrationReview: raw.entityRegistrationReview !== false,
    twoFactorEnabled: raw.twoFactorEnabled !== false,
    twoFactorRoles: Array.isArray(raw.twoFactorRoles) ? [...raw.twoFactorRoles] : [],
    smsStatus: smsStatusOf(raw.smsStatus),
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
