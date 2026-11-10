import { expireCookie, readCookie, writeCookie } from '../contract/cookies'

export const MOCK_SESSION_COOKIE = 'moashir_mock_session'

function cookieDescriptor() {
  const raw = readCookie(MOCK_SESSION_COOKIE)
  if (!raw) return null
  try {
    return JSON.parse(raw)
  } catch {
    return null
  }
}

export function startMockSession(descriptor) {
  writeCookie(MOCK_SESSION_COOKIE, JSON.stringify(descriptor))
}

export function endMockSession() {
  expireCookie(MOCK_SESSION_COOKIE)
}

export function currentSessionUser() {
  const descriptor = cookieDescriptor()
  if (!Array.isArray(descriptor?.roles)) return null
  return {
    id: descriptor.id ?? null,
    name: descriptor.name ?? null,
    roles: descriptor.roles,
    entityId: descriptor.entityId ?? null,
    locale: descriptor.locale ?? null,
    superAdmin: descriptor.superAdmin === true,
  }
}

export function currentSessionRoles() {
  return currentSessionUser()?.roles ?? []
}

export function sessionHasRole(role) {
  return currentSessionRoles().includes(role)
}

export function updateMockSession(changes) {
  const descriptor = cookieDescriptor()
  if (!descriptor) return null
  const next = { ...descriptor, ...changes }
  startMockSession(next)
  return next
}
