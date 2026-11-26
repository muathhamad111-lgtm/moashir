import { currentSessionUser } from '../sessionStore'
import { sessionCan } from '../sessionPermissions'
import { contentReject } from './contentRejection'

export const CONTENT_PERMISSIONS = Object.freeze({
  HOME: 'content.home.manage',
  KPIS: 'content.kpis.manage',
  BRANDING: 'settings.branding.manage',
})

export function signedInActor() {
  const user = currentSessionUser()
  if (!user) throw contentReject.unauthenticated()
  return user
}

export function requireContentPermission(permission) {
  const user = signedInActor()
  if (!user.superAdmin && !sessionCan(permission)) throw contentReject.forbidden()
  return user
}

export function actorIsSuperAdmin(user) {
  return user?.superAdmin === true
}
