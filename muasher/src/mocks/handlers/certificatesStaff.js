import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan, sessionIsSuperAdmin } from '../stores/sessionPermissions'
import { certificateResource, certificates } from '../stores/certificatesStore'
import { findAssessment } from '../stores/assessments/assessmentsStore'
import {
  actorName,
  forRequest,
  issuedTemplate,
  newCertificate,
} from '../stores/requestCertificates'
import { certificateSettingsResource } from '../stores/certificateSettingsStore'

const LATENCY_MS = 280
const MANAGE = 'certificates.manage'
const ISSUE = 'certificates.issue.accredited'
const REASON_MIN = 10
const CREATED = 201
const REVOCABLE = ['issued', 'suspended']
const REISSUABLE = ['issued', 'suspended', 'revoked']
const CURRENT = ['issued', 'suspended']
const COMPLETED = 'completed'

function staffView(certificate) {
  return {
    ...certificateResource(certificate),
    issuedBy: certificate.issuedBy ?? { id: 10, name: 'د. خالد بن سعد القحطاني' },
    revokedBy: certificate.revokedBy ?? null,
    reissueReason: certificate.reissueReason ?? null,
  }
}

function reasonError(body) {
  const reason = typeof body.reason === 'string' ? body.reason.trim() : ''
  if (reason.length >= REASON_MIN) return null
  return errorResponse(422, 'validation_failed', 'The given data was invalid.', {
    reason: ['The reason must be at least 10 characters.'],
  })
}

function manageable(id) {
  if (!sessionCan(MANAGE)) return { refusal: errorResponse(404, 'not_found', 'Not found.') }
  const certificate = certificates.find((item) => item.id === id)
  return certificate ? { certificate } : { refusal: errorResponse(404, 'not_found', 'Not found.') }
}

export const certificatesStaffHandlers = [
  http.get('/api/v1/certificates', async ({ request }) => {
    const query = new URL(request.url).searchParams
    const assessmentId = query.get('filter[assessmentId]')
    if (!assessmentId || !sessionCan(MANAGE)) return undefined
    await delay(LATENCY_MS)
    const items = forRequest(assessmentId).map(staffView)
    return envelope(items, {
      pagination: { page: 1, perPage: 20, total: items.length, lastPage: 1 },
      accreditedIssuanceMode: certificateSettingsResource().accreditedIssuanceMode,
    })
  }),

  http.get('/api/v1/assessments/:id/certificate', async ({ params }) => {
    await delay(LATENCY_MS)
    const record = findAssessment(Number(params.id))
    if (!record || record.path !== 'accredited')
      return errorResponse(404, 'not_found', 'Not found.')
    if (!sessionCan(ISSUE) && !sessionIsSuperAdmin())
      return errorResponse(403, 'forbidden', 'Forbidden.')
    const current = forRequest(record.id).find((item) => CURRENT.includes(item.status)) ?? null
    return envelope({
      certificate: current ? staffView(current) : null,
      issuanceMode: certificateSettingsResource().accreditedIssuanceMode,
    })
  }),

  http.post('/api/v1/certificates/:id/revoke', async ({ params, request }) => {
    await delay(LATENCY_MS)
    const { certificate, refusal } = manageable(params.id)
    if (refusal) return refusal
    const body = await request.json()
    const invalid = reasonError(body)
    if (invalid) return invalid
    if (!REVOCABLE.includes(certificate.status))
      return errorResponse(409, 'certificate.not_issued', 'This certificate is not issued.')
    Object.assign(certificate, {
      status: 'revoked',
      revokedAt: new Date().toISOString(),
      revokedReason: body.reason.trim(),
      revokedBy: { id: currentSessionUser()?.id ?? null, name: actorName() },
    })
    return envelope(staffView(certificate))
  }),

  http.post('/api/v1/certificates/:id/reissue', async ({ params, request }) => {
    await delay(LATENCY_MS)
    const { certificate, refusal } = manageable(params.id)
    if (refusal) return refusal
    const body = await request.json()
    const invalid = reasonError(body)
    if (invalid) return invalid
    if (!REISSUABLE.includes(certificate.status))
      return errorResponse(409, 'certificate.superseded', 'This certificate was already replaced.')
    const replacement = newCertificate(certificate, {
      replaces: { id: certificate.id, number: certificate.number },
      reissueReason: body.reason.trim(),
    })
    Object.assign(certificate, { status: 'superseded', supersededAt: replacement.issuedAt })
    certificates.unshift(replacement)
    return envelope(staffView(replacement), {}, { status: CREATED })
  }),

  http.post('/api/v1/assessments/:id/certificate', async ({ params, request }) => {
    await delay(LATENCY_MS)
    const record = findAssessment(Number(params.id))
    if (!record || record.path !== 'accredited')
      return errorResponse(404, 'not_found', 'Not found.')
    if (!sessionCan(ISSUE) && !sessionIsSuperAdmin())
      return errorResponse(403, 'forbidden', 'Forbidden.')
    const body = await request.json()
    if (sessionIsSuperAdmin() && !body.reason)
      return errorResponse(422, 'reason_required', 'A reason is required for this action.')
    if (record.status !== COMPLETED)
      return errorResponse(
        409,
        'certificate.assessment_not_completed',
        'The request is not completed.',
      )
    const existing = forRequest(record.id).find((item) => CURRENT.includes(item.status))
    if (existing) return envelope(staffView(existing))
    const issued = newCertificate(issuedTemplate(record), { replaces: null, reissueReason: null })
    certificates.unshift(issued)
    return envelope(staffView(issued), {}, { status: CREATED })
  }),
]
