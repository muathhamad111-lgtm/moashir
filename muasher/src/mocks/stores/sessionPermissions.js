import { currentSessionUser } from './sessionStore'
import { allPermissionKeys, grantedPermissionKeys } from './permissionsStore'

export function permissionsOf(user) {
  if (!user) return []
  return user.superAdmin ? allPermissionKeys() : grantedPermissionKeys(user.roles)
}

export function sessionPermissions() {
  return permissionsOf(currentSessionUser())
}

export function sessionCan(key) {
  return sessionPermissions().includes(key)
}

export function sessionIsSuperAdmin() {
  return currentSessionUser()?.superAdmin === true
}
