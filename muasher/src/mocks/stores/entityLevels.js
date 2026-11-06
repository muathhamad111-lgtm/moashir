import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { OPEN_ACCREDITED_STATUSES } from '@/constants/levels'
import { allAssessments } from './assessments/assessmentsStore'
import { certificates } from './certificatesStore'

const METAL_LEVELS = ['bronze', 'silver', 'gold', 'diamond']
const PARTICIPATION = 'participation'
const NONE = 'none'
const IN_PROGRESS = 'in_progress'
const ISSUED = 'issued'
const ACCREDITED_KIND = 'accredited'

export const LEVEL_FILTERS = [NONE, PARTICIPATION, ...METAL_LEVELS]
export const LEVEL_BUCKET_KEYS = [NONE, IN_PROGRESS, PARTICIPATION, ...METAL_LEVELS]
const LEVEL_RANK = [NONE, PARTICIPATION, ...METAL_LEVELS]

function newerFirst(left, right) {
  if (left.issuedAt !== right.issuedAt) return left.issuedAt < right.issuedAt ? 1 : -1
  return left.id < right.id ? 1 : -1
}

export function levelCertificateOf(entityId) {
  return (
    certificates
      .filter(
        (certificate) =>
          certificate.entityId === Number(entityId) &&
          certificate.kind === ACCREDITED_KIND &&
          certificate.status === ISSUED,
      )
      .sort(newerFirst)[0] ?? null
  )
}

export function entityLevelOf(entityId) {
  const certificate = levelCertificateOf(entityId)
  if (!certificate) return null
  return METAL_LEVELS.includes(certificate.level) ? certificate.level : PARTICIPATION
}

export function hasOpenAccreditedRequest(entityId) {
  return allAssessments().some(
    (record) =>
      record.entityId === Number(entityId) &&
      record.path === ASSESSMENT_PATHS.ACCREDITED &&
      OPEN_ACCREDITED_STATUSES.includes(record.status),
  )
}

export function entityLevelFields(entity) {
  return {
    level: entityLevelOf(entity.id),
    levelCertificateNumber: levelCertificateOf(entity.id)?.number ?? null,
  }
}

export function levelFilterOf(entity) {
  return entityLevelOf(entity.id) ?? NONE
}

export function levelRankOf(entity) {
  return LEVEL_RANK.indexOf(levelFilterOf(entity))
}

function bucketOf(entity) {
  const level = entityLevelOf(entity.id)
  if (level) return level
  return hasOpenAccreditedRequest(entity.id) ? IN_PROGRESS : NONE
}

export function entitiesByLevel(entities) {
  const buckets = entities.map(bucketOf)
  return LEVEL_BUCKET_KEYS.map((key) => ({
    key,
    count: buckets.filter((bucket) => bucket === key).length,
  }))
}
