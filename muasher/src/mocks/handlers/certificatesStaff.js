import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan, sessionIsSuperAdmin } from '../stores/sessionPermissions'
import { certificateResource, certificates } from '../stores/certificatesStore'
import { findAssessment } from '../stores/assessments/assessmentsStore'
import { actorDisplayName } from '../stores/actorName'

const LATENCY_MS = 280
const MANAGE = 'certificates.manage'
const ISSUE = 'certificates.issue.accredited'
const REASON_MIN = 10
const CREATED = 201
const REVOCABLE = ['issued', 'suspended']
const REISSUABLE = ['issued', 'suspended', 'revoked']
const CURRENT = ['issued', 'suspended']
const COMPLETED = 'completed'
const state = { sequence: 40 }

function actorName() {
  return actorDisplayName(currentSessionUser(), '')
}

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

function nextNumber() {
  state.sequence += 1
  return `CRT-2026-${String(state.sequence).padStart(6, '0')}`
}

function nextCode() {
  return `MOCK-${String(state.sequence).padStart(4, '0')}-ISSU-ED26-QRCD-VRFY-0001`
}

function newCertificate(template, extra) {
  return {
    ...template,
    id: crypto.randomUUID(),
    number: nextNumber(),
    verificationCode: nextCode(),
    status: 'issued',
    issuedAt: new Date().toISOString(),
    revokedAt: null,
    revokedReason: null,
    revokedBy: null,
    supersededAt: null,
    suspendedAt: null,
    issuedBy: { id: currentSessionUser()?.id ?? null, name: actorName() },
    ...extra,
  }
}

function forRequest(assessmentId) {
  return certificates
    .filter((certificate) => certificate.request?.id === Number(assessmentId))
    .sort((left, right) => new Date(right.issuedAt) - new Date(left.issuedAt))
}

function manageable(id) {
  if (!sessionCan(MANAGE)) return { refusal: errorResponse(404, 'not_found', 'Not found.') }
  const certificate = certificates.find((item) => item.id === id)
  return certificate ? { certificate } : { refusal: errorResponse(404, 'not_found', 'Not found.') }
}

function issuedTemplate(record) {
  const earlier = forRequest(record.id)[0]
  if (earlier) return earlier
  return {
    entityId: record.entityId,
    kind: 'accredited',
    level: 'silver',
    levelLabel: { ar: 'المستوى الفضي', en: 'Silver level' },
    isTest: false,
    percent: '78.00',
    request: { id: record.id, number: record.number, path: 'accredited', pathLabel: null },
    entity: {
      id: record.entityId,
      reference: `ENT-00${record.entityId}`,
      name: record.entityName ?? null,
    },
    replaces: null,
    hasPdf: true,
  }
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
