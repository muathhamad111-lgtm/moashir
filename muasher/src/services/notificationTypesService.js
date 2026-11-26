import { apiClient } from '@/api/client'
import { domainRequests } from './domainRequest'
import { filterParams } from '@/utils/queryParams'

const { read, write } = domainRequests('notificationTypes', 'notification-type')

// The API client camel-cases response keys, so `in_app` arrives as `inApp` inside channel_states.
const CHANNEL_OF_KEY = { inApp: 'in_app' }

function toChannelStates(raw = {}) {
  return Object.fromEntries(
    Object.entries(raw).map(([audience, states]) => [
      audience,
      Object.fromEntries(
        Object.entries(states).map(([key, enabled]) => [CHANNEL_OF_KEY[key] ?? key, enabled]),
      ),
    ]),
  )
}

function toAudienceFields(raw) {
  return {
    isCore: raw.isCore === true,
    audienceKinds: raw.audienceKinds ?? [],
    channelStates: toChannelStates(raw.channelStates),
  }
}

export function toNotificationType(raw = {}) {
  return {
    code: raw.code ?? '',
    module: raw.module ?? '',
    category: raw.category ?? '',
    channels: raw.channels ?? [],
    enabledChannels: raw.enabledChannels ?? raw.channels ?? [],
    audiences: raw.audiences ?? [],
    isMandatory: raw.isMandatory === true,
    ...toAudienceFields(raw),
    userConfigurableChannels: raw.userConfigurableChannels ?? [],
  }
}

function toTypeList(envelope) {
  return { ...envelope, data: (envelope.data ?? []).map(toNotificationType) }
}

export const notificationTypesService = {
  list(filters = {}) {
    const params = filterParams(filters)
    return read(
      'list',
      () => apiClient.get('/settings/notifications/types', { params }),
      params,
    ).then(toTypeList)
  },

  /**
   * Switches channels on or off for many types at once: `scope` is `codes`, `module` or `all`; core types
   * are skipped by the server. Answers the whole list again.
   */
  bulkUpdate(change) {
    return write(
      'bulkUpdate',
      () => apiClient.patch('/settings/notifications/channels', change),
      change,
    ).then(toTypeList)
  },
}
