import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { readJson } from '../stores/entityRejection'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan, sessionIsSuperAdmin } from '../stores/sessionPermissions'
import { authSettingsResource, setEntitySecondFactor } from '../stores/authSettingsStore'
import { authField, authReject, authRoute } from './authRoute'

const PERMISSION = 'settings.auth.manage'
const REASON_LENGTH = { MIN: 10, MAX: 1000 }
const LATENCY = { READ: 220, WRITE: 360 }

function assertManager() {
  if (!currentSessionUser()) throw authReject.unauthenticated()
  if (!sessionCan(PERMISSION)) throw authReject.forbidden()
}

function reasonOf(body) {
  const reason = String(body.reason ?? '').trim()
  if (reason && (reason.length < REASON_LENGTH.MIN || reason.length > REASON_LENGTH.MAX))
    throw authReject.validation({
      reason: [authField('field.reason_min', { min: REASON_LENGTH.MIN })],
    })
  return reason
}

function enabledOf(body) {
  if (!('entitySecondFactorEnabled' in body)) return undefined
  if (typeof body.entitySecondFactorEnabled !== 'boolean')
    throw authReject.validation({ entitySecondFactorEnabled: [authField('field.boolean')] })
  return body.entitySecondFactorEnabled
}

export const authSettingsHandlers = [
  http.get(
    '/api/v1/settings/auth',
    authRoute(() => {
      assertManager()
      return envelope(authSettingsResource())
    }, LATENCY.READ),
  ),

  http.put(
    '/api/v1/settings/auth',
    authRoute(async ({ request }) => {
      assertManager()
      const body = await readJson(request)
      const enabled = enabledOf(body)
      const reason = reasonOf(body)
      if (sessionIsSuperAdmin() && !reason) throw authReject.reasonRequired()
      if (enabled !== undefined) setEntitySecondFactor(enabled)
      return envelope(authSettingsResource())
    }, LATENCY.WRITE),
  ),
]
