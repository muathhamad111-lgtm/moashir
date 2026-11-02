import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { assessmentReject, assessmentRoute, readJson } from '../stores/assessments/assessmentRejection'
import { signedInActor } from '../stores/assessments/assessmentAccess'
import { allAssessments, exposesResult, findAssessment, isOpen } from '../stores/assessments/assessmentsStore'
import { assessmentResource, scoresResource } from '../stores/assessments/selfSerializers'
import { sessionCan } from '../stores/sessionPermissions'
import { ACCREDITED_PERMISSIONS, authorizeOverride, authorizeReview, authorizeScore } from '../stores/accredited/accreditedAccess'
import { feedbackResource, reviewResource } from '../stores/accredited/accreditedSerializers'
import {
  completeReview,
  requestCompletion,
  reviewModeOf,
  saveFeedbackItems,
  saveReviewScores,
  startReview,
} from '../stores/accredited/assessorWork'
import { forceTransition, overrideScores, reopenAssessment } from '../stores/accredited/overrides'
import { runAccreditedSchedule } from '../stores/accredited/scheduler'

const ASSESSMENT = '/api/v1/assessments/:assessment'
const NUMERIC_ID = /^\d+$/
const CONTEXT_PATHS = ['self', 'accredited']

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

function latestResultOf(records) {
  return records
    .filter((record) => !isOpen(record) && exposesResult(record))
    .sort((a, b) => ((a.completedAt ?? '') < (b.completedAt ?? '') ? 1 : -1))[0]
}

function contextOf(record) {
  if (!sessionCan(ACCREDITED_PERMISSIONS.CONTEXT_VIEW)) return []
  const others = allAssessments().filter((item) => item.entityId === record.entityId && item.id !== record.id)
  return CONTEXT_PATHS.map((path) => latestResultOf(others.filter((item) => item.path === path)))
    .filter(Boolean)
    .map((item) => ({ id: item.id, number: item.number, path: item.path, completedAt: item.completedAt, percent: assessmentResource(item).result?.percent ?? null }))
}

function scoringRoute(path, act, present = assessmentResource) {
  return http.post(
    `${ASSESSMENT}/${path}`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeScore(user, record)
      return envelope(present(act(user, record, await readJson(request))))
    }),
  )
}

function overrideRoute(path, act) {
  return http.post(
    `${ASSESSMENT}/${path}`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeOverride(user, record)
      return envelope(assessmentResource(act(record, await readJson(request))))
    }),
  )
}

export const assessorReviewHandlers = [
  http.get(
    `${ASSESSMENT}/review`,
    numericRoute(({ params }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeReview(user, record)
      return envelope(reviewResource(record, { mode: reviewModeOf(user, record), context: contextOf(record) }))
    }),
  ),

  scoringRoute('start-review', startReview),

  http.put(
    `${ASSESSMENT}/review/scores`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeScore(user, record)
      return envelope(scoresResource(saveReviewScores(user, record, await readJson(request))))
    }),
  ),

  http.put(
    `${ASSESSMENT}/review/feedback`,
    numericRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = boundRecord(params)
      authorizeScore(user, record)
      return envelope(feedbackResource(saveFeedbackItems(user, record, await readJson(request))))
    }),
  ),

  scoringRoute('request-completion', requestCompletion),
  scoringRoute('complete', completeReview),

  overrideRoute('scores/override', overrideScores),
  overrideRoute('reopen', reopenAssessment),
  overrideRoute('transition/force', forceTransition),
]
