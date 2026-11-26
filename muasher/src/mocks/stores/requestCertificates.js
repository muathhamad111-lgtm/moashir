import { currentSessionUser } from './sessionStore'
import { actorDisplayName } from './actorName'
import { certificates } from './certificatesStore'
import { ISSUANCE_MODES, certificateSettingsResource } from './certificateSettingsStore'

// Certificates of one accredited request: minted by the assessor (manual mode) or on completion
// (automatic mode), mirroring IssueAccreditedCertificate.
const state = { sequence: 40 }

export function actorName() {
  return actorDisplayName(currentSessionUser(), '')
}

function nextNumber() {
  state.sequence += 1
  return `CRT-2026-${String(state.sequence).padStart(6, '0')}`
}

function nextCode() {
  return `MOCK-${String(state.sequence).padStart(4, '0')}-ISSU-ED26-QRCD-VRFY-0001`
}

export function newCertificate(template, extra) {
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

export function forRequest(assessmentId) {
  return certificates
    .filter((certificate) => certificate.request?.id === Number(assessmentId))
    .sort((left, right) => new Date(right.issuedAt) - new Date(left.issuedAt))
}

export function issuedTemplate(record) {
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

export function issueOnCompletion(record) {
  if (certificateSettingsResource().accreditedIssuanceMode !== ISSUANCE_MODES.AUTOMATIC) return null
  const issued = newCertificate(issuedTemplate(record), { replaces: null, reissueReason: null })
  certificates.unshift(issued)
  return issued
}
