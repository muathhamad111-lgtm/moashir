import { toAssessment, toAssessmentScores, toEntitySummary } from './assessments'
import { toCompletionRequest, toCvSheet, toEvidenceList, toFeedbackFields } from './accredited'
import { toClock, toPlace } from './requests'

function toReviewEntity(raw) {
  if (!raw) return null
  return {
    ...toEntitySummary(raw),
    entityType: raw.entityType ?? null,
    developmentSector: raw.developmentSector ?? null,
    orgSize: raw.orgSize ?? null,
    foundingYear: raw.foundingYear ?? null,
    region: toPlace(raw.region),
    city: toPlace(raw.city),
    governanceGrade: raw.governanceGrade ?? null,
    employeesCount: raw.employeesCount ?? null,
    annualBeneficiariesCount: raw.annualBeneficiariesCount ?? null,
    hasStrategicPlan: raw.hasStrategicPlan === true,
  }
}

function toFeedbackSheet(raw) {
  return Object.fromEntries(Object.entries(raw ?? {}).map(([subId, fields]) => [subId, toFeedbackFields(fields)]))
}

function toContextItem(raw) {
  return { id: raw.id ?? null, number: raw.number ?? '', path: raw.path ?? null, completedAt: raw.completedAt ?? null, percent: raw.percent ?? null }
}

export function toAssessorReview(raw = {}) {
  return {
    ...toAssessmentScores(raw),
    mode: raw.mode ?? null,
    clock: toClock(raw),
    entity: toReviewEntity(raw.entity),
    criteria: raw.criteria ?? null,
    cv: toCvSheet(raw.cv),
    evidence: toEvidenceList(raw.evidence),
    feedback: toFeedbackSheet(raw.feedback),
    completionRequest: toCompletionRequest(raw.completionRequest),
    context: (raw.context ?? []).map(toContextItem),
  }
}

export function toFeedbackSaved(raw = {}) {
  return { assessment: toAssessment(raw.assessment), feedback: toFeedbackSheet(raw.feedback) }
}
