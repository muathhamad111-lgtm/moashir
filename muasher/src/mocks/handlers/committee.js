import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import {
  assessmentReject,
  assessmentRoute,
  readJson,
} from '../stores/assessments/assessmentRejection'
import { signedInActor } from '../stores/assessments/assessmentAccess'
import { findAssessment } from '../stores/assessments/assessmentsStore'
import { normalizeSearchText } from '../stores/searchIndex'
import { runAccreditedSchedule } from '../stores/accredited/scheduler'
import { sessionCan } from '../stores/sessionPermissions'
import { authorizeManageCycle } from '../stores/award/cycleAccess'
import { cycleDetailResource } from '../stores/award/cycleSerializers'
import { findAssignment, findSeat } from '../stores/committee/committeeState'
import {
  COMMITTEE_PERMISSIONS,
  authorizeManageRoster,
  authorizeOwnWork,
  authorizeViewRoster,
} from '../stores/committee/committeeAccess'
import { startCommittee } from '../stores/committee/committeeLifecycle'
import {
  appointMember,
  committeeCandidates,
  disableMember,
  excludeMember,
  removeMember,
  rosterOf,
  substituteMember,
} from '../stores/committee/committeeRoster'
import {
  authorizeOwnRow,
  evaluationMode,
  latestReturnNote,
  myRows,
  saveCommitteeNotes,
  saveCommitteeScores,
  submitEvaluation,
} from '../stores/committee/committeeWork'
import {
  assignmentRowResource,
  evaluationResource,
  memberResource,
} from '../stores/committee/committeeSerializers'
import {
  memberDiscussionResource,
  myDiscussions,
  respondToDiscussion,
} from '../stores/committee/committeeDiscussions'
import { optionalReason } from '../stores/accredited/accreditedValidation'
import { boundCycle, cycleRoute, queryOf } from './awardRouting'

const CYCLE = '/api/v1/award/cycles/:cycle'
const MEMBER = `${CYCLE}/committee/members/:member`
const ASSIGNMENT = '/api/v1/committee/me/assignments/:assignment'

function boundSeat(cycle, params) {
  const seat = findSeat(params.member)
  if (!seat || seat.cycleId !== cycle.id) throw assessmentReject.notFound()
  return seat
}

function memberRoute(act, status = 200) {
  return cycleRoute(async ({ params, request }) => {
    const user = signedInActor()
    const cycle = boundCycle(params)
    authorizeManageRoster(user, cycle)
    const seat = boundSeat(cycle, params)
    const outcome = act(user, cycle, seat, await readJson(request))
    if (outcome?.meta) return envelope(memberResource(outcome.seat), outcome.meta)
    return envelope(memberResource(outcome), {}, { status })
  })
}

function boundRow(user, params) {
  runAccreditedSchedule()
  const row = findAssignment(params.assignment)
  authorizeOwnRow(user, row)
  return row
}

function workRoute(act) {
  return cycleRoute(async ({ params, request }) => {
    const user = signedInActor()
    const row = boundRow(user, params)
    if (!user.superAdmin && !sessionCan(COMMITTEE_PERMISSIONS.SCORE))
      throw assessmentReject.forbidden()
    return envelope(act(user, row, await readJson(request)))
  })
}

export const committeeHandlers = [
  http.get(
    `${CYCLE}/committee`,
    cycleRoute(({ params }) => {
      const user = signedInActor()
      const cycle = boundCycle(params)
      authorizeViewRoster(user, cycle)
      return envelope(rosterOf(cycle).map(memberResource))
    }),
  ),

  http.get(
    `${CYCLE}/committee/candidates`,
    cycleRoute(({ params, request }) => {
      const user = signedInActor()
      const cycle = boundCycle(params)
      authorizeManageRoster(user, cycle)
      return envelope(
        committeeCandidates(cycle, queryOf(request).get('search'), normalizeSearchText),
      )
    }),
  ),

  http.post(
    `${CYCLE}/committee/members`,
    cycleRoute(async ({ params, request }) => {
      const user = signedInActor()
      const cycle = boundCycle(params)
      authorizeManageRoster(user, cycle)
      return envelope(
        memberResource(appointMember(user, cycle, await readJson(request))),
        {},
        { status: 201 },
      )
    }),
  ),

  http.delete(
    MEMBER,
    cycleRoute(async ({ params, request }) => {
      const user = signedInActor()
      const cycle = boundCycle(params)
      authorizeManageRoster(user, cycle)
      removeMember(user, cycle, boundSeat(cycle, params), await readJson(request))
      return noContent()
    }),
  ),

  http.post(`${MEMBER}/disable`, memberRoute(disableMember)),
  http.post(`${MEMBER}/exclude`, memberRoute(excludeMember)),
  http.post(`${MEMBER}/substitute`, memberRoute(substituteMember, 201)),

  http.post(
    `${CYCLE}/start-committee`,
    cycleRoute(async ({ params, request }) => {
      const user = signedInActor()
      const cycle = boundCycle(params)
      authorizeManageCycle(user, cycle)
      return envelope(
        cycleDetailResource(
          startCommittee(user, cycle, optionalReason(await readJson(request))),
          user,
        ),
      )
    }),
  ),

  http.get(
    '/api/v1/committee/me/assignments',
    assessmentRoute(({ request }) => {
      const user = signedInActor()
      authorizeOwnWork(user)
      runAccreditedSchedule()
      const { items, pagination } = myRows(user, queryOf(request))
      return envelope(
        items.map((row) => assignmentRowResource(row, findAssessment(row.assessmentId))),
        { pagination },
      )
    }),
  ),

  http.get(
    ASSIGNMENT,
    cycleRoute(({ params }) => {
      const user = signedInActor()
      const row = boundRow(user, params)
      return envelope(
        evaluationResource(row, findAssessment(row.assessmentId), {
          mode: evaluationMode(user, row),
          returnNote: latestReturnNote(row),
        }),
      )
    }),
  ),

  http.put(`${ASSIGNMENT}/scores`, workRoute(saveCommitteeScores)),
  http.put(`${ASSIGNMENT}/notes`, workRoute(saveCommitteeNotes)),
  http.post(`${ASSIGNMENT}/submit`, workRoute(submitEvaluation)),

  http.get(
    '/api/v1/committee/me/discussions',
    assessmentRoute(({ request }) => {
      const user = signedInActor()
      authorizeOwnWork(user)
      const { items, pagination } = myDiscussions(user, queryOf(request))
      return envelope(
        items.map((discussion) => memberDiscussionResource(discussion, user)),
        { pagination },
      )
    }),
  ),

  http.post(
    '/api/v1/committee/me/discussions/:discussion/entries',
    cycleRoute(async ({ params, request }) => {
      const user = signedInActor()
      return envelope(
        respondToDiscussion(user, params.discussion, await readJson(request)),
        {},
        { status: 201 },
      )
    }),
  ),
]
