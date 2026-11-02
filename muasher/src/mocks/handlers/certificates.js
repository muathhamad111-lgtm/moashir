import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { FIXTURE_ENTITY_ID } from '../fixtures/assessments'
import { certificates, feedbackReports } from '../stores/certificatesStore'
import { findAssessment } from '../stores/assessments/assessmentsStore'
import { boundSubsOf } from '../stores/accredited/accreditedRecords'
import { findSub } from '../stores/criteriaStore'
import { failsRandomly } from '../contract/faults'

const COMPLETED = 'completed'
const maybeFail = failsRandomly(0.06)

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}

function errorResponse(message, status, fieldErrors = {}) {
  return HttpResponse.json({ message, errors: fieldErrors, code: status }, { status })
}

function subNameOf(record, subCriterionId) {
  const bound = record ? boundSubsOf(record).some((sub) => sub.id === subCriterionId) : false
  return bound ? (findSub(subCriterionId)?.name ?? null) : null
}

export const certificatesHandlers = [
  http.get('/api/v1/certificates', async ({ request }) => {
    await delay(360)
    if (maybeFail(0.07)) return errorResponse('Failed to load the certificates', 503)
    const kind = new URL(request.url).searchParams.get('filter[kind]')
    const items = certificates
      .filter((certificate) => certificate.entityId === FIXTURE_ENTITY_ID && (!kind || certificate.kind === kind))
      .map((certificate) => ({ ...certificate }))
      .sort((a, b) => new Date(b.issuedAt) - new Date(a.issuedAt))
    return envelope(items)
  }),

  http.get('/api/v1/certificates/:id', async ({ params }) => {
    await delay(280)
    if (maybeFail(0.05)) return errorResponse('Failed to load the certificate', 503)
    const found = certificates.find((certificate) => certificate.id === params.id)
    if (!found) return errorResponse('Certificate not found', 404)
    return envelope({ ...found })
  }),

  http.post('/api/v1/certificates/:id/download', async ({ params }) => {
    await delay(500)
    if (maybeFail(0.08)) return errorResponse('The download could not be prepared', 500)
    const found = certificates.find((certificate) => certificate.id === params.id)
    if (!found) return errorResponse('Certificate not found', 404)
    return envelope({ downloadUrl: found.downloadUrl, fileName: `${found.id}.pdf` })
  }),

  http.get('/api/v1/assessments/:id/feedback-report', async ({ params }) => {
    await delay(400)
    if (maybeFail(0.06)) return errorResponse('Failed to load the feedback report', 503)
    const record = findAssessment(params.id)
    const report = feedbackReports.find((item) => item.assessmentId === Number(params.id))
    if (!report || record?.status !== COMPLETED) {
      return envelope({ assessmentId: Number(params.id), status: 'not-yet-available', items: [] })
    }
    return envelope({
      assessmentId: report.assessmentId,
      status: report.status,
      generatedAt: report.generatedAt,
      items: report.items.map((item) => ({ ...item, name: subNameOf(record, item.subCriterionId) })),
    })
  }),

  http.post('/api/v1/assessments/:id/feedback-report/export', async ({ params }) => {
    await delay(700)
    if (maybeFail(0.08)) return errorResponse('The export could not be prepared', 500)
    const report = feedbackReports.find((item) => item.assessmentId === Number(params.id))
    if (!report || report.status !== 'available') return errorResponse('The feedback report is not available yet', 422)
    return envelope({ exportUrl: `/assets/reports/feedback-${params.id}.pdf`, fileName: `feedback-${params.id}.pdf` })
  }),
]
