import { delay } from 'msw'
import { MockRejection, messageEntry, rejectionResponse } from '../../contract/rejection'
import { integrationMessages } from '../../fixtures/integrationMessages'
import { currentSessionUser } from '../sessionStore'
import { sessionCan, sessionIsSuperAdmin } from '../sessionPermissions'
import { INTEGRATION_ACTOR } from '../../fixtures/integrations'
import { actorDisplayName } from '../actorName'

export const INTEGRATION_LATENCY_MS = 180

export const INTEGRATION_PERMISSIONS = Object.freeze({
  payment: 'settings.payment.manage',
  sms: 'settings.sms.manage',
  email: 'settings.email.manage',
  ai: 'ai.provider.manage',
})

export function fieldMessage(code, params = {}) {
  return messageEntry(integrationMessages, code, params)
}

export const integrationReject = {
  unauthenticated: () => new MockRejection(401, 'unauthenticated'),
  forbidden: () => new MockRejection(403, 'forbidden'),
  validation: (fields) => new MockRejection(422, 'validation_failed', { fields }),
  field: (code, field) =>
    new MockRejection(422, code, { fields: { [field]: [fieldMessage(code)] } }),
  notConfigured: (kind) => new MockRejection(409, 'integration.not_configured', { meta: { kind } }),
  missing: (code) => new MockRejection(404, code),
  provider: (kind, result = null) =>
    new MockRejection(502, 'provider_error', {
      fields: { providerErrorKind: [{ ar: kind, en: kind }] },
      meta: result ? { result } : {},
    }),
  rateLimited: (seconds) =>
    new MockRejection(429, 'rate_limited', { headers: { 'Retry-After': String(seconds) } }),
}

export function integrationActor(kind) {
  const user = currentSessionUser()
  if (!user) throw integrationReject.unauthenticated()
  if (!sessionCan(INTEGRATION_PERMISSIONS[kind])) throw integrationReject.forbidden()
  return user
}

export function actorReference(user) {
  return {
    id: user.id ?? INTEGRATION_ACTOR.id,
    name: actorDisplayName(user, INTEGRATION_ACTOR.name),
  }
}

export function actorIsSuperAdmin() {
  return sessionIsSuperAdmin()
}

export async function readBody(request) {
  const text = await request.text()
  return text ? JSON.parse(text) : {}
}

export function integrationRoute(resolve) {
  return async (info) => {
    await delay(INTEGRATION_LATENCY_MS)
    try {
      return await resolve(info)
    } catch (error) {
      if (error instanceof MockRejection)
        return rejectionResponse(error, info.request, integrationMessages)
      throw error
    }
  }
}
