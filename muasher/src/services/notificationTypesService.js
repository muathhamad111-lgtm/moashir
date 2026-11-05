import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { filterParams } from '@/utils/queryParams'

const { read } = domainRequests('notificationTypes', 'notification-type')

export function toNotificationType(raw = {}) {
  return {
    code: raw.code ?? '',
    module: raw.module ?? '',
    category: raw.category ?? '',
    channels: raw.channels ?? [],
    audiences: raw.audiences ?? [],
    isMandatory: raw.isMandatory === true,
    userConfigurableChannels: raw.userConfigurableChannels ?? [],
  }
}

export const notificationTypesService = {
  list(filters = {}) {
    const params = filterParams(filters)
    return read(
      'list',
      () => apiClient.get('/settings/notifications/types', { params }),
      params,
    ).then((envelope) => ({ ...envelope, data: (envelope.data ?? []).map(toNotificationType) }))
  },
}
