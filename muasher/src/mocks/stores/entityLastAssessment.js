import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { allAssessments } from './assessments/assessmentsStore'
import { displayPercent } from './assessments/selfScoring'

const COMPLETED = 'completed'

function newestCompletedFirst(left, right) {
  if (left.completedAt !== right.completedAt) return left.completedAt < right.completedAt ? 1 : -1
  return right.id - left.id
}

function lastAccreditedRecordOf(entityId) {
  return (
    allAssessments()
      .filter(
        (record) =>
          record.entityId === Number(entityId) &&
          record.path === ASSESSMENT_PATHS.ACCREDITED &&
          record.status === COMPLETED &&
          Boolean(record.completedAt),
      )
      .sort(newestCompletedFirst)[0] ?? null
  )
}

export function lastAccreditedAssessmentOf(entityId) {
  const record = lastAccreditedRecordOf(entityId)
  if (!record) return null
  return {
    percent: record.result ? displayPercent(record.result.percent) : null,
    completedAt: record.completedAt,
  }
}

export function lastAssessedAtOf(entity) {
  return lastAccreditedRecordOf(entity.id)?.completedAt ?? null
}

export function lastAssessmentPercentOf(entity) {
  const percent = lastAccreditedAssessmentOf(entity.id)?.percent
  return percent === null || percent === undefined ? null : Number(percent)
}
