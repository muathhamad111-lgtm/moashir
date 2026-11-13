import { HttpResponse } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { requestLocale } from '../contract/locale'
import { paginate } from '../contract/pagination'
import {
  actorWith,
  entityReject,
  entityRoute,
  fieldError,
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
import { ENTITY_ACCESS, entityResourceFor } from '../stores/entitySerializers'
import { entityStats, filtersFrom, queryEntities } from '../stores/entityQueries'
import { LEVEL_FILTERS } from '../stores/entityLevels'
import { applyProfile, isDeleted } from '../stores/entitiesStore'
import { applyUnifiedNumber } from '../stores/entityUnifiedNumber'
import { removeEntityLogo, storeEntityLogo } from '../stores/entityLogo'
import { assertProfile, assertReason, contactFrom, profileFrom } from '../stores/entityValidation'
import { sessionCan } from '../stores/sessionPermissions'
import { entitiesCsv, exportFileName } from '../stores/entityCsv'
import { recordProfileChanges } from '../stores/profileChangesStore'
import { ownEditableEntity, ownEntityActor, ownResource } from '../stores/ownEntity'
import { PROFILE_SECTION_KEYS } from '../stores/entityProfileSections'
import {
  applySection,
  assertFreshRevision,
  assertSection,
  snapshotOf,
} from '../stores/entitySectionSaves'

function staffResource(user, entity) {
  return entityResourceFor(accessOf(user))(entity, user)
}

async function saveSection({ params, request }) {
  if (!PROFILE_SECTION_KEYS.includes(params.section)) throw entityReject.notFound()
  const { user, entity } = ownEditableEntity()
  const body = await readJson(request)
  assertSection(params.section, body, entity)
  assertFreshRevision(params.section, body, entity)
  const before = snapshotOf(entity)
  applySection(params.section, entity, body)
  const changedFields = recordProfileChanges(entity, before, entity, {
    actor: user,
    isStaff: false,
  })
  return envelope(ownResource(entity), { section: params.section, changedFields })
}

function listFilters(request) {
  const filters = filtersFrom(new URL(request.url).searchParams)
  if (filters.trashed && !sessionCan(P.DELETE)) throw entityReject.forbidden()
  if (filters.level !== null && !LEVEL_FILTERS.includes(filters.level))
    throw entityReject.validation({ 'filter.level': [fieldError('filter.level_invalid')] })
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

  http.patch('/api/v1/entities/me/profile/:section', entityRoute(saveSection)),

  http.post(
    '/api/v1/entities/me/logo',
    entityRoute(async ({ request }) => {
      const { user, entity } = ownEditableEntity()
      const form = await request.formData()
      await storeEntityLogo(user, entity, form.get('file'))
      return envelope(ownResource(entity), {}, { status: 201 })
    }),
  ),

  http.delete(
    '/api/v1/entities/me/logo',
    entityRoute(() => {
      const { entity } = ownEditableEntity()
      return envelope(ownResource(removeEntityLogo(entity)))
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
      const before = snapshotOf(entity)
      applyProfile(entity, profileFrom(body), contactFrom(body))
      applyUnifiedNumber(entity, body)
      recordProfileChanges(entity, before, entity, { actor: user, isStaff: true })
      return envelope(staffResource(user, entity))
    }),
  ),
]
