import { HttpResponse, delay } from 'msw'
import { http } from '../contract/http'
import { subCriteriaFixture } from '../fixtures/criteria'
import { advancedCertificatesFixture, advancedFeedbackReportsFixture, advancedResultsFixture } from '../fixtures/advancedResults'
import { failsRandomly } from '../contract/faults'

const ANNOUNCED = ['announced', 'certificate-issued']
const maybeFail = failsRandomly(0.06)

function envelope(data, meta = {}) {
  return HttpResponse.json({ data, meta, errors: null })
}

function errorResponse(message, status) {
  return HttpResponse.json({ message, errors: {}, code: status }, { status })
}

function subName(id) {
  return subCriteriaFixture.find((sub) => sub.id === id)?.name ?? null
}

function feedbackOf(assessmentId) {
  const report = advancedFeedbackReportsFixture.find((item) => item.assessmentId === assessmentId)
  if (!report) return { source: 'first-consultant', status: 'not-yet-available', items: [] }
  return {
    source: report.source,
    status: report.status,
    generatedAt: report.generatedAt,
    items: report.items.map((item) => ({ subCriterionId: item.subCriterionId, name: subName(item.subCriterionId), scorePercent: item.scorePercent, comment: item.comment })),
  }
}

function resultOf(assessment) {
  if (!ANNOUNCED.includes(assessment.status)) return { id: assessment.id, status: assessment.status, cycleId: assessment.cycleId, available: false }
  return {
    id: assessment.id,
    status: assessment.status,
    available: true,
    cycleId: assessment.cycleId,
    cycleName: { ...assessment.cycleName },
    announcedAt: assessment.announcedAt,
    resultKind: assessment.certificateKind,
    certificateId: assessment.certificateId,
    overallPercent: assessment.initialPercent,
    feedback: feedbackOf(assessment.id),
  }
}

export const advancedResultsHandlers = [
  http.get('/api/v1/assessments/:id/advanced-result', async ({ params }) => {
    await delay(420)
    if (maybeFail(0.06)) return errorResponse('Failed to load the result', 503)
    const assessment = advancedResultsFixture.find((item) => item.id === Number(params.id))
    if (!assessment) return errorResponse('Assessment not found', 404)
    return envelope(resultOf(assessment))
  }),

  http.get('/api/v1/award/certificates/:id', async ({ params }) => {
    await delay(280)
    if (maybeFail(0.05)) return errorResponse('Failed to load the certificate', 503)
    const found = advancedCertificatesFixture.find((item) => item.id === params.id)
    if (!found) return errorResponse('Certificate not found', 404)
    return envelope({ ...found })
  }),

  http.post('/api/v1/award/certificates/:id/download', async ({ params }) => {
    await delay(500)
    if (maybeFail(0.08)) return errorResponse('The download could not be prepared', 500)
    const found = advancedCertificatesFixture.find((item) => item.id === params.id)
    if (!found) return errorResponse('Certificate not found', 404)
    return envelope({ downloadUrl: found.downloadUrl, fileName: `${found.id}.pdf` })
  }),
]
