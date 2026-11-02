import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import { criteriaProblemMessages } from '../fixtures/criteriaMessages'
import { findVersion, liveVersions } from '../stores/criteriaStore'
import { consistencyProblems } from '../stores/criteriaRules'
import { versionResource } from '../stores/criteriaSerializers'
import { createDraft, discardDraft } from '../stores/criteriaVersions'
import { correctTexts, publishVersion } from '../stores/criteriaPublishing'
import { interpolate, requestLocale } from '../stores/criteriaRejection'
import {
  CRITERIA_PERMISSIONS as CAN,
  actorWith,
  criteriaRoute,
  found,
  paginate,
  readBody,
} from './criteriaSupport'

const BASE = '/api/v1/criteria/versions'

function withMessage(entry, locale) {
  const template = criteriaProblemMessages[entry.problem][locale]
  return {
    ...entry,
    message: interpolate(template, {
      code: entry.code ?? '',
      expected: entry.expectedWeight ?? '',
      actual: entry.actualWeight ?? '',
    }),
  }
}

export const criteriaVersionHandlers = [
  http.get(
    BASE,
    criteriaRoute(({ request }) => {
      actorWith(CAN.VIEW)
      const query = new URL(request.url).searchParams
      const status = query.get('filter[status]')
      const versions = liveVersions().filter((version) => !status || version.status === status)
      const page = paginate(versions, query)
      return envelope(page.items.map(versionResource), { pagination: page.pagination })
    }),
  ),

  http.post(
    BASE,
    criteriaRoute(async ({ request }) => {
      actorWith(CAN.MANAGE)
      const body = await readBody(request)
      const draft = createDraft({
        cloneFromVersionId: body.cloneFromVersionId ?? null,
        label: body.label ?? null,
      })
      return envelope(versionResource(draft), {}, { status: 201 })
    }),
  ),

  http.get(
    `${BASE}/:versionId`,
    criteriaRoute(({ params }) => {
      actorWith(CAN.VIEW)
      return envelope(versionResource(found(findVersion(params.versionId))))
    }),
  ),

  http.delete(
    `${BASE}/:versionId`,
    criteriaRoute(({ params }) => {
      actorWith(CAN.MANAGE)
      discardDraft(found(findVersion(params.versionId)))
      return noContent()
    }),
  ),

  http.get(
    `${BASE}/:versionId/check`,
    criteriaRoute(({ request, params }) => {
      actorWith(CAN.VIEW)
      const version = found(findVersion(params.versionId))
      const locale = requestLocale(request)
      const problems = consistencyProblems(version.id).map((entry) => withMessage(entry, locale))
      return envelope({ consistent: problems.length === 0, problems })
    }),
  ),

  http.post(
    `${BASE}/:versionId/publish`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.PUBLISH)
      const version = found(findVersion(params.versionId))
      const outcome = publishVersion(actor, version, await readBody(request))
      return envelope(versionResource(outcome.version), outcome.meta)
    }),
  ),

  http.patch(
    `${BASE}/:versionId/labels`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.MANAGE)
      const version = found(findVersion(params.versionId))
      const outcome = correctTexts(actor, version, await readBody(request))
      return envelope(versionResource(outcome.version), outcome.meta)
    }),
  ),
]
