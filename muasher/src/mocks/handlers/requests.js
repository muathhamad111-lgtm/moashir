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
  queueFiltersFrom,
  queuePage,
  queueRecords,
  queueStats,
} from '../stores/accredited/requestQueue'
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
    payment: { paid: findPayment(record.paymentId)?.status === 'paid' },
    record: recordResource(record, viewer),
    assignmentHistory: assignments.map((assignment) => assignmentItem(assignment, reveals)),
    completionRequests: record.completionRequests.map((completion) =>
      completionResource(completion, null),
    ),
  }
}

function assignedFor(user, query) {
  const keys = [...query.keys()].filter((key) => key.startsWith('filter['))
  const tab = query.get('filter[tab]')
  if (keys.some((key) => key !== 'filter[tab]') || (tab && !TABS[tab])) {
    throw assessmentReject.validation({
      'filter.tab': [fieldMessage('validation.invalid', {}, 'filter')],
    })
  }
  return reviewedRecords()
    .filter((record) => !isFrozen(record))
    .filter((record) =>
      record.assignments.some(
        (assignment) => assignment.assessorId === user.id && REVIEWING.includes(assignment.status),
      ),
    )
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
      const filters = queueFiltersFrom(query)
      const records = queueRecords(filters, normalizeSearchText)
      const { items, pagination } = queuePage(records, query)
      return envelope(
        items.map((record) => queueRow(record, staffNameOf)),
        { pagination, stats: queueStats(records, filters) },
      )
    }),
  ),

  http.get(
    '/api/v1/requests/assigned',
    assessmentRoute(({ request }) => {
      const user = signedInActor()
      authorizeAssignedList(user)
      runAccreditedSchedule()
      const query = new URL(request.url).searchParams
      const { items, pagination } = paginate(assignedFor(user, query), query)
      return envelope(
        items.map((record) => assignedRow(record, user)),
        { pagination },
      )
    }),
  ),

  http.get(
    '/api/v1/requests/assessors',
    assessmentRoute(({ request }) => {
      const user = signedInActor()
      authorizeListAssessors(user)
      return envelope(eligibleAssessors(user, new URL(request.url).searchParams))
    }),
  ),

  http.get(
    REQUEST,
    numericRoute(({ params }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeViewRequest(user, record)
      return envelope(detailResource(record, user))
    }),
  ),

  http.post(
    `${REQUEST}/assign`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeAssign(user, record)
      return envelope(detailResource(assignAssessor(user, record, await readJson(request)), user))
    }),
  ),

  http.post(
    `${REQUEST}/return-for-completion`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
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
      const record = boundRecord(params)
      authorizeFollowAdvanced(user, record)
      return envelope(detailResource(withdrawRequest(user, record, await readJson(request)), user))
    }),
  ),

  http.post(
    `${REQUEST}/reassign`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeAssign(user, record)
      return envelope(detailResource(reassignAssessor(user, record, await readJson(request)), user))
    }),
  ),
]
