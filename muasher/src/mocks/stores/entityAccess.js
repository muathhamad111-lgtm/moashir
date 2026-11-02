import { ROLES } from '@/constants/roles'
import { ENTITY_ACCESS } from './entitySerializers'
import { sessionCan } from './sessionPermissions'
import { entityReject } from './entityRejection'
import { findEntity, isDeleted } from './entitiesStore'

export const ENTITY_PERMISSIONS = {
  PROFILE_VIEW: 'entity.profile.view',
  PROFILE_EDIT: 'entity.profile.edit',
  VIEW: 'entities.view',
  MANAGE: 'entities.manage',
  DEACTIVATE: 'entities.deactivate',
  REACTIVATE: 'entities.reactivate',
  VERIFICATION_RESEND: 'entities.verification.resend',
  EMAIL_CHANGE_APPROVE: 'entities.email_change.approve',
  DELETE: 'entities.delete',
  EXPORT: 'entities.export',
  PRIVACY_MANAGE: 'privacy.requests.manage',
}

const FULL_ACCESS_ROLES = [ROLES.SYSTEM_ADMIN, ROLES.REQUEST_FOLLOWER]
const CONTACT_ACCESS_ROLES = [ROLES.FINANCE_OFFICER]

function holdsAny(user, roles) {
  return roles.some((role) => user.roles.includes(role))
}

export function accessOf(user) {
  if (user.superAdmin) return ENTITY_ACCESS.FULL
  if (!sessionCan(ENTITY_PERMISSIONS.VIEW)) return ENTITY_ACCESS.NONE
  if (holdsAny(user, FULL_ACCESS_ROLES)) return ENTITY_ACCESS.FULL
  if (holdsAny(user, CONTACT_ACCESS_ROLES)) return ENTITY_ACCESS.CONTACT
  return ENTITY_ACCESS.NONE
}

export function hasFullAccess(user) {
  return accessOf(user) === ENTITY_ACCESS.FULL
}

export function viewableEntity(user, id) {
  const entity = findEntity(id)
  if (!entity) throw entityReject.notFound()
  if (isDeleted(entity) && !sessionCan(ENTITY_PERMISSIONS.DELETE)) throw entityReject.notFound()
  if (!sessionCan(ENTITY_PERMISSIONS.VIEW)) throw entityReject.forbidden()
  if (accessOf(user) === ENTITY_ACCESS.NONE) throw entityReject.notFound()
  return entity
}

export function boundEntity(id) {
  const entity = findEntity(id)
  if (!entity) throw entityReject.notFound()
  return entity
}

export function ownEntityOf(user) {
  const entity = user.entityId ? findEntity(user.entityId) : null
  if (!entity) throw entityReject.notFound()
  return entity
}
