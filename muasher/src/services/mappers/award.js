import { toAssessment, toEntitySummary } from './assessments'
import { toAccreditedResult } from './accredited'
import { toAssessorReview } from './review'
import { toPlace } from './requests'
import { toPriceQuote } from './paths'

function toPerson(raw) {
  if (!raw) return null
  return { id: raw.id ?? null, name: raw.name ?? null }
}

function toCounts(raw) {
  if (!raw) return null
  return { requests: raw.requests ?? 0, qualified: raw.qualified ?? 0 }
}

export function toCycle(raw = {}) {
  return {
    id: raw.id ?? null,
    name: raw.name ?? null,
    year: raw.year ?? null,
    status: raw.status ?? null,
    criteriaVersionId: raw.criteriaVersionId ?? null,
    scaleKind: raw.scaleKind ?? null,
    submissionStart: raw.submissionStart ?? null,
    submissionEnd: raw.submissionEnd ?? null,
    committeeStart: raw.committeeStart ?? null,
    committeeEnd: raw.committeeEnd ?? null,
    announcementAt: raw.announcementAt ?? null,
    conditions: {
      minEntityAgeYears: raw.conditions?.minEntityAgeYears ?? null,
      disqualificationScore: raw.conditions?.disqualificationScore ?? null,
    },
    initialReviewSlaDays: raw.initialReviewSlaDays ?? null,
    participationCertificateEnabled: raw.participationCertificateEnabled === true,
    entryFee: raw.entryFee ?? null,
    entryFeeQuote: toPriceQuote(raw.entryFeeQuote),
    entryFeeEditable: raw.entryFeeEditable === true,
    regions: (raw.regions ?? []).map(toPlace),
    counts: toCounts(raw.counts),
    openedAt: raw.openedAt ?? null,
    submissionsClosedAt: raw.submissionsClosedAt ?? null,
    closedAt: raw.closedAt ?? null,
    createdAt: raw.createdAt ?? null,
    updatedAt: raw.updatedAt ?? null,
  }
}

function toMember(raw) {
  return { id: raw.id ?? null, name: raw.name ?? null, role: raw.role ?? null }
}

function toLinkedAssessor(raw) {
  return { id: raw.id ?? null, name: raw.name ?? null, isActive: raw.isActive === true }
}

export function toCycleDetail(raw = {}) {
  const detail = { ...toCycle(raw), canManage: raw.canManage === true }
  if (!Object.hasOwn(raw, 'members'))
    return { ...detail, revealsStaff: false, members: [], initialAssessors: [] }
  return {
    ...detail,
    revealsStaff: true,
    members: (raw.members ?? []).map(toMember),
    initialAssessors: (raw.initialAssessors ?? []).map(toLinkedAssessor),
  }
}

export function toCycleStats(raw) {
  if (!raw) return null
  return { total: raw.total ?? 0, byStatus: raw.byStatus ?? {} }
}

export function toPublicCycle(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    name: raw.name ?? null,
    year: raw.year ?? null,
    status: raw.status ?? null,
    isAcceptingApplications: raw.isAcceptingApplications === true,
    submissionStart: raw.submissionStart ?? null,
    submissionEnd: raw.submissionEnd ?? null,
    committeeStart: raw.committeeStart ?? null,
    committeeEnd: raw.committeeEnd ?? null,
    announcementAt: raw.announcementAt ?? null,
    conditions: { minEntityAgeYears: raw.conditions?.minEntityAgeYears ?? null },
    regions: (raw.regions ?? []).map(toPlace),
  }
}

export function toCycleDefaults(raw = {}) {
  return {
    minEntityAgeYears: raw.minEntityAgeYears ?? null,
    disqualificationScore: raw.disqualificationScore ?? null,
    initialReviewSlaDays: raw.initialReviewSlaDays ?? null,
    entryFee: raw.entryFee ?? null,
  }
}

