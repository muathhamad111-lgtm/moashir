import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import { paginate } from '../contract/pagination'
import { assessmentReject, assessmentRoute, readJson } from '../stores/assessments/assessmentRejection'
import { signedInActor } from '../stores/assessments/assessmentAccess'
import { findAssessment } from '../stores/assessments/assessmentsStore'
import { assessmentResource } from '../stores/assessments/selfSerializers'
import { normalizeSearchText } from '../stores/searchIndex'
import { runAccreditedSchedule } from '../stores/accredited/scheduler'
import { findCycle, runningCycle } from '../stores/award/cycleStore'
import {
  authorizeAdvancedEntity,
  authorizeCreateCycle,
  authorizeDecideFiltering,
  authorizeDeleteDraft,
  authorizeFilteringRequest,
  authorizeManageCycle,
  authorizeReopenCycle,
  authorizeViewAnyCycles,
  authorizeViewCycle,
  authorizeViewFiltering,
} from '../stores/award/cycleAccess'
import { cycleDetailResource, cycleResource, publicCycleResource } from '../stores/award/cycleSerializers'
import { cycleDefaultsResource } from '../stores/award/cycleDefaults'
import { cycleFiltersFrom, cycleStats, listedCycles } from '../stores/award/cycleQueries'
import { createCycle, updateCycle } from '../stores/award/cycleEditing'
import { listCandidates, syncInitialAssessors, syncMembers } from '../stores/award/cycleStaff'
import { closeCycle, closeCycleSubmissions, openCycle, reopenCycle } from '../stores/award/cycleTransitions'
import { disqualifyRequest, filteringDetail, filteringQueue, filteringRow, qualifyRequest } from '../stores/award/filtering'
import { advancedCurrentResource, applyForAdvanced, deleteAdvancedDraft } from '../stores/award/advancedEntity'

const CYCLES = '/api/v1/award/cycles'
const CYCLE = `${CYCLES}/:cycle`
const FILTERING = `${CYCLE}/filtering/:assessment`
const NUMERIC_ID = /^\d+$/

function cycleRoute(resolve) {
  const route = assessmentRoute(resolve)
  return (info) => (NUMERIC_ID.test(info.params.cycle) && (!info.params.assessment || NUMERIC_ID.test(info.params.assessment)) ? route(info) : undefined)
}

function boundCycle(params) {
  runAccreditedSchedule()
  const cycle = findCycle(params.cycle)
  if (!cycle) throw assessmentReject.notFound()
  return cycle
}

function managedRoute(act) {
  return cycleRoute(async ({ params, request }) => {
    const user = signedInActor()
    const cycle = boundCycle(params)
    authorizeManageCycle(user, cycle)
    return envelope(cycleDetailResource(act(user, cycle, await readJson(request)), user))
  })
}

function decisionRoute(act) {
  return cycleRoute(async ({ params, request }) => {
    const user = signedInActor()
    const cycle = boundCycle(params)
    const record = findAssessment(params.assessment)
    authorizeDecideFiltering(user, cycle, record)
    return envelope(filteringRow(act(user, cycle, record, await readJson(request)), cycle))
  })
}

