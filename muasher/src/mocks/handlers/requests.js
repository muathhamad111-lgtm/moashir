import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { paginate } from '../contract/pagination'
import {
  assessmentReject,
  assessmentRoute,
  fieldMessage,
  readJson,
} from '../stores/assessments/assessmentRejection'
import { readsEveryAssessment, signedInActor } from '../stores/assessments/assessmentAccess'
import { findAssessment, isFrozen } from '../stores/assessments/assessmentsStore'
import { recordResource } from '../stores/assessments/selfSerializers'
import { normalizeSearchText } from '../stores/searchIndex'
import { findPayment } from '../stores/finance/financeState'
import {
  authorizeAssign,
  authorizeAssignedList,
  authorizeListAssessors,
  authorizeQueue,
  authorizeViewRequest,
} from '../stores/accredited/accreditedAccess'
import { reviewedRecords } from '../stores/accredited/accreditedRecords'
import { returnForCompletion, withdrawRequest } from '../stores/award/followerWork'
import { authorizeFollowAdvanced } from '../stores/award/cycleAccess'
import {
  assignedRow,
  completionResource,
  queueRow,
} from '../stores/accredited/accreditedSerializers'
import {
  preparingFiltersFrom,
  preparingRecords,
  queueFiltersFrom,
  queuePage,
  queuePathCounts,
  queueRecords,
  queueStats,
} from '../stores/accredited/requestQueue'
import { assignedPathCounts, assignedPathOf } from '../stores/accredited/assignedQueue'
import {
  lastActivityOf,
  progressSummaryOf,
  requestPaymentOf,
} from '../stores/accredited/requestPayments'
import { qualificationsOf, requestScopeOf } from '../stores/staffUsers'
import { sessionCan } from '../stores/sessionPermissions'
import { REVIEWED_PATHS } from '../stores/assessments/reviewedPaths'
import {
  assignAssessor,
  eligibleAssessors,
  reassignAssessor,
} from '../stores/accredited/assignments'
import { staffNameOf } from '../stores/accredited/staffDirectory'
import { runAccreditedSchedule } from '../stores/accredited/scheduler'

const REQUEST = '/api/v1/requests/:assessment'
const NUMERIC_ID = /^\d+$/
const TABS = {
  new: ['assigned'],
  in_progress: ['under-review', 'needs-completion', 'under-initial-assessment'],
  completed: ['completed', 'awaiting-filtering', 'qualified-for-committee', 'not-qualified'],
}
const REVIEWING = ['active', 'completed']
const QUEUE_VIEW = 'requests.queue.view'

function followedPaths(user) {
  return requestScopeOf(user.id, { superAdmin: user.superAdmin }).paths
}

function assertInScope(user, record) {
  if (user.superAdmin || !sessionCan(QUEUE_VIEW) || followedPaths(user).includes(record.path))
    return
  if (record.assignments.some((assignment) => assignment.assessorId === user.id)) return
  throw assessmentReject.notFound()
}

function scopedRecord(user, params) {
  const record = boundRecord(params)
  assertInScope(user, record)
  return record
}

function numericRoute(resolve) {
  const route = assessmentRoute(resolve)
  return (info) => (NUMERIC_ID.test(info.params.assessment) ? route(info) : undefined)
}

function boundRecord(params) {
  runAccreditedSchedule()
  const record = findAssessment(params.assessment)
  if (!record) throw assessmentReject.notFound()
  return record
}

function assignmentItem(assignment, revealsIdentities) {
  const item = {
    id: assignment.id,
    assessor: { id: assignment.assessorId, name: staffNameOf(assignment.assessorId) },
    status: assignment.status,
    assignedAt: assignment.createdAt,
    startedAt: assignment.startedAt,
    endedAt: assignment.endedAt,
  }
  if (!revealsIdentities) return item
  const assignedBy =
    assignment.assignedById === null
      ? null
      : { id: assignment.assignedById, name: staffNameOf(assignment.assignedById) }
  return { ...item, assignedBy, reason: assignment.reason, endReason: assignment.endReason }
}

function detailResource(record, viewer) {
  const reveals = readsEveryAssessment(viewer)
  const assignments = reveals
    ? record.assignments
    : record.assignments.filter((assignment) => assignment.assessorId === viewer.id)
  return {
    ...queueRow(record, staffNameOf),
    payment: {
      paid: findPayment(record.paymentId)?.status === 'paid',
      ...(reveals ? (requestPaymentOf(record) ?? {}) : {}),
    },
    record: recordResource(record, viewer),
    assignmentHistory: assignments.map((assignment) => assignmentItem(assignment, reveals)),
    completionRequests: record.completionRequests.map((completion) =>
      completionResource(completion, null),
    ),
  }
}