export function toCandidate(raw = {}) {
  return { id: raw.id ?? null, name: raw.name ?? '' }
}

export function toConditionCheck(raw = {}) {
  return {
    rule: raw.rule ?? null,
    passed: raw.passed === true,
    required: raw.required ?? null,
    actual: raw.actual ?? null,
  }
}

function toAdvancedRequest(raw) {
  if (!raw) return null
  return {
    ...toAssessment(raw),
    awardCycleId: raw.awardCycleId ?? null,
    conditionFailures: (raw.conditionFailure?.failures ?? []).map(toConditionCheck),
  }
}

export function toAdvancedCurrent(raw = {}) {
  return {
    cycle: toPublicCycle(raw.cycle),
    eligibility: {
      eligible: raw.eligibility?.eligible === true,
      windowOpen: raw.eligibility?.windowOpen === true,
      conditions: (raw.eligibility?.conditions ?? []).map(toConditionCheck),
    },
    request: toAdvancedRequest(raw.request),
    previous: toAdvancedRequest(raw.previous),
  }
}

export function toAdvancedAssessment(raw = {}) {
  return { ...toAssessment(raw), awardCycleId: raw.awardCycleId ?? null }
}

export function toFilteringDecision(raw = {}) {
  return {
    id: raw.id ?? null,
    outcome: raw.outcome ?? null,
    isOverride: raw.isOverride === true,
    initialPercent: raw.initialPercent ?? null,
    thresholdPercent: raw.thresholdPercent ?? null,
    reason: raw.reason ?? null,
    note: raw.note ?? null,
    decidedBy: toPerson(raw.decidedBy),
    decidedAt: raw.decidedAt ?? null,
  }
}

function toInitialResult(raw) {
  if (!raw) return null
  return { total: raw.total ?? null, maxTotal: raw.maxTotal ?? null, percent: raw.percent ?? null }
}

export function toFilteringRow(raw = {}) {
  return {
    id: raw.id ?? null,
    number: raw.number ?? '',
    status: raw.status ?? null,
    isFrozen: raw.isFrozen === true,
    entity: toEntitySummary(raw.entity),
    region: toPlace(raw.region),
    initialResult: toInitialResult(raw.initialResult),
    meetsThreshold: typeof raw.meetsThreshold === 'boolean' ? raw.meetsThreshold : null,
    initialAssessedAt: raw.initialAssessedAt ?? null,
    submittedAt: raw.submittedAt ?? null,
    decision: raw.decision ? toFilteringDecision(raw.decision) : null,
  }
}

export function toFilteringMeta(raw = {}) {
  return {
    disqualificationScore: raw.disqualificationScore ?? null,
    counts: {
      awaitingFiltering: raw.counts?.awaitingFiltering ?? 0,
      qualifiedForCommittee: raw.counts?.qualifiedForCommittee ?? 0,
      notQualified: raw.counts?.notQualified ?? 0,
    },
    belowThreshold: raw.belowThreshold ?? 0,
    pagination: raw.pagination ?? null,
  }
}

export function toFilteringDetail(raw = {}) {
  return {
    row: toFilteringRow(raw.row),
    disqualificationScore: raw.disqualificationScore ?? null,
    assessor: toPerson(raw.assessor),
    review: toAssessorReview(raw.review),
    result: toAccreditedResult(raw.result),
    decisions: (raw.decisions ?? []).map(toFilteringDecision),
  }
}

function toCycleBinding(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    name: raw.name ?? null,
    status: raw.status ?? null,
    criteriaVersionId: raw.criteriaVersionId ?? null,
    openRequests: raw.openRequests ?? 0,
  }
}

export function toCycleBindings(raw) {
  return {
    running: toCycleBinding(raw?.running),
    requestsStayOnCycleVersion: raw?.requestsStayOnCycleVersion !== false,
    drafts: (raw?.drafts ?? []).map(toCycleBinding),
  }
}