export const awardHandlers = [
  http.get(
    `${CYCLES}/current`,
    assessmentRoute(() => {
      runAccreditedSchedule()
      const cycle = runningCycle()
      return envelope(cycle ? publicCycleResource(cycle) : null)
    }),
  ),

  http.get(
    `${CYCLES}/default-conditions`,
    assessmentRoute(() => {
      authorizeCreateCycle(signedInActor())
      return envelope(cycleDefaultsResource())
    }),
  ),

  http.get(
    `${CYCLES}/candidates`,
    assessmentRoute(({ request }) => {
      authorizeCreateCycle(signedInActor())
      return envelope(listCandidates(new URL(request.url).searchParams))
    }),
  ),

  http.get(
    CYCLES,
    assessmentRoute(({ request }) => {
      const user = signedInActor()
      authorizeViewAnyCycles(user)
      runAccreditedSchedule()
      const query = new URL(request.url).searchParams
      const filters = cycleFiltersFrom(query)
      const { items, pagination } = paginate(listedCycles(user, filters, normalizeSearchText), query)
      return envelope(items.map((cycle) => cycleResource(cycle, { withCounts: true })), { pagination, stats: cycleStats(user, filters, normalizeSearchText) })
    }),
  ),

  http.post(
    CYCLES,
    assessmentRoute(async ({ request }) => {
      const user = signedInActor()
      authorizeCreateCycle(user)
      return envelope(cycleDetailResource(createCycle(user, await readJson(request)), user), {}, { status: 201 })
    }),
  ),

  http.get(
    CYCLE,
    cycleRoute(({ params }) => {
      const user = signedInActor()
      const cycle = boundCycle(params)
      authorizeViewCycle(user, cycle)
      return envelope(cycleDetailResource(cycle, user))
    }),
  ),

  http.put(CYCLE, managedRoute(updateCycle)),
  http.put(`${CYCLE}/members`, managedRoute(syncMembers)),
  http.put(`${CYCLE}/initial-assessors`, managedRoute(syncInitialAssessors)),
  http.post(`${CYCLE}/open`, managedRoute(openCycle)),
  http.post(`${CYCLE}/close-submissions`, managedRoute(closeCycleSubmissions)),
  http.post(`${CYCLE}/close`, managedRoute(closeCycle)),

  http.post(
    `${CYCLE}/reopen`,
    cycleRoute(async ({ params, request }) => {
      const user = signedInActor()
      const cycle = boundCycle(params)
      authorizeReopenCycle(user, cycle)
      return envelope(cycleDetailResource(reopenCycle(user, cycle, await readJson(request)), user))
    }),
  ),

  http.get(
    `${CYCLE}/filtering`,
    cycleRoute(({ params, request }) => {
      const user = signedInActor()
      const cycle = boundCycle(params)
      authorizeViewFiltering(user, cycle)
      const { items, meta } = filteringQueue(cycle, new URL(request.url).searchParams, normalizeSearchText)
      return envelope(items, meta)
    }),
  ),

  http.get(
    FILTERING,
    cycleRoute(({ params }) => {
      const user = signedInActor()
      const cycle = boundCycle(params)
      const record = findAssessment(params.assessment)
      authorizeFilteringRequest(user, cycle, record)
      return envelope(filteringDetail(record, cycle))
    }),
  ),

  http.post(`${FILTERING}/qualify`, decisionRoute(qualifyRequest)),
  http.post(`${FILTERING}/disqualify`, decisionRoute(disqualifyRequest)),

  http.get(
    '/api/v1/assessments/advanced/current',
    assessmentRoute(() => {
      const user = signedInActor()
      authorizeAdvancedEntity(user)
      runAccreditedSchedule()
      return envelope(advancedCurrentResource(user))
    }),
  ),

  http.post(
    '/api/v1/assessments/advanced',
    assessmentRoute(async ({ request }) => {
      const user = signedInActor()
      authorizeAdvancedEntity(user)
      runAccreditedSchedule()
      const { record, created } = applyForAdvanced(user, await readJson(request))
      return envelope(assessmentResource(record), {}, { status: created ? 201 : 200 })
    }),
  ),

  http.delete(
    '/api/v1/assessments/:assessment',
    assessmentRoute(async ({ params, request }) => {
      if (!NUMERIC_ID.test(params.assessment)) return undefined
      const user = signedInActor()
      runAccreditedSchedule()
      const record = findAssessment(params.assessment)
      if (!record) throw assessmentReject.notFound()
      authorizeDeleteDraft(user, record)
      deleteAdvancedDraft(user, record, await readJson(request))
      return noContent()
    }),
  ),
]