function preparingRow(record) {
  const row = queueRow(record, staffNameOf)
  return {
    id: row.id,
    number: row.number,
    path: row.path,
    pathLabel: row.pathLabel,
    status: row.status,
    isFrozen: row.isFrozen,
    entity: row.entity,
    awardCycle: row.awardCycle,
    openedAt: record.createdAt,
    payment: requestPaymentOf(record),
    progress: progressSummaryOf(record),
    lastActivityAt: lastActivityOf(record),
  }
}

function reviewedBy(user) {
  return reviewedRecords()
    .filter((record) => !isFrozen(record))
    .filter((record) =>
      record.assignments.some(
        (assignment) => assignment.assessorId === user.id && REVIEWING.includes(assignment.status),
      ),
    )
}

function checkAssignedFilters(query) {
  const keys = [...query.keys()].filter((key) => key.startsWith('filter['))
  const tab = query.get('filter[tab]')
  const path = query.get('filter[path]')
  const errors = {}
  if (keys.some((key) => !['filter[tab]', 'filter[path]'].includes(key)) || (tab && !TABS[tab]))
    errors['filter.tab'] = [fieldMessage('validation.invalid', {}, 'filter')]
  if (path && !REVIEWED_PATHS.includes(path))
    errors['filter.path'] = [fieldMessage('validation.invalid', {}, 'filter')]
  if (Object.keys(errors).length) throw assessmentReject.validation(errors)
  return tab
}

function assignedFor(records, tab, path) {
  return records
    .filter((record) => record.path === path)
    .filter((record) => !tab || TABS[tab].includes(record.status))
    .sort((a, b) => ((a.dueAt ?? '') < (b.dueAt ?? '') ? -1 : 1))
}

export const requestsHandlers = [
  http.get(
    '/api/v1/requests',
    assessmentRoute(({ request }) => {
      const user = signedInActor()
      authorizeQueue(user)
      runAccreditedSchedule()
      const query = new URL(request.url).searchParams
      const followed = followedPaths(user)
      const filters = queueFiltersFrom(query, followed)
      const records = queueRecords(filters, normalizeSearchText)
      const { items, pagination } = queuePage(records, query)
      return envelope(
        items.map((record) => queueRow(record, staffNameOf)),
        {
          pagination,
          stats: queueStats(records, filters),
          path: filters.paths[0],
          pathCounts: queuePathCounts(followed),
        },
      )
    }),
  ),

  http.get(
    '/api/v1/requests/preparing',
    assessmentRoute(({ request }) => {
      const user = signedInActor()
      authorizeQueue(user)
      runAccreditedSchedule()
      const query = new URL(request.url).searchParams
      const followed = followedPaths(user)
      const filters = preparingFiltersFrom(query, followed)
      const { items, pagination } = queuePage(preparingRecords(filters, normalizeSearchText), query)
      return envelope(items.map(preparingRow), {
        pagination,
        path: filters.paths[0],
        pathCounts: queuePathCounts(followed),
      })
    }),
  ),

  http.get(
    '/api/v1/requests/assigned',
    assessmentRoute(({ request }) => {
      const user = signedInActor()
      authorizeAssignedList(user)
      runAccreditedSchedule()
      const query = new URL(request.url).searchParams
      const tab = checkAssignedFilters(query)
      const records = reviewedBy(user)
      const pathCounts = assignedPathCounts(records, qualificationsOf(user.id), TABS)
      const path = assignedPathOf(query.get('filter[path]'), pathCounts)
      const { items, pagination } = paginate(assignedFor(records, tab, path), query)
      return envelope(
        items.map((record) => assignedRow(record, user)),
        { pagination, path, pathCounts },
      )
    }),
  ),

  http.get(
    '/api/v1/requests/assessors',
    assessmentRoute(({ request }) => {
      const user = signedInActor()
      authorizeListAssessors(user)
      return envelope(
        eligibleAssessors(user, new URL(request.url).searchParams, followedPaths(user)),
      )
    }),
  ),

  http.get(
    REQUEST,
    numericRoute(({ params }) => {
      const user = signedInActor()
      const record = scopedRecord(user, params)
      authorizeViewRequest(user, record)
      return envelope(detailResource(record, user))
    }),
  ),

  http.post(
    `${REQUEST}/assign`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = scopedRecord(user, params)
      authorizeAssign(user, record)
      return envelope(detailResource(assignAssessor(user, record, await readJson(request)), user))
    }),
  ),

  http.post(
    `${REQUEST}/return-for-completion`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = scopedRecord(user, params)
      authorizeFollowAdvanced(user, record)
      return envelope(
        detailResource(returnForCompletion(user, record, await readJson(request)), user),
      )
    }),
  ),

  http.post(
    `${REQUEST}/withdraw`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = scopedRecord(user, params)
      authorizeFollowAdvanced(user, record)
      return envelope(detailResource(withdrawRequest(user, record, await readJson(request)), user))
    }),
  ),

  http.post(
    `${REQUEST}/reassign`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = scopedRecord(user, params)
      authorizeAssign(user, record)
      return envelope(detailResource(reassignAssessor(user, record, await readJson(request)), user))
    }),
  ),
]
