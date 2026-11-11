import { entityReject, signedInUser } from './entityRejection'
import { ENTITY_PERMISSIONS as P, ownEntityOf } from './entityAccess'
import { sessionCan } from './sessionPermissions'
import { ownEntityResource } from './entitySerializers'
import { awaitingChangeOf } from './entityEmailChanges'

const ACTIVE = 'active'

export function ownEntityActor(permission) {
  const user = signedInUser()
  if (!user.entityId || !sessionCan(permission)) throw entityReject.forbidden()
  return user
}

export function canEditOwn(entity) {
  return sessionCan(P.PROFILE_EDIT) && entity.status === ACTIVE
}

export function ownEditableEntity() {
  const user = ownEntityActor(P.PROFILE_EDIT)
  const entity = ownEntityOf(user)
  if (entity.status !== ACTIVE) throw entityReject.forbidden()
  return { user, entity }
}

export function ownResource(entity) {
  return ownEntityResource(entity, {
    awaitingChange: awaitingChangeOf(entity.id),
    canEdit: canEditOwn(entity),
  })
}
