import { delay } from 'msw'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import { paginate } from '../contract/pagination'
import { MockRejection, rejectionResponse } from '../contract/rejection'
import { assessmentMessages } from '../fixtures/assessmentMessages'
import { insightsMessages } from '../fixtures/insightsMessages'
import { readJson } from '../stores/assessments/assessmentRejection'
import { readsEveryAssessment, signedInActor } from '../stores/assessments/assessmentAccess'
import { findAssessment } from '../stores/assessments/assessmentsStore'
import { sessionCan } from '../stores/sessionPermissions'
import { findEntity } from '../stores/entitiesStore'
import { signedDownloadOf, storeFile } from '../stores/finance/signedFiles'
import {
  eligibleResultsOf,
  exclusionReasonOf,
  exclusionsOf,
  hasSelfResult,
  isEligible,
  openAccreditedRequestOf,
  resultRefOf,
  snapshotRecordsOf,
} from '../stores/insights/insightsResults'
import { executiveSummaryOf, maturityOf, strengthsGapsOf } from '../stores/insights/insightsTools'
import { requestLocale } from '../contract/locale'
import {
  insightsRulesResource,
  rulesVersions,
  updateInsightsRules,
} from '../stores/insights/insightsRules'
import {
  deleteMaturityText,
  maturityTextRows,
  saveMaturityText,
} from '../stores/insights/maturityTexts'

const LATENCY_MS = 140
const OWN_VIEW = 'reports.insights.own.view'
const STAFF_VIEW = 'reports.insights.view'
const SETTINGS_MANAGE = 'settings.assessment.manage'
const TEXTS_MANAGE = 'content.insights.manage'
const LOCALES = ['ar', 'en']
const COMING_SOON = ['periodic', 'annual', 'benchmark'].map((kind) => ({ kind }))
const MESSAGES = { ...assessmentMessages, ...insightsMessages }

const reject = {
  forbidden: () => new MockRejection(403, 'forbidden'),
  notFound: () => new MockRejection(404, 'not_found'),
  notEligible: (reason) =>
    new MockRejection(409, 'insights.result_not_eligible', { meta: { reason } }),
}

function insightsRoute(resolve) {
  return async (info) => {
    await delay(LATENCY_MS)
    try {
      return await resolve(info)
    } catch (error) {
      if (!(error instanceof MockRejection)) throw error
      return rejectionResponse(error, info.request, MESSAGES)
    }
  }
}

function isStaffReader(user) {
  return user.superAdmin || (sessionCan(STAFF_VIEW) && readsEveryAssessment(user))
}

function ownEntityOf(user) {
  if (!user.entityId || !sessionCan(OWN_VIEW)) throw reject.forbidden()
  return Number(user.entityId)
}

function staffEntityOf(user, entityId) {
  if (!isStaffReader(user)) throw reject.forbidden()
  if (!findEntity(entityId)) throw reject.notFound()
  return Number(entityId)
}

function emptyStateOf(entityId, offersActions) {
  if (eligibleResultsOf(entityId).length) return null
  const open = openAccreditedRequestOf(entityId)
  const hasExcluded = snapshotRecordsOf(entityId).length > 0
  const reason = hasExcluded
    ? 'under_review'
    : open
      ? 'in_progress'
      : hasSelfResult(entityId)
        ? 'only_self'
        : 'no_verified_result'
  return { reason, cta: offersActions ? ctaOf(reason, open) : null }
}

function ctaOf(reason, open) {
  if (reason === 'under_review') return null
  if (reason === 'in_progress') return { kind: 'view_request', assessmentId: open.id }
  return { kind: 'start_accredited', assessmentId: null }
}

function overviewOf(entityId, offersActions) {
  const selected = eligibleResultsOf(entityId)[0] ?? null
  const open = openAccreditedRequestOf(entityId)
  return {
    selected: selected ? resultRefOf(selected) : null,
    exclusions: exclusionsOf(entityId),
    emptyState: emptyStateOf(entityId, offersActions),
    inProgressRequest: selected && open ? { assessmentId: open.id } : null,
    rules: rulesVersions(),
  }
}

function resultsOf(entityId, request) {
  const query = new URL(request.url).searchParams
  const { items, pagination } = paginate(eligibleResultsOf(entityId), query)
  return envelope(items.map(resultRefOf), { pagination })
}

function feedbackPdfUrl(user, record, locale) {
  const file = storeFile({
    purpose: 'feedback_report',
    name: `feedback-${record.number}-${locale}.pdf`,
    mimeType: 'application/pdf',
    sizeBytes: 182000,
  })
  return signedDownloadOf(user, file).url
}

function feedbackReportItem(record, { user, locale }) {
  const available = record.status === 'completed'
  return {
    kind: 'feedback_report',
    available,
    status: null,
    certificateId: null,
    pdfAvailable: available,
    pdfUrl: available ? feedbackPdfUrl(user, record, locale) : null,
  }
}

function libraryRowOf(record, viewer) {
  const ref = resultRefOf(record)
  const reason = exclusionReasonOf(record)
  return {
    result: ref,
    eligible: !reason,
    exclusionReason: reason,
    items: [
      feedbackReportItem(record, viewer),
      { kind: 'board_report', available: !reason, status: null, certificateId: null },
      {
        kind: 'certificate',
        available: ref.certificate?.status === 'issued',
        status: ref.certificate?.status ?? null,
        certificateId: ref.certificate?.id ?? null,
      },
    ],
  }
}

