import { listPathRows } from '../stores/periodic/pathRows'
import { periodConflictFor } from '../stores/periodic/periodConflict'
import { requestLocale } from '../contract/locale'
import { HttpResponse } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { CRITERIA_VERSION_STATUSES } from '@/constants/criteria'
import { findVersion } from '../stores/criteriaStore'
import { publishedTree, versionTree } from '../stores/criteriaSerializers'
import { reject } from '../stores/criteriaRejection'
import { CRITERIA_PERMISSIONS, actorWith, criteriaRoute, found } from './criteriaSupport'
import { failsRandomly } from '../contract/faults'
import {
  assessmentReject,
  assessmentRoute,
  readJson,
} from '../stores/assessments/assessmentRejection'
import {
  authorizeCreateSelf,
  authorizeManageSelf,
  authorizeView,
  authorizeViewAny,
  signedInActor,
} from '../stores/assessments/assessmentAccess'
import { findAssessment } from '../stores/assessments/assessmentsStore'
import { countAssessments, listFiltersFrom } from '../stores/assessments/assessmentQueries'
import {
  assessmentResource,
  formResource,
  recordResource,
  resultResource,
  scoresResource,
} from '../stores/assessments/selfSerializers'
import {
  saveSelfDraft,
  startSelfAssessment,
  submitSelfAssessment,
} from '../stores/assessments/selfWorkflow'
import '../stores/assessments/criteriaRebind'
import { ACCREDITED_PERMISSIONS, authorizeManageOwn } from '../stores/accredited/accreditedAccess'
import {
  accreditedFormResource,
  accreditedResultResource,
} from '../stores/accredited/accreditedSerializers'
import { submitReviewedRequest } from '../stores/accredited/entityWorkflow'
import { isReviewedPath } from '../stores/assessments/reviewedPaths'
import { runAccreditedSchedule } from '../stores/accredited/scheduler'

const ASSESSMENT = '/api/v1/assessments/:assessment'
const NUMERIC_ID = /^\d+$/

const failsToRead = failsRandomly(0.05)

function versionTreeFor(versionId) {
  actorWith(CRITERIA_PERMISSIONS.VIEW)
  const version = found(findVersion(versionId))
  if (version.status === CRITERIA_VERSION_STATUSES.DRAFT) throw reject.noPublished()
  return versionTree(version)
}

function requestedTree(request) {
  actorWith(CRITERIA_PERMISSIONS.VIEW_PUBLISHED)
  const versionId = new URL(request.url).searchParams.get('version')
  if (versionId) return versionTreeFor(versionId)
  const tree = publishedTree()
  if (!tree) throw reject.noPublished()
  return tree
}

function unavailable() {
  return HttpResponse.json(
    {
      data: null,
      meta: {},
      message: 'The service is temporarily unavailable.',
      code: 'service_unavailable',
      errors: {},
    },
    { status: 503 },
  )
}

function boundRecord(params) {
  runAccreditedSchedule()
  const record = findAssessment(params.assessment)
  if (!record) throw assessmentReject.notFound()
  return record
}

function numericRoute(resolve) {
  const route = assessmentRoute(resolve)
  return (info) => (NUMERIC_ID.test(info.params.assessment) ? route(info) : undefined)
}

function perPath(selfSerializer, reviewedSerializer) {
  return (record, user) =>
    isReviewedPath(record.path) ? reviewedSerializer(record, user) : selfSerializer(record, user)
}

function readRoute(serialize) {
  return numericRoute(({ params }) => {
    const user = signedInActor()
    const record = boundRecord(params)
    authorizeView(user, record)
    if (failsToRead()) return unavailable()
    return envelope(serialize(record, user))
  })
}

export const assessmentsHandlers = [
  http.get(
    '/api/v1/assessments/criteria',
    criteriaRoute(({ request }) => envelope(requestedTree(request))),
  ),

  http.get(
    '/api/v1/assessments',
    assessmentRoute(({ request }) => {
      const user = signedInActor()
      authorizeViewAny(user)
      const query = new URL(request.url).searchParams
      const filters = listFiltersFrom(query)
      runAccreditedSchedule()
      if (failsToRead()) return unavailable()
      const { items, pagination, missedPeriods } = listPathRows(user, filters, query)
      return envelope(items, {
        pagination,
        counts: countAssessments(user, filters),
        missedPeriods,
      })
    }),
  ),

  http.post(
    '/api/v1/assessments',
    assessmentRoute(async ({ request }) => {
      const user = signedInActor()
      authorizeCreateSelf(user)
      const record = startSelfAssessment(user, await readJson(request))
      return envelope(assessmentResource(record), {}, { status: 201 })
    }),
  ),

  http.get(ASSESSMENT, readRoute(recordResource)),

  http.get(`${ASSESSMENT}/form`, readRoute(perPath(formResource, accreditedFormResource))),

  http.get(`${ASSESSMENT}/result`, readRoute(perPath(resultResource, accreditedResultResource))),

  http.put(
    ASSESSMENT,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeManageSelf(user, record)
      return envelope(scoresResource(saveSelfDraft(user, record, await readJson(request))))
    }),
  ),

  http.post(
    `${ASSESSMENT}/submit`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      if (isReviewedPath(record.path)) {
        authorizeManageOwn(user, record, ACCREDITED_PERMISSIONS.ACCREDITED_MANAGE)
        const conflict = periodConflictFor(record, requestLocale(request))
        if (conflict) return conflict
        return envelope(
          assessmentResource(submitReviewedRequest(user, record, await readJson(request))),
        )
      }
      authorizeManageSelf(user, record)
      return envelope(resultResource(submitSelfAssessment(user, record, await readJson(request))))
    }),
  ),
]
