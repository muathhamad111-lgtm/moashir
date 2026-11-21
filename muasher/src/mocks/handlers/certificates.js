import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { FIXTURE_ENTITY_ID } from '../fixtures/assessments'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan } from '../stores/sessionPermissions'
import { certificateByCode, certificateResource, certificates } from '../stores/certificatesStore'
import { feedbackReportOf } from '../stores/feedbackReports'
import { findAssessment } from '../stores/assessments/assessmentsStore'
import { findEntity, referenceOf } from '../stores/entitiesStore'
import { signedDownloadOf, storeFile } from '../stores/finance/signedFiles'
import { requestLocale } from '../contract/locale'
import { certificatesPending } from '../stores/certificateScenarios'

const ISSUED = 'issued'
const MANAGE = 'certificates.manage'
const VERIFY_LIMIT = 30
const VERIFY_WINDOW_MS = 60 * 1000
const verifyAttempts = []

const OUTCOMES = {
  issued: 'valid',
  suspended: 'under_review',
  revoked: 'revoked',
  superseded: 'superseded',
}

const MESSAGES = {
  'certificate.revoked': {
    ar: 'أُلغيت هذه الشهادة.',
    en: 'This certificate was revoked.',
  },
  'certificate.superseded': {
    ar: 'استُبدلت هذه الشهادة بشهادة أحدث.',
    en: 'This certificate was replaced.',
  },
  'certificate.suspended': {
    ar: 'الشهادة معلّقة مؤقتاً.',
    en: 'This certificate is suspended.',
  },
  rate_limited: { ar: 'طلبات كثيرة.', en: 'Too many requests.' },
  not_found: { ar: 'غير موجود.', en: 'Not found.' },
}

const CERTIFICATE_STATUSES = ['issued', 'suspended', 'revoked', 'superseded']

function envelope(data, meta = {}, init = {}) {
  return HttpResponse.json({ data, meta, errors: null }, init)
}

function rejection(request, status, code) {
  const message = MESSAGES[code] ?? MESSAGES.not_found
  return HttpResponse.json(
    {
      data: null,
      meta: {},
      message: message[requestLocale(request)] ?? message.ar,
      code,
      errors: {},
    },
    { status },
  )
}

function viewerEntityId() {
  return currentSessionUser()?.entityId ?? FIXTURE_ENTITY_ID
}

function listedEntityId(query) {
  const requested = query.get('filter[entityId]')
  const isStaff = currentSessionUser() && !currentSessionUser().entityId
  if (requested && isStaff) return sessionCan(MANAGE) ? requested : null
  return viewerEntityId()
}

function ownCertificate(id) {
  return (
    certificates.find(
      (certificate) => certificate.id === id && certificate.entityId === Number(viewerEntityId()),
    ) ?? null
  )
}

function statusesOf(query) {
  return (query.get('filter[status]') ?? '').split(',').filter(Boolean)
}

function statusCountsOf(items) {
  return Object.fromEntries(
    CERTIFICATE_STATUSES.map((status) => [
      status,
      items.filter((certificate) => certificate.status === status).length,
    ]),
  )
}

function ownedRecord(id) {
  const record = findAssessment(id)
  return record && record.entityId === Number(viewerEntityId()) ? record : null
}

function entitySummaryOf(entityId) {
  const entity = findEntity(entityId)
  if (!entity) return null
  return {
    id: entity.id,
    reference: referenceOf(entity),
    name: { ...entity.name },
    type: entity.entityType,
    typeLabel: { ar: 'جمعية أهلية', en: 'Civil association' },
    region: { ar: 'منطقة الرياض', en: 'Riyadh Region' },
    city: { ar: 'الرياض', en: 'Riyadh' },
  }
}

function pdfDownload(name) {
  const user = currentSessionUser()
  const file = storeFile({
    purpose: 'certificate',
    name,
    mimeType: 'application/pdf',
    sizeBytes: 182000,
  })
  return signedDownloadOf(user, file)
}

function verifyRateLimited(now = Date.now()) {
  while (verifyAttempts.length && now - verifyAttempts[0] > VERIFY_WINDOW_MS) verifyAttempts.shift()
  verifyAttempts.push(now)
  return verifyAttempts.length > VERIFY_LIMIT
}

export const certificatesHandlers = [
  http.get('/api/v1/certificates', async ({ request }) => {
    await delay(certificatesPending() ? 'infinite' : 320)
    const query = new URL(request.url).searchParams
    const statuses = statusesOf(query)
    const owned = certificates.filter(
      (certificate) => certificate.entityId === Number(listedEntityId(query)),
    )
    const items = owned
      .filter((certificate) => !statuses.length || statuses.includes(certificate.status))
      .sort((a, b) => new Date(b.issuedAt) - new Date(a.issuedAt))
      .map(certificateResource)
    return envelope(items, {
      statusCounts: statusCountsOf(owned),
      pagination: { page: 1, perPage: 50, total: items.length, lastPage: 1 },
    })
  }),

  http.get('/api/v1/certificates/:id', async ({ params, request }) => {
    await delay(240)
    const found = ownCertificate(params.id)
    if (!found) return rejection(request, 404, 'not_found')
    return envelope(certificateResource(found))
  }),

  http.post('/api/v1/certificates/:id/download', async ({ params, request }) => {
    await delay(400)
    const found = ownCertificate(params.id)
    if (!found) return rejection(request, 404, 'not_found')
    if (found.status !== ISSUED) return rejection(request, 409, `certificate.${found.status}`)
    return envelope(pdfDownload(`${found.number}.pdf`))
  }),

  http.get('/api/v1/verify/:code', async ({ params, request }) => {
    await delay(260)
    if (verifyRateLimited()) return rejection(request, 429, 'rate_limited')
    const found = certificateByCode(params.code)
    if (!found) return envelope({ result: 'not_found', certificate: null })
    if (found.isTest) return envelope({ result: 'unverifiable', certificate: null })
    const outcome = OUTCOMES[found.status]
    const hidden = outcome === 'under_review'
    return envelope({
      result: outcome,
      certificate: {
        number: found.number,
        kind: found.kind,
        level: hidden ? null : found.level,
        levelLabel: hidden ? null : found.levelLabel,
        percent: hidden ? null : found.percent,
        entityName: found.entity.name,
        issuedAt: found.issuedAt,
      },
    })
  }),

  http.get('/api/v1/assessments/:id/feedback-report', async ({ params, request }) => {
    await delay(360)
    const record = ownedRecord(params.id)
    if (!record) return rejection(request, 404, 'not_found')
    return envelope(feedbackReportOf(record, entitySummaryOf(record.entityId)))
  }),

  http.post('/api/v1/assessments/:id/feedback-report/export', async ({ params, request }) => {
    await delay(500)
    const record = ownedRecord(params.id)
    if (!record) return rejection(request, 404, 'not_found')
    if (!feedbackReportOf(record, null).available)
      return rejection(request, 422, 'report.not_available')
    const body = (await request.json().catch(() => null)) ?? {}
    return envelope(pdfDownload(`feedback-${record.number}-${body.locale ?? 'ar'}.pdf`))
  }),
]
