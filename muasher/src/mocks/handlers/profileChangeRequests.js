import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { paginate } from '../contract/pagination'
import { PROFILE_CHANGE_STATUSES as S } from '@/constants/entityEnums'
import { actorWith, entityReject, entityRoute, readJson } from '../stores/entityRejection'
import { ENTITY_PERMISSIONS as P, ownEntityOf } from '../stores/entityAccess'
import { findEntity, referenceOf } from '../stores/entitiesStore'
import { ownEditableEntity, ownEntityActor, ownResource } from '../stores/ownEntity'
import {
  cancelRequest,
  decideRequest,
  findRequest,
  listRequests,
  pendingRequestOf,
  requestsOf,
} from '../stores/profileChangeRequests'
import { profileChangeDetail, profileChangeSummary } from '../stores/profileChangeResources'

const ADMIN_PATH = '/api/v1/admin/entity-profile-changes'

function approver() {
  return actorWith(P.PROFILE_CHANGE_APPROVE)
}

function boundRequest(id) {
  const request = findRequest(id)
  const entity = request ? findEntity(request.entityId) : null
  if (!request || !entity) throw entityReject.notFound()
  return { request, entity }
}

function matcherOf(search) {
  const term = String(search ?? '')
    .trim()
    .toLowerCase()
  if (!term) return () => true
  return (request) => {
    const entity = findEntity(request.entityId)
    const haystack = [entity?.name?.ar, entity?.name?.en, entity ? referenceOf(entity) : '']
    return haystack.some((text) =>
      String(text ?? '')
        .toLowerCase()
        .includes(term),
    )
  }
}

function summariesOf(items) {
  return items.map((request) => profileChangeSummary(request, findEntity(request.entityId)))
}

export const profileChangeRequestsHandlers = [
  http.get(
    '/api/v1/entities/me/profile-change',
    entityRoute(() => {
      const user = ownEntityActor(P.PROFILE_VIEW)
      const entity = ownEntityOf(user)
      const pending = pendingRequestOf(entity.id)
      return envelope(pending ? profileChangeDetail(pending, entity, user) : null)
    }),
  ),

  http.post(
    '/api/v1/entities/me/profile-change/cancel',
    entityRoute(() => {
      const { entity } = ownEditableEntity()
      cancelRequest(entity)
      return envelope(ownResource(entity))
    }),
  ),

  http.get(
    '/api/v1/entities/me/profile-change-requests',
    entityRoute(({ request }) => {
      const user = ownEntityActor(P.PROFILE_VIEW)
      const query = new URL(request.url).searchParams
      const { items, pagination } = paginate(requestsOf(ownEntityOf(user).id), query)
      return envelope(summariesOf(items), { pagination })
    }),
  ),

  http.get(
    ADMIN_PATH,
    entityRoute(({ request }) => {
      approver()
      const query = new URL(request.url).searchParams
      const entityId = query.get('filter[entityId]')
      const matching = listRequests({
        status: query.get('filter[status]') || S.PENDING,
        entityId: entityId ? Number(entityId) : null,
        matches: matcherOf(query.get('search')),
      })
      const { items, pagination } = paginate(matching, query)
      return envelope(summariesOf(items), { pagination })
    }),
  ),

  http.get(
    `${ADMIN_PATH}/:id`,
    entityRoute(({ params }) => {
      const user = approver()
      const { request, entity } = boundRequest(params.id)
      return envelope(profileChangeDetail(request, entity, user))
    }),
  ),

  http.post(
    `${ADMIN_PATH}/:id/decide`,
    entityRoute(async ({ params, request: httpRequest }) => {
      const user = approver()
      const { request, entity } = boundRequest(params.id)
      decideRequest(request, entity, await readJson(httpRequest), user)
      return envelope(profileChangeDetail(request, entity, user))
    }),
  ),
]
