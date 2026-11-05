import { HttpResponse } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { requestLocale } from '../contract/locale'
import { paginate } from '../contract/pagination'
import {
  actorWith,
  entityReject,
  entityRoute,
  readJson,
  signedInUser,
} from '../stores/entityRejection'
import {
  ENTITY_PERMISSIONS as P,
  accessOf,
  hasFullAccess,
  ownEntityOf,
  viewableEntity,
} from '../stores/entityAccess'
import { ENTITY_ACCESS, entityResourceFor, ownEntityResource } from '../stores/entitySerializers'
import { entityStats, filtersFrom, queryEntities } from '../stores/entityQueries'
import { applyProfile, isDeleted, setMarketingConsent } from '../stores/entitiesStore'
import { applyRegistry } from '../stores/entityRegistry'
import { applyUnifiedNumber } from '../stores/entityUnifiedNumber'
import { removeEntityLogo, storeEntityLogo } from '../stores/entityLogo'
import {
  assertOwnProfile,
  assertProfile,
  assertReason,
  contactFrom,
  profileFrom,
} from '../stores/entityValidation'
import { awaitingChangeOf } from '../stores/entityEmailChanges'
import { sessionCan } from '../stores/sessionPermissions'
import { entitiesCsv, exportFileName } from '../stores/entityCsv'
import { recordProfileChanges } from '../stores/profileChangesStore'

function ownEntityActor(permission) {
  const user = signedInUser()
  if (!user.entityId || !sessionCan(permission)) throw entityReject.forbidden()
  return user
}

function ownResource(entity) {
  return ownEntityResource(entity, awaitingChangeOf(entity.id))
}

function staffResource(user, entity) {
  return entityResourceFor(accessOf(user))(entity, user)
}

function listFilters(request) {
  const filters = filtersFrom(new URL(request.url).searchParams)
  if (filters.trashed && !sessionCan(P.DELETE)) throw entityReject.forbidden()
  return filters
}

function manager() {
  const user = actorWith(P.MANAGE)
  if (!hasFullAccess(user)) throw entityReject.forbidden()
  return user
}

function visibleTo(user, filters) {
  return accessOf(user) === ENTITY_ACCESS.NONE ? [] : queryEntities(filters)
}

function statsFor(user, filters) {
  return accessOf(user) === ENTITY_ACCESS.NONE ? { total: 0 } : entityStats(filters)
}

export const entitiesHandlers = [
  http.get(
    '/api/v1/entities/me',
    entityRoute(() => {
      const user = ownEntityActor(P.PROFILE_VIEW)
      return envelope(ownResource(ownEntityOf(user)))
    }),
  ),

  http.put(
    '/api/v1/entities/me',
    entityRoute(async ({ request }) => {
      const user = ownEntityActor(P.PROFILE_EDIT)
      const entity = ownEntityOf(user)
      const body = await readJson(request)
      assertOwnProfile(body, entity.id)
      const before = { ...entity, name: { ...entity.name } }
      applyProfile(entity, profileFrom(body), contactFrom(body))
      applyRegistry(entity, body)
      applyUnifiedNumber(entity, body)
      recordProfileChanges(entity, before, entity, { actor: user, isStaff: false })
      setMarketingConsent(entity, body.marketingConsent)
      return envelope(ownResource(entity))
    }),
  ),

  http.post(
    '/api/v1/entities/me/logo',
    entityRoute(async ({ request }) => {
      const user = ownEntityActor(P.PROFILE_EDIT)
      const entity = ownEntityOf(user)
      const form = await request.formData()
      await storeEntityLogo(user, entity, form.get('file'))
      return envelope(ownResource(entity), {}, { status: 201 })
    }),
  ),

  http.delete(
    '/api/v1/entities/me/logo',
    entityRoute(() => {
      const user = ownEntityActor(P.PROFILE_EDIT)
      const entity = removeEntityLogo(ownEntityOf(user))
      return envelope(ownResource(entity))
    }),
  ),

  http.get(
    '/api/v1/entities',
    entityRoute(({ request }) => {
      const user = actorWith(P.VIEW)
      const filters = listFilters(request)
      const { items, pagination } = paginate(
        visibleTo(user, filters),
        new URL(request.url).searchParams,
      )
      const stats = statsFor(user, filters)
      return envelope(
        items.map((entity) => staffResource(user, entity)),
        { pagination, stats },
      )
    }),
  ),

  http.post(
    '/api/v1/entities/export',
    entityRoute(({ request }) => {
      const user = actorWith(P.EXPORT)
      if (!hasFullAccess(user)) throw entityReject.forbidden()
      const rows = queryEntities(listFilters(request))
      return new HttpResponse(entitiesCsv(rows, requestLocale(request)), {
        headers: {
          'Content-Type': 'text/csv; charset=UTF-8',
          'Content-Disposition': `attachment; filename=${exportFileName()}`,
          'X-Export-Row-Count': String(rows.length),
        },
      })
    }),
  ),

  http.get(
    '/api/v1/entities/:id',
    entityRoute(({ params }) => {
      const user = signedInUser()
      return envelope(staffResource(user, viewableEntity(user, params.id)))
    }),
  ),

  http.put(
    '/api/v1/entities/:id',
    entityRoute(async ({ params, request }) => {
      const user = manager()
      const entity = viewableEntity(user, params.id)
      const body = await readJson(request)
      assertProfile(body, entity.id)
      assertReason(body)
      if (isDeleted(entity)) throw entityReject.deleted()
      const before = { ...entity, name: { ...entity.name } }
      applyProfile(entity, profileFrom(body), contactFrom(body))
      applyUnifiedNumber(entity, body)
      recordProfileChanges(entity, before, entity, { actor: user, isStaff: true })
      return envelope(staffResource(user, entity))
    }),
  ),
]
