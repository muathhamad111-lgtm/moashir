import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { paginate } from '../contract/pagination'
import { PRIVACY_REQUEST_KINDS, PRIVACY_REQUEST_STATUSES as S } from '@/constants/entityEnums'
import { actorWith, entityReject, entityRoute, readJson } from '../stores/entityRejection'
import { ENTITY_PERMISSIONS as P } from '../stores/entityAccess'
import { privacyRequestResource } from '../stores/entitySerializers'
import { assertReason, optionalReason, rejectField } from '../stores/entityValidation'
import { allEntities, findEntity, isDeleted, referenceOf } from '../stores/entitiesStore'
import { normalizeSearchText } from '../stores/searchIndex'
import {
  createPrivacyRequest,
  decidePrivacyRequest,
  findPrivacyRequest,
  listPrivacyRequests,
} from '../stores/privacyRequestsStore'

const PATH = '/api/v1/privacy/requests'
const DETAILS_MIN = 10
const LOOKUP_LIMIT = { DEFAULT: 10, MAX: 20 }
const SEARCH_MAX = 100

function lookupLimit(raw) {
  if (raw === null) return LOOKUP_LIMIT.DEFAULT
  const limit = Number(raw)
  if (!Number.isInteger(limit) || limit < 1 || limit > LOOKUP_LIMIT.MAX)
    rejectField('limit', 'field.invalid_choice')
  return limit
}

function lookupMatches(entity, needle) {
  if (!needle) return true
  return [entity.name.ar, entity.name.en, entity.owner.email, referenceOf(entity)].some((value) =>
    normalizeSearchText(value ?? '').includes(needle),
  )
}

function lookupResource(entity) {
  return {
    id: entity.id,
    reference: referenceOf(entity),
    name: { ar: entity.name.ar, en: entity.name.en ?? null },
    isDeleted: isDeleted(entity),
  }
}

function lookupEntities(query) {
  const search = query.get('search') ?? ''
  if (search.length > SEARCH_MAX) rejectField('search', 'field.invalid_choice')
  const limit = lookupLimit(query.get('limit'))
  const needle = normalizeSearchText(search)
  const items = allEntities()
    .filter((entity) => lookupMatches(entity, needle))
    .sort((left, right) => left.name.ar.localeCompare(right.name.ar, 'ar'))
    .slice(0, limit)
  return { items, limit }
}

function boundRequest(id) {
  const request = findPrivacyRequest(id)
  if (!request) throw entityReject.notFound()
  return request
}

function assertNewRequest(body) {
  if (!findEntity(body.entityId)) rejectField('entityId', 'field.invalid_choice')
  if (!PRIVACY_REQUEST_KINDS.includes(body.kind)) rejectField('kind', 'field.invalid_choice')
  return {
    entityId: body.entityId,
    kind: body.kind,
    details: assertReason(body, DETAILS_MIN, 'details'),
  }
}

export const privacyRequestsHandlers = [
  http.get(
    '/api/v1/privacy/entities',
    entityRoute(({ request }) => {
      actorWith(P.PRIVACY_MANAGE)
      const { items, limit } = lookupEntities(new URL(request.url).searchParams)
      return envelope(items.map(lookupResource), { limit })
    }),
  ),

  http.get(
    PATH,
    entityRoute(({ request }) => {
      actorWith(P.PRIVACY_MANAGE)
      const query = new URL(request.url).searchParams
      const filters = {
        status: query.get('filter[status]'),
        kind: query.get('filter[kind]'),
        entityId: query.get('filter[entityId]'),
      }
      const { items, pagination } = paginate(listPrivacyRequests(filters), query)
      return envelope(items.map(privacyRequestResource), { pagination })
    }),
  ),

  http.post(
    PATH,
    entityRoute(async ({ request }) => {
      const actor = actorWith(P.PRIVACY_MANAGE)
      const created = createPrivacyRequest(actor, assertNewRequest(await readJson(request)))
      return envelope(privacyRequestResource(created), {}, { status: 201 })
    }),
  ),

  http.get(
    `${PATH}/:id`,
    entityRoute(({ params }) => {
      actorWith(P.PRIVACY_MANAGE)
      return envelope(privacyRequestResource(boundRequest(params.id)))
    }),
  ),

  http.post(
    `${PATH}/:id/fulfil`,
    entityRoute(async ({ params, request }) => {
      const actor = actorWith(P.PRIVACY_MANAGE)
      const record = boundRequest(params.id)
      const note = optionalReason(await readJson(request))
      return envelope(
        privacyRequestResource(decidePrivacyRequest(record, actor, S.FULFILLED, note)),
      )
    }),
  ),

  http.post(
    `${PATH}/:id/reject`,
    entityRoute(async ({ params, request }) => {
      const actor = actorWith(P.PRIVACY_MANAGE)
      const record = boundRequest(params.id)
      const reason = assertReason(await readJson(request))
      return envelope(
        privacyRequestResource(decidePrivacyRequest(record, actor, S.REJECTED, reason)),
      )
    }),
  ),
]
