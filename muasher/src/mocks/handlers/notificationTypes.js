import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan } from '../stores/sessionPermissions'
import { NOTIFICATION_TYPE_CATALOG } from '../fixtures/notificationTypes'

const LATENCY_MS = 260
const MANAGE = 'settings.notifications.manage'
const CHANNEL_OF_LETTER = { I: 'in_app', E: 'email', S: 'sms' }
const OPTIONAL_CHANNELS = ['email', 'sms']

function typeRow(type) {
  const channels = [...type.channels].map((letter) => CHANNEL_OF_LETTER[letter])
  return {
    code: type.code,
    module: type.code.split('-')[0],
    category: type.category,
    channels,
    audiences: [...type.audiences],
    isMandatory: type.isMandatory,
    userConfigurableChannels: type.isMandatory
      ? []
      : channels.filter((channel) => OPTIONAL_CHANNELS.includes(channel)),
  }
}

function matches(row, query) {
  const module = query.get('filter[module]')
  const category = query.get('filter[category]')
  const audience = query.get('filter[audience]')
  if (module && row.module !== module) return false
  if (category && row.category !== category) return false
  return !audience || row.audiences.includes(audience)
}

export function notificationTypeRows(query = new URLSearchParams()) {
  return NOTIFICATION_TYPE_CATALOG.map(typeRow).filter((row) => matches(row, query))
}

export const notificationTypesHandlers = [
  http.get('/api/v1/settings/notifications/types', async ({ request }) => {
    await delay(LATENCY_MS)
    if (!currentSessionUser()) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    if (!sessionCan(MANAGE)) return errorResponse(403, 'forbidden', 'Forbidden.')
    const rows = notificationTypeRows(new URL(request.url).searchParams)
    return envelope(rows, { total: rows.length })
  }),
]
