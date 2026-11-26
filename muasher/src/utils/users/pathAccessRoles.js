import { ROLES } from '@/constants/roles'

const QUALIFIABLE_ROLES = [ROLES.ASSESSOR]
const SCOPABLE_ROLES = [ROLES.REQUEST_FOLLOWER, ROLES.SYSTEM_ADMIN]

function holdsAny(user, roles) {
  return (user?.roles ?? []).some((role) => roles.includes(role))
}

export function isQualifiableUser(user) {
  return holdsAny(user, QUALIFIABLE_ROLES)
}

export function isScopableUser(user) {
  return holdsAny(user, SCOPABLE_ROLES)
}
