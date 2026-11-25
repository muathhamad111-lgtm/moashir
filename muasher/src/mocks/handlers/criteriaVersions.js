import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import { criteriaProblemMessages } from '../fixtures/criteriaMessages'
import { editEvents, findVersion, liveVersions } from '../stores/criteriaStore'
import { consistencyProblems } from '../stores/criteriaRules'
import { versionResource, workspaceTree } from '../stores/criteriaSerializers'
import { createDraft, discardDraft } from '../stores/criteriaVersions'
import { publicationPreview } from '../stores/criteriaPreview'
import { correctTexts, publishVersion } from '../stores/criteriaPublishing'
import { interpolate, reject, requestLocale } from '../stores/criteriaRejection'
import { cycleBindingsSummary, moveDraftCycles } from '../stores/award/cycleBindings'
import { allAssessments } from '../stores/assessments/assessmentsStore'
import { openAssessmentsBoundTo } from '../stores/criteriaEditHooks'
import {
  CRITERIA_PERMISSIONS as CAN,
  actorWith,
  criteriaRoute,
  found,
  paginate,
  readBody,
} from './criteriaSupport'

const BASE = '/api/v1/criteria/versions'

function assertNoDraftCyclesChoice(body) {
  if (Object.hasOwn(body, 'draftCycles')) throw reject.draftCyclesFollowPublished()
}

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

// The list row: the version and how many requests it scores (`CriteriaVersionSummaryResource`).
function versionSummaryResource(version) {
  return {
    ...versionResource(version),
    boundAssessments: allAssessments().filter((record) => record.criteriaVersionId === version.id)
      .length,
    boundOpenAssessments: openAssessmentsBoundTo(version.id),
  }
}

function versionRef(versionId) {
  const version = findVersion(versionId)
  return version
    ? { id: version.id, versionNumber: version.versionNumber, label: version.label ?? null }
    : null
}

function editEventResource(event) {
  return {
    id: event.id,
    kind: event.kind,
    scope: event.scope,
    fromVersion: versionRef(event.fromVersionId),
    toVersion: event.toVersionId ? versionRef(event.toVersionId) : null,
    changeCount: event.changeCount ?? 0,
    scoreAffectingCount: event.rescoredCodes?.length ?? 0,
    changes: event.changes ?? [],
    affectedOpenCount: event.affectedOpenCount ?? 0,
    reason: event.reason ?? null,
    editedBy: { id: event.actorId, name: null },
    createdAt: event.createdAt,
  }
}

export const criteriaVersionHandlers = [
  http.get(
    '/api/v1/criteria/edit-events',
    criteriaRoute(({ request }) => {
      actorWith(CAN.VIEW)
      const query = new URL(request.url).searchParams
      const page = paginate([...editEvents()].reverse(), query)
      return envelope(page.items.map(editEventResource), { pagination: page.pagination })
    }),
  ),

  http.get(
    BASE,
    criteriaRoute(({ request }) => {
      actorWith(CAN.VIEW)
      const query = new URL(request.url).searchParams
      const status = query.get('filter[status]')
      const versions = liveVersions().filter((version) => !status || version.status === status)
      const page = paginate(versions, query)
      return envelope(page.items.map(versionSummaryResource), { pagination: page.pagination })
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
    `${BASE}/:versionId/tree`,
    criteriaRoute(({ params }) => {
      actorWith(CAN.VIEW)
      return envelope(workspaceTree(found(findVersion(params.versionId))))
    }),
  ),

  http.get(
    `${BASE}/:versionId/check`,
    criteriaRoute(({ request, params }) => {
      actorWith(CAN.VIEW)
      const version = found(findVersion(params.versionId))
      const locale = requestLocale(request)
      const problems = consistencyProblems(version.id).map((entry) => withMessage(entry, locale))
      return envelope(
        { consistent: problems.length === 0, problems },
        { awardCycles: cycleBindingsSummary() },
      )
    }),
  ),

  http.get(
    `${BASE}/:versionId/changes`,
    criteriaRoute(({ params }) => {
      actorWith(CAN.VIEW)
      return envelope(publicationPreview(found(findVersion(params.versionId))))
    }),
  ),

  http.post(
    `${BASE}/:versionId/publish`,
    criteriaRoute(async ({ request, params }) => {
      const actor = actorWith(CAN.PUBLISH)
      const version = found(findVersion(params.versionId))
      const body = await readBody(request)
      assertNoDraftCyclesChoice(body)
      const outcome = publishVersion(actor, version, body)
      const movedCycleIds = moveDraftCycles(outcome.version.id)
      return envelope(versionResource(outcome.version), {
        ...outcome.meta,
        awardCycles: { ...cycleBindingsSummary(), movedCycleIds },
      })
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
