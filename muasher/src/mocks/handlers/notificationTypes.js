import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse, noContent } from '../contract/responses'
import { readJson } from '../stores/entityRejection'
import { requestLocale } from '../contract/locale'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan } from '../stores/sessionPermissions'
import { NOTIFICATION_TYPE_CATALOG } from '../fixtures/notificationTypes'
import {
  audienceKindsOf,
  channelChoiceErrors,
  channelStatesOf,
  enabledChannelsOf,
  isCoreType,
  findNotificationType,
  findTemplate,
  notificationTypeResource,
  resetNotificationText,
  saveNotificationText,
  switchNotificationChannels,
  validateTextParts,
} from '../stores/notificationTexts'

const LATENCY_MS = 260
const MANAGE = 'settings.notifications.manage'
const CHANNEL_OF_LETTER = { I: 'in_app', E: 'email', S: 'sms' }
const OPTIONAL_CHANNELS = ['email', 'sms']
const ALL_CHANNELS = ['in_app', 'email', 'sms']
const BULK_SCOPES = ['codes', 'module', 'all']

function bulkTargets({ scope, codes, module, enabled } = {}) {
  if (!BULK_SCOPES.includes(scope) || typeof enabled !== 'boolean') return null
  if (scope === 'codes')
    return Array.isArray(codes) ? codes.map(findNotificationType).filter(Boolean) : null
  if (scope === 'module')
    return NOTIFICATION_TYPE_CATALOG.filter((type) => type.code.split('-')[0] === module)
  return NOTIFICATION_TYPE_CATALOG
}

function typeRow(type) {
  const channels = [...type.channels].map((letter) => CHANNEL_OF_LETTER[letter])
  return {
    code: type.code,
    module: type.code.split('-')[0],
    category: type.category,
    channels,
    audiences: [...type.audiences],
    isMandatory: type.isMandatory,
    isCore: isCoreType(type),
    audienceKinds: audienceKindsOf(type),
    channelStates: channelStatesOf(type),
    enabledChannels: enabledChannelsOf(type),
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

function denied() {
  if (!currentSessionUser()) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
  if (!sessionCan(MANAGE)) return errorResponse(403, 'forbidden', 'Forbidden.')
  return null
}

const MISSING = () => errorResponse(404, 'not_found', 'Not found.')

export const notificationTypesHandlers = [
  http.get('/api/v1/settings/notifications/types', async ({ request }) => {
    await delay(LATENCY_MS)
    if (!currentSessionUser()) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    if (!sessionCan(MANAGE)) return errorResponse(403, 'forbidden', 'Forbidden.')
    const rows = notificationTypeRows(new URL(request.url).searchParams)
    return envelope(rows, { total: rows.length })
  }),
  http.get('/api/v1/settings/notifications/types/:code', async ({ params }) => {
    await delay(LATENCY_MS)
    const refusal = denied()
    if (refusal) return refusal
    const type = findNotificationType(params.code)
    return type ? envelope(notificationTypeResource(type)) : MISSING()
  }),

  http.patch('/api/v1/settings/notifications/types/:code/channels', async ({ params, request }) => {
    await delay(LATENCY_MS)
    const refusal = denied()
    if (refusal) return refusal
    const type = findNotificationType(params.code)
    if (!type) return MISSING()
    const body = await readJson(request)
    const errors = channelChoiceErrors(type, body, requestLocale(request))
    if (Object.keys(errors).length)
      return errorResponse(422, 'validation_failed', Object.values(errors)[0][0], errors)
    switchNotificationChannels(type, body.channels, body.audience ? [body.audience] : undefined)
    return envelope(notificationTypeResource(type))
  }),

  http.patch('/api/v1/settings/notifications/channels', async ({ request }) => {
    await delay(LATENCY_MS)
    const refusal = denied()
    if (refusal) return refusal
    const change = await readJson(request)
    const types = bulkTargets(change)
    if (!types)
      return errorResponse(422, 'validation_failed', 'The selection is not valid.', {
        scope: ['invalid'],
      })
    const switched = types.filter((type) => !isCoreType(type))
    switched.forEach((type) => {
      const audiences = (change.audiences ?? audienceKindsOf(type)).filter((kind) =>
        audienceKindsOf(type).includes(kind),
      )
      const choices = Object.fromEntries(
        (change.channels ?? ALL_CHANNELS).map((channel) => [channel, Boolean(change.enabled)]),
      )
      switchNotificationChannels(type, choices, audiences)
    })
    const rows = notificationTypeRows()
    return envelope(rows, { total: rows.length, updated: switched.length })
  }),

  http.put('/api/v1/settings/notifications/texts/:key', async ({ params, request }) => {
    await delay(LATENCY_MS)
    const refusal = denied()
    if (refusal) return refusal
    const found = findTemplate(params.key)
    if (!found) return MISSING()
    const { parts } = await readJson(request)
    const errors = validateTextParts(found, parts, requestLocale(request))
    if (Object.keys(errors).length)
      return errorResponse(422, 'validation_failed', 'The text is not valid.', errors)
    saveNotificationText(found, parts, new Date().toISOString())
    return envelope(notificationTypeResource(found.type))
  }),

  http.delete('/api/v1/settings/notifications/texts/:key', async ({ params }) => {
    await delay(LATENCY_MS)
    const refusal = denied()
    if (refusal) return refusal
    if (!findTemplate(params.key)) return MISSING()
    resetNotificationText(params.key)
    return noContent()
  }),
]
