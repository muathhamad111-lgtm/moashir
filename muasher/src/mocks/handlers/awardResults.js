import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { readJson } from '../stores/assessments/assessmentRejection'
import { signedInActor } from '../stores/assessments/assessmentAccess'
import { cycleDetailResource } from '../stores/award/cycleSerializers'
import {
  authorizeAnnounce,
  authorizeApproveResults,
  authorizeRank,
  authorizeUnapprove,
  authorizeViewResults,
} from '../stores/committee/committeeAccess'
import {
  announceCycle,
  approveNational,
  approveRegion,
  boundRanking,
  cycleRegion,
  resultsBoard,
  setRank,
  unapproveRegion,
} from '../stores/committee/awardResultsBoard'
import { boundCycle, cycleRoute, queryOf } from './awardRouting'

const CYCLE = '/api/v1/award/cycles/:cycle'

function boardResponse(cycle, stage, regionId = null) {
  const { items, meta } = resultsBoard(cycle, stage, regionId)
  return envelope(items, meta)
}

function guarded(authorize, resolve) {
  return cycleRoute(async (info) => {
    const user = signedInActor()
    const cycle = boundCycle(info.params)
    authorize(user, cycle)
    return resolve({
      ...info,
      user,
      cycle,
      body: info.request.method === 'GET' ? {} : await readJson(info.request),
    })
  })
}

function regionFilter(request) {
  const value = queryOf(request).get('filter[regionId]')
  return value ? Number(value) : null
}

export const awardResultsHandlers = [
  http.get(
    `${CYCLE}/results`,
    guarded(authorizeViewResults, ({ cycle, request }) =>
      boardResponse(cycle, 'regional', regionFilter(request)),
    ),
  ),

  http.put(
    `${CYCLE}/results/:assessment/regional-rank`,
    guarded(authorizeRank, ({ user, cycle, params, body }) =>
      envelope(setRank(user, cycle, boundRanking(cycle, params.assessment), 'regional', body)),
    ),
  ),

  http.post(
    `${CYCLE}/regions/:region/approve`,
    guarded(authorizeApproveResults, ({ user, cycle, params, body }) => {
      const regionId = cycleRegion(cycle, params.region)
      approveRegion(user, cycle, regionId, body)
      return boardResponse(cycle, 'regional', regionId)
    }),
  ),

  http.post(
    `${CYCLE}/regions/:region/unapprove`,
    guarded(authorizeUnapprove, ({ user, cycle, params, body }) => {
      const regionId = cycleRegion(cycle, params.region)
      unapproveRegion(user, cycle, regionId, body)
      return boardResponse(cycle, 'regional', regionId)
    }),
  ),

  http.get(
    `${CYCLE}/national`,
    guarded(authorizeViewResults, ({ cycle }) => boardResponse(cycle, 'national')),
  ),

  http.put(
    `${CYCLE}/national/:assessment/rank`,
    guarded(authorizeRank, ({ user, cycle, params, body }) =>
      envelope(setRank(user, cycle, boundRanking(cycle, params.assessment), 'national', body)),
    ),
  ),

  http.post(
    `${CYCLE}/national/approve`,
    guarded(authorizeApproveResults, ({ user, cycle, body }) => {
      approveNational(user, cycle, body)
      return boardResponse(cycle, 'national')
    }),
  ),

  http.post(
    `${CYCLE}/announce`,
    guarded(authorizeAnnounce, ({ user, cycle, body }) =>
      envelope(cycleDetailResource(announceCycle(user, cycle, body), user)),
    ),
  ),
]
