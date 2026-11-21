import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { readJson } from '../stores/assessments/assessmentRejection'
import { signedInActor } from '../stores/assessments/assessmentAccess'
import { normalizeSearchText } from '../stores/searchIndex'
import {
  authorizeDecideSecretary,
  authorizeViewSecretary,
} from '../stores/committee/committeeAccess'
import {
  approveAverage,
  approveSubstitution,
  deskRecord,
  recomputeAverage,
  returnToCommittee,
  secretaryDetail,
  secretaryEvaluation,
  secretaryQueue,
} from '../stores/committee/secretaryDesk'
import {
  closeDiscussion,
  discussionResource,
  findRecordDiscussion,
  openDiscussion,
} from '../stores/committee/committeeDiscussions'
import { boundCycle, cycleRoute, queryOf } from './awardRouting'

const DESK = '/api/v1/award/cycles/:cycle/secretary/requests'
const REQUEST = `${DESK}/:assessment`

function readRoute(resolve) {
  return cycleRoute((info) => {
    const user = signedInActor()
    const cycle = boundCycle(info.params)
    authorizeViewSecretary(user, cycle)
    return resolve({ ...info, user, cycle })
  })
}

function decisionRoute(resolve) {
  return cycleRoute(async (info) => {
    const user = signedInActor()
    const cycle = boundCycle(info.params)
    authorizeDecideSecretary(user, cycle)
    const record = deskRecord(cycle, info.params.assessment)
    return resolve({ ...info, user, cycle, record, body: await readJson(info.request) })
  })
}

export const secretaryHandlers = [
  http.get(
    DESK,
    readRoute(({ cycle, request }) => {
      const { items, meta } = secretaryQueue(cycle, queryOf(request), normalizeSearchText)
      return envelope(items, meta)
    }),
  ),

  http.get(
    REQUEST,
    readRoute(({ cycle, params }) =>
      envelope(secretaryDetail(deskRecord(cycle, params.assessment))),
    ),
  ),

  http.get(
    `${REQUEST}/evaluations/:assignment`,
    readRoute(({ cycle, params }) =>
      envelope(secretaryEvaluation(deskRecord(cycle, params.assessment), params.assignment)),
    ),
  ),

  http.post(
    `${REQUEST}/average`,
    decisionRoute(({ user, cycle, record, body }) => {
      const meta = recomputeAverage(user, cycle, record, body)
      return envelope(secretaryDetail(record), meta)
    }),
  ),

  http.post(
    `${REQUEST}/approve`,
    decisionRoute(({ user, cycle, record, body }) =>
      envelope(secretaryDetail(approveAverage(user, cycle, record, body))),
    ),
  ),

  http.post(
    `${REQUEST}/return`,
    decisionRoute(({ user, cycle, record, body }) =>
      envelope(secretaryDetail(returnToCommittee(user, cycle, record, body))),
    ),
  ),

  http.post(
    `${REQUEST}/substitutions/:substitution/approve`,
    decisionRoute(({ user, cycle, record, body, params }) =>
      envelope(
        secretaryDetail(approveSubstitution(user, cycle, record, params.substitution, body)),
      ),
    ),
  ),

  http.post(
    `${REQUEST}/discussions`,
    decisionRoute(({ user, cycle, record, body }) =>
      envelope(discussionResource(openDiscussion(user, cycle, record, body)), {}, { status: 201 }),
    ),
  ),

  http.post(
    `${REQUEST}/discussions/:discussion/close`,
    decisionRoute(({ user, cycle, record, body, params }) =>
      envelope(
        discussionResource(
          closeDiscussion(
            user,
            cycle,
            record,
            findRecordDiscussion(record, params.discussion),
            body,
          ),
        ),
      ),
    ),
  ),
]
