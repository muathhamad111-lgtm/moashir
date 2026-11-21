import { toAssessment, toAssessmentScores, toCriteriaUpdate, toEntitySummary } from './assessments'
import { toCompletionRequest, toCvSheet, toEvidenceList, toFeedbackFields } from './accredited'
import { toClock, toPlace } from './requests'

export function toReviewEntity(raw) {
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
  return Object.fromEntries(
    Object.entries(raw ?? {}).map(([subId, fields]) => [subId, toFeedbackFields(fields)]),
  )
}

function toContextItem(raw) {
  return {
    id: raw.id ?? null,
    number: raw.number ?? '',
    path: raw.path ?? null,
    pathLabel: raw.pathLabel ?? null,
    completedAt: raw.completedAt ?? null,
    percent: raw.percent ?? null,
  }
}

export function toAssessorSummary(raw) {
  if (!raw?.body) return null
  return { body: raw.body, version: raw.version ?? null, updatedAt: raw.updatedAt ?? null }
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
    lateAttachmentKeys: raw.lateAttachmentKeys ?? [],
    feedback: toFeedbackSheet(raw.feedback),
    completionRequest: toCompletionRequest(raw.completionRequest),
    context: (raw.context ?? []).map(toContextItem),
    summary: toAssessorSummary(raw.summary),
    criteriaUpdate: toCriteriaUpdate(raw.criteriaUpdate),
  }
}

export function toFeedbackSaved(raw = {}) {
  return { assessment: toAssessment(raw.assessment), feedback: toFeedbackSheet(raw.feedback) }
}

function toRoundScore(raw) {
  return {
    round: raw.round ?? 1,
    completionRequestId: raw.completionRequestId ?? null,
    rawValue: raw.rawValue ?? null,
    percent: raw.percent ?? null,
    scoredAt: raw.scoredAt ?? null,
  }
}

// The completion rounds of a review (design §B.3.3): every completion request, and each item's
// last score per round, keyed by its field name (point-31, sub-12).
export function toReviewRounds(raw) {
  return {
    requests: (raw?.requests ?? []).map((request) => ({
      ...toCompletionRequest(request),
      round: request.round ?? null,
    })),
    units: Object.fromEntries(
      Object.entries(raw?.units ?? {}).map(([field, rounds]) => [field, rounds.map(toRoundScore)]),
    ),
  }
}

function toResultLevel(raw) {
  return { percent: raw?.percent ?? null, level: raw?.level ?? null }
}

// An override preview (design §B.3.5): the published total and level, the new ones, and the items
// whose value changes.
export function toOverridePreview(raw) {
  return {
    before: toResultLevel(raw?.before),
    after: toResultLevel(raw?.after),
    changes: (raw?.changes ?? []).map((change) => ({
      code: change.code ?? null,
      field: change.field ?? null,
      before: change.before ?? null,
      after: change.after ?? null,
    })),
  }
}
