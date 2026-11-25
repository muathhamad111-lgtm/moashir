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
import { acceptEntityLogo } from '../stores/entityLogo'
import { acceptVatCertificate, vatCertificateFileOf } from '../stores/entityBilling'
import {
  acceptEntityDocument,
  acceptStaffLetter,
  changeKindOf,
  documentKeyOf,
  entityDocumentFileOf,
} from '../stores/entityDocuments'
import {
  mayHoldVatCertificate,
  proposeFile,
  proposeFileRemoval,
  proposeSection,
} from '../stores/profileChangeRequests'
import { findFile } from '../stores/finance/financeState'
import { signedDownloadOf } from '../stores/finance/signedFiles'
import { assertProfile, assertReason, contactFrom, profileFrom } from '../stores/entityValidation'
import { sessionCan } from '../stores/sessionPermissions'
import { entitiesCsv, exportFileName } from '../stores/entityCsv'
import { recordProfileChanges } from '../stores/profileChangesStore'
import { readFormOrJson } from '../stores/registrationForm'
import { ownEditableEntity, ownEntityActor, ownResource } from '../stores/ownEntity'
import { PROFILE_SECTION_KEYS } from '../stores/entityProfileSections'
import { assertFreshRevision, assertSection, snapshotOf } from '../stores/entitySectionSaves'

const ACCEPTED = 202

function staffResource(user, entity) {
  return entityResourceFor(accessOf(user))(entity, user)
}

async function saveSection({ params, request }) {
  if (!PROFILE_SECTION_KEYS.includes(params.section)) throw entityReject.notFound()
  const { user, entity } = ownEditableEntity()
  const body = await readJson(request)
  assertSection(params.section, body, entity)
  assertFreshRevision(params.section, body, entity)
  proposeSection(entity, params.section, body, user)
  return envelope(ownResource(entity), { section: params.section }, { status: ACCEPTED })
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

async function saveStaffEdit(params, body) {
  const user = manager()
  const entity = viewableEntity(user, params.id)
  assertProfile(body, entity.id)
  assertReason(body)
  if (isDeleted(entity)) throw entityReject.deleted()
  const letter = await acceptStaffLetter(entity, body)
  const before = snapshotOf(entity)
  applyProfile(entity, profileFrom(body), contactFrom(body))
  applyUnifiedNumber(entity, body)
  if (letter) entity.authorizationLetter = letter
  recordProfileChanges(entity, before, entity, { actor: user, isStaff: true })
  return envelope(staffResource(user, entity))
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
      proposeFile(entity, 'logo', await acceptEntityLogo(user, form.get('file')), user)
      return envelope(ownResource(entity), {}, { status: ACCEPTED })
    }),
  ),

  http.delete(
    '/api/v1/entities/me/logo',
    entityRoute(() => {
      const { user, entity } = ownEditableEntity()
      proposeFileRemoval(entity, 'logo', user)
      return envelope(ownResource(entity), {}, { status: ACCEPTED })
    }),
  ),

  http.post(
    '/api/v1/entities/me/vat-certificate',
    entityRoute(async ({ request }) => {
      const { user, entity } = ownEditableEntity()
      if (!mayHoldVatCertificate(entity)) throw entityReject.vatNotRegistered()
      const form = await request.formData()
      proposeFile(entity, 'vat_certificate', await acceptVatCertificate(form.get('file')), user)
      return envelope(ownResource(entity), {}, { status: ACCEPTED })
    }),
  ),

  http.delete(
    '/api/v1/entities/me/vat-certificate',
    entityRoute(() => {
      const { user, entity } = ownEditableEntity()
      proposeFileRemoval(entity, 'vat_certificate', user)
      return envelope(ownResource(entity), {}, { status: ACCEPTED })
    }),
  ),

  http.post(
    '/api/v1/entities/me/vat-certificate/download',
    entityRoute(() => {
      const user = ownEntityActor(P.PROFILE_VIEW)
      const file = vatCertificateFileOf(ownEntityOf(user), findFile)
      return envelope(signedDownloadOf(user, file))
    }),
  ),

  http.post(
    '/api/v1/entities/me/documents/:document',
    entityRoute(async ({ params, request }) => {
      const key = documentKeyOf(params.document, { uploadable: true })
      const { user, entity } = ownEditableEntity()
      const form = await request.formData()
      proposeFile(
        entity,
        changeKindOf(key),
        await acceptEntityDocument(key, form.get('file')),
        user,
      )
      return envelope(ownResource(entity), {}, { status: ACCEPTED })
    }),
  ),

  http.post(
    '/api/v1/entities/me/documents/:document/download',
    entityRoute(({ params }) => {
      const user = ownEntityActor(P.PROFILE_VIEW)
      const file = entityDocumentFileOf(ownEntityOf(user), documentKeyOf(params.document))
      return envelope(signedDownloadOf(user, file))
    }),
  ),

  http.post(
    '/api/v1/entities/:id/documents/:document/download',
    entityRoute(({ params }) => {
      const user = actorWith(P.MANAGE)
      const entity = viewableEntity(user, params.id)
      return envelope(
        signedDownloadOf(user, entityDocumentFileOf(entity, documentKeyOf(params.document))),
      )
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
    entityRoute(async ({ params, request }) =>
      saveStaffEdit(params, await readFormOrJson(request)),
    ),
  ),

  // The staff edit with a new authorization letter: multipart, sent as a POST that names the PUT method.
  http.post(
    '/api/v1/entities/:id',
    entityRoute(async ({ params, request }) => {
      const body = await readFormOrJson(request)
      if (body._method !== 'PUT') throw entityReject.notFound()
      return saveStaffEdit(params, body)
    }),
  ),
]
