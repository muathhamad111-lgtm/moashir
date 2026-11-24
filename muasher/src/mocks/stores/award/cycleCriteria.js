import { CRITERIA_VERSION_STATUSES } from '@/constants/criteria'
import { assessmentReject } from '../assessments/assessmentRejection'
import { versionWithStatus } from '../criteriaStore'

export function publishedVersion() {
  const version = versionWithStatus(CRITERIA_VERSION_STATUSES.PUBLISHED)
  if (!version) throw assessmentReject.coded(409, 'cycle.no_published_criteria')
  return version
}

export function followPublished(cycle) {
  const version = versionWithStatus(CRITERIA_VERSION_STATUSES.PUBLISHED)
  if (cycle.status !== 'draft' || !version || version.id === cycle.criteriaVersionId) return {}
  return { criteriaVersionId: version.id, scaleKind: version.scaleKind }
}
