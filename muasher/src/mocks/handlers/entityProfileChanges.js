import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { paginate } from '../contract/pagination'
import { entityReject, entityRoute, fieldError, signedInUser } from '../stores/entityRejection'
import { PROFILE_SECTION_KEYS } from '../stores/entityProfileSections'
import {
  ENTITY_PERMISSIONS as P,
  hasFullAccess,
  ownEntityOf,
  viewableEntity,
} from '../stores/entityAccess'
import { sessionCan } from '../stores/sessionPermissions'
import { changesOf, ownChangeResource, staffChangeResource } from '../stores/profileChangesStore'

function sectionFilter(request) {
  const section = new URL(request.url).searchParams.get('section')
  if (section === null || section === '') return null
  if (!PROFILE_SECTION_KEYS.includes(section))
    throw entityReject.validation({ section: [fieldError('field.invalid_choice')] })
  return section
}

function page(request, changes, resource) {
  const { items, pagination } = paginate(changes, new URL(request.url).searchParams)
  return envelope(items.map(resource), { pagination })
}

export const entityProfileChangesHandlers = [
  http.get(
    '/api/v1/entities/me/profile-changes',
    entityRoute(({ request }) => {
      const user = signedInUser()
      if (!user.entityId || !sessionCan(P.PROFILE_VIEW)) throw entityReject.forbidden()
      return page(
        request,
        changesOf(ownEntityOf(user).id, sectionFilter(request)),
        ownChangeResource,
      )
    }),
  ),

  http.get(
    '/api/v1/entities/:id/profile-changes',
    entityRoute(({ params, request }) => {
      const user = signedInUser()
      const entity = viewableEntity(user, params.id)
      if (!hasFullAccess(user)) throw entityReject.forbidden()
      return page(request, changesOf(entity.id, sectionFilter(request)), staffChangeResource)
    }),
  ),
]