function libraryOf(entityId, request) {
  const query = new URL(request.url).searchParams
  const { items, pagination } = paginate(snapshotRecordsOf(entityId), query)
  const viewer = { user: signedInActor(), locale: requestLocale(request) }
  return envelope(
    items.map((record) => libraryRowOf(record, viewer)),
    { comingSoon: COMING_SOON, pagination },
  )
}

function canViewRecord(user, record) {
  if (user.superAdmin || isStaffReader(user)) return true
  return sessionCan(OWN_VIEW) && Number(user.entityId) === record.entityId
}

function eligibleRecord(user, id) {
  const record = findAssessment(id)
  if (!record || record.path === ASSESSMENT_PATHS.SELF) throw reject.notFound()
  if (!user.superAdmin && !sessionCan(OWN_VIEW) && !sessionCan(STAFF_VIEW)) throw reject.forbidden()
  if (!canViewRecord(user, record)) throw reject.notFound()
  if (record.path !== ASSESSMENT_PATHS.ACCREDITED) throw reject.notEligible('not_accredited')
  if (!record.result || !record.completedAt) throw reject.notEligible('not_completed')
  if (!isEligible(record)) throw reject.notEligible(exclusionReasonOf(record) ?? 'not_completed')
  return record
}

function toolRoute(path, present) {
  return http.get(
    `/api/v1/assessments/:id/insights/${path}`,
    insightsRoute(({ params }) => {
      const user = signedInActor()
      return envelope(present(eligibleRecord(user, params.id), user))
    }),
  )
}

function boardReportLink(user, record, locale) {
  const file = storeFile({
    purpose: 'board_report',
    name: `board-report-${record.number}-${locale}.pdf`,
    mimeType: 'application/pdf',
    sizeBytes: 96000,
  })
  return signedDownloadOf(user, file)
}

function authorizeSettings(user) {
  if (!user.superAdmin && !sessionCan(SETTINGS_MANAGE)) throw reject.forbidden()
}

function authorizeTexts(user) {
  if (!user.superAdmin && !sessionCan(TEXTS_MANAGE)) throw reject.forbidden()
}

export const insightsHandlers = [
  http.get(
    '/api/v1/entities/me/insights',
    insightsRoute(() => envelope(overviewOf(ownEntityOf(signedInActor()), true))),
  ),
  http.get(
    '/api/v1/entities/me/insights/results',
    insightsRoute(({ request }) => resultsOf(ownEntityOf(signedInActor()), request)),
  ),
  http.get(
    '/api/v1/entities/me/report-library',
    insightsRoute(({ request }) => libraryOf(ownEntityOf(signedInActor()), request)),
  ),
  http.get(
    '/api/v1/entities/:entity/insights',
    insightsRoute(({ params }) =>
      envelope(overviewOf(staffEntityOf(signedInActor(), params.entity), false)),
    ),
  ),
  http.get(
    '/api/v1/entities/:entity/insights/results',
    insightsRoute(({ params, request }) =>
      resultsOf(staffEntityOf(signedInActor(), params.entity), request),
    ),
  ),
  http.get(
    '/api/v1/entities/:entity/report-library',
    insightsRoute(({ params, request }) =>
      libraryOf(staffEntityOf(signedInActor(), params.entity), request),
    ),
  ),
  toolRoute('strengths-gaps', strengthsGapsOf),
  toolRoute('maturity', maturityOf),
  toolRoute('executive-summary', executiveSummaryOf),
  http.post(
    '/api/v1/assessments/:id/board-report/export',
    insightsRoute(async ({ params, request }) => {
      const user = signedInActor()
      const record = eligibleRecord(user, params.id)
      const locale = (await readJson(request)).locale ?? 'ar'
      if (!LOCALES.includes(locale))
        throw new MockRejection(422, 'validation_failed', {
          fields: { locale: [{ ar: 'اللغة غير مدعومة.', en: 'The language is not supported.' }] },
        })
      return envelope(boardReportLink(user, record, locale))
    }),
  ),
  http.get(
    '/api/v1/settings/insights',
    insightsRoute(() => {
      authorizeSettings(signedInActor())
      return envelope(insightsRulesResource())
    }),
  ),
  http.put(
    '/api/v1/settings/insights',
    insightsRoute(async ({ request }) => {
      const user = signedInActor()
      authorizeSettings(user)
      updateInsightsRules(user, await readJson(request))
      return envelope(insightsRulesResource())
    }),
  ),
  http.get(
    '/api/v1/content/maturity-texts',
    insightsRoute(({ request }) => {
      authorizeTexts(signedInActor())
      const query = new URL(request.url).searchParams
      const { items, pagination } = paginate(maturityTextRows(), query, 50)
      return envelope(items, { pagination })
    }),
  ),
  http.put(
    '/api/v1/content/maturity-texts/:band',
    insightsRoute(async ({ params, request }) => {
      authorizeTexts(signedInActor())
      return envelope(saveMaturityText(params.band, await readJson(request)))
    }),
  ),
  http.delete(
    '/api/v1/content/maturity-texts/:id',
    insightsRoute(({ params }) => {
      authorizeTexts(signedInActor())
      deleteMaturityText(params.id)
      return noContent()
    }),
  ),
]
