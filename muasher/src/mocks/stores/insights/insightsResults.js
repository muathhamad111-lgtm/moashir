import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { allAssessments, isOpen } from '../assessments/assessmentsStore'
import { displayPercent } from '../assessments/selfScoring'
import { certificates } from '../certificatesStore'
import { findVersion } from '../criteriaStore'

const COMPLETED = 'completed'
const EXCLUDING_CERTIFICATES = { revoked: 'certificate_revoked', suspended: 'under_review' }

function byNewest(left, right) {
  if (left.completedAt === right.completedAt) return right.id - left.id
  return left.completedAt < right.completedAt ? 1 : -1
}

export function latestCertificateOf(record) {
  return (
    certificates
      .filter((certificate) => certificate.request?.id === record.id)
      .sort((left, right) => (left.issuedAt < right.issuedAt ? 1 : -1))[0] ?? null
  )
}

function accreditedOfEntity(entityId) {
  return allAssessments().filter(
    (record) => record.path === ASSESSMENT_PATHS.ACCREDITED && record.entityId === Number(entityId),
  )
}

export function exclusionReasonOf(record) {
  if (record.status !== COMPLETED) return 'under_review'
  return EXCLUDING_CERTIFICATES[latestCertificateOf(record)?.status] ?? null
}

export function snapshotRecordsOf(entityId) {
  return accreditedOfEntity(entityId)
    .filter((record) => record.result && record.completedAt)
    .sort(byNewest)
}

export function eligibleResultsOf(entityId) {
  return snapshotRecordsOf(entityId).filter(
    (record) => record.status === COMPLETED && !exclusionReasonOf(record),
  )
}

export function isEligible(record) {
  return eligibleResultsOf(record.entityId).some((item) => item.id === record.id)
}

export function previousOf(record) {
  const eligible = eligibleResultsOf(record.entityId)
  const index = eligible.findIndex((item) => item.id === record.id)
  return index === -1 ? null : (eligible[index + 1] ?? null)
}

export function exclusionsOf(entityId) {
  const selected = eligibleResultsOf(entityId)[0] ?? null
  return snapshotRecordsOf(entityId)
    .filter((record) => exclusionReasonOf(record))
    .filter((record) => !selected || record.completedAt > selected.completedAt)
    .map((record) => ({
      assessmentId: record.id,
      number: record.number,
      reason: exclusionReasonOf(record),
    }))
}

export function openAccreditedRequestOf(entityId) {
  return accreditedOfEntity(entityId).find((record) => isOpen(record)) ?? null
}

export function hasSelfResult(entityId) {
  return allAssessments().some(
    (record) =>
      record.path === ASSESSMENT_PATHS.SELF &&
      record.entityId === Number(entityId) &&
      Boolean(record.result),
  )
}

function criteriaVersionRef(record) {
  const version = findVersion(record.criteriaVersionId)
  if (!version) return null
  return { id: version.id, versionNumber: version.versionNumber, label: { ...version.label } }
}

function certificateRef(record) {
  const certificate = latestCertificateOf(record)
  if (!certificate) return null
  return {
    id: certificate.id,
    number: certificate.number ?? null,
    status: certificate.status,
    level: certificate.level ?? null,
  }
}

export function resultRefOf(record) {
  return {
    assessmentId: record.id,
    number: record.number,
    percent: displayPercent(record.result.percent),
    snapshotVersion: record.result.version ?? 1,
    verifiedAt: record.completedAt,
    criteriaVersion: criteriaVersionRef(record),
    certificate: certificateRef(record),
  }
}
