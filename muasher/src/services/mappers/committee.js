import { fromCanonicalStatus } from '@/utils/canonicalStatus'
import { SECRETARY_STATUSES } from '@/constants/committee'
import { toEntitySummary, toScoreMap } from './assessments'
import { toAccreditedResult, toCvSheet, toEvidenceList } from './accredited'
import { toRequestStatus, toStatusCounts } from './accreditedStatus'
import { toCycleSummary, toPlace } from './requests'
import { toReviewEntity } from './review'

function toPerson(raw) {
  if (!raw) return null
  return { id: raw.id ?? null, name: raw.name ?? null }
}

function toProgress(raw = {}) {
  return {
    notStarted: raw.notStarted ?? 0,
    inProgress: raw.inProgress ?? 0,
    completed: raw.completed ?? 0,
    voided: raw.voided ?? 0,
  }
}

export function toMember(raw = {}) {
  return {
    id: raw.id ?? null,
    user: toPerson(raw.user),
    status: raw.status ?? null,
    isSubstitute: raw.isSubstitute === true,
    replacesId: raw.replacesId ?? null,
    replacedById: raw.replacedById ?? null,
    appointedAt: raw.appointedAt ?? null,
    disabledAt: raw.disabledAt ?? null,
    disabledReason: raw.disabledReason ?? null,
    removedAt: raw.removedAt ?? null,
    progress: toProgress(raw.progress),
  }
}

export function toRosterOutcome(raw = {}) {
  return {
    affectedRequestIds: raw.affectedRequestIds ?? [],
    recomputedRequestIds: raw.recomputedRequestIds ?? [],
  }
}

export function toCommitteeEntity(raw) {
  if (!raw) return null
  const base = { visibility: raw.visibility ?? null, region: toPlace(raw.region) }
  if (Object.hasOwn(raw, 'code')) return { ...base, code: raw.code ?? '', name: null }
  if (Object.hasOwn(raw, 'reference')) return { ...toReviewEntity(raw), ...base, code: null }
  return { ...base, code: null, name: raw.name ?? null }
}

export function toAssignmentRow(raw = {}) {
  return {
    id: raw.id ?? null,
    assessmentId: raw.assessmentId ?? null,
    cycle: toCycleSummary(raw.cycle),
    entity: toCommitteeEntity(raw.entity),
    status: raw.status ?? null,
    membershipStatus: raw.membershipStatus ?? null,
    isSubstitute: raw.isSubstitute === true,
    substituteApprovedAt: raw.substituteApprovedAt ?? null,
    dueAt: raw.dueAt ?? null,
    isOverdue: raw.isOverdue === true,
    percent: raw.percent ?? null,
    startedAt: raw.startedAt ?? null,
    submittedAt: raw.submittedAt ?? null,
    returnedAt: raw.returnedAt ?? null,
  }
}

export function toCommitteeScores(raw = {}) {
  return {
    assignmentId: raw.assignmentId ?? null,
    status: raw.status ?? null,
    scale: {
      kind: fromCanonicalStatus(raw.scale?.kind ?? null),
      maximum: raw.scale?.maximum ?? null,
    },
    scores: { subs: toScoreMap(raw.scores?.subs), points: toScoreMap(raw.scores?.points) },
    progress: { scored: raw.progress?.scored ?? 0, required: raw.progress?.required ?? 0 },
    percent: raw.percent ?? null,
  }
}

function toNotes(raw) {
  return { subs: toCvSheet(raw?.subs), general: raw?.general ?? '' }
}

function toPrior(raw) {
  if (!raw) return null
  return {
    scores: { subs: toScoreMap(raw.scores?.subs), points: toScoreMap(raw.scores?.points) },
    progress: { scored: raw.progress?.scored ?? 0, required: raw.progress?.required ?? 0 },
    percent: raw.percent ?? null,
    status: raw.status ?? null,
    notes: toNotes(raw.notes),
  }
}

function toReturnNote(raw) {
  if (!raw) return null
  return { note: raw.note ?? '', returnedAt: raw.returnedAt ?? null }
}

export function toEvaluation(raw = {}) {
  return {
    ...toCommitteeScores(raw),
    assignment: toAssignmentRow(raw.assignment),
    mode: raw.mode ?? null,
    criteria: raw.criteria ?? null,
    cv: toCvSheet(raw.cv),
    evidence: toEvidenceList(raw.evidence),
    notes: toNotes(raw.notes),
    returnNote: toReturnNote(raw.returnNote),
    prior: toPrior(raw.prior),
  }
}

export function toNotesSaved(raw = {}) {
  return {
    assignmentId: raw.assignmentId ?? null,
    subs: toCvSheet(raw.subs),
    general: raw.general ?? '',
  }
}

export function toSubmission(raw = {}) {
  return {
    id: raw.id ?? null,
    status: raw.status ?? null,
    percent: raw.percent ?? null,
    submittedAt: raw.submittedAt ?? null,
  }
}

export function toAverage(raw) {
  if (!raw) return null
  return {
    id: raw.id ?? null,
    version: raw.version ?? null,
    percent: raw.percent ?? null,
    memberCount: raw.memberCount ?? 0,
    isManual: raw.isManual === true,
    computedAt: raw.computedAt ?? null,
  }
}

function toInitialResult(raw) {
  if (!raw) return null
  return { total: raw.total ?? null, maxTotal: raw.maxTotal ?? null, percent: raw.percent ?? null }
}

export function toSecretaryRow(raw = {}) {
  return {
    id: raw.id ?? null,
    number: raw.number ?? '',
    status: toRequestStatus(raw.status),
    isFrozen: raw.isFrozen === true,
    entity: toEntitySummary(raw.entity),
    region: toPlace(raw.region),
    initialPercent: raw.initialPercent ?? null,
    average: toAverage(raw.average),
    committee: {
      assignments: raw.committee?.assignments ?? 0,
      completed: raw.committee?.completed ?? 0,
      pendingSubstitutions: raw.committee?.pendingSubstitutions ?? 0,
    },
    submittedAt: raw.submittedAt ?? null,
  }
}

export function toSecretaryMeta(raw = {}) {
  return {
    counts: toStatusCounts(raw.counts, SECRETARY_STATUSES),
    pagination: raw.pagination ?? null,
  }
}

function toEntry(raw = {}) {
  return {
    id: raw.id ?? null,
    member: raw.member ? toPerson(raw.member) : null,
    kind: raw.kind ?? null,
    body: raw.body ?? null,
    isMine: raw.isMine === true,
    at: raw.at ?? null,
  }
}

function discussionHeader(raw) {
  return {
    id: raw.id ?? null,
    assessmentId: raw.assessmentId ?? null,
    mode: raw.mode ?? null,
    status: raw.status ?? null,
    deadlineAt: raw.deadlineAt ?? null,
    agreedOutcome: raw.agreedOutcome ?? null,
    openedAt: raw.openedAt ?? null,
    closedAt: raw.closedAt ?? null,
    entries: (raw.entries ?? []).map(toEntry),
  }
}

export function toDiscussion(raw = {}) {
  return {
    ...discussionHeader(raw),
    openedBy: toPerson(raw.openedBy),
    summary: raw.summary ?? null,
  }
}

export function toMemberDiscussion(raw = {}) {
  return {
    ...discussionHeader(raw),
    entity: toCommitteeEntity(raw.entity),
    acceptsEntries: raw.acceptsEntries === true,
    myEntry: raw.myEntry ? toEntry(raw.myEntry) : null,
  }
}

export function toDiscussionEntry(raw = {}) {
  return {
    id: raw.id ?? null,
    discussionId: raw.discussionId ?? null,
    kind: raw.kind ?? null,
    body: raw.body ?? null,
    at: raw.at ?? null,
  }
}

function toEvaluationSummary(raw = {}) {
  return {
    id: raw.id ?? null,
    member: {
      id: raw.member?.id ?? null,
      user: toPerson(raw.member?.user),
      status: raw.member?.status ?? null,
    },
    status: raw.status ?? null,
    counted: raw.counted === true,
    isSubstitute: raw.isSubstitute === true,
    replacesAssignmentId: raw.replacesAssignmentId ?? null,
    substituteApprovedAt: raw.substituteApprovedAt ?? null,
    percent: raw.percent ?? null,
    startedAt: raw.startedAt ?? null,
    submittedAt: raw.submittedAt ?? null,
    returnedAt: raw.returnedAt ?? null,
    voidedAt: raw.voidedAt ?? null,
    voidReason: raw.voidReason ?? null,
  }
}

function toDecision(raw = {}) {
  return {
    id: raw.id ?? null,
    kind: raw.kind ?? null,
    note: raw.note ?? null,
    committeeAverageId: raw.committeeAverageId ?? null,
    decidedBy: toPerson(raw.decidedBy),
    decidedAt: raw.decidedAt ?? null,
  }
}

export function toSecretaryDetail(raw = {}) {
  return {
    id: raw.id ?? null,
    number: raw.number ?? '',
    status: toRequestStatus(raw.status),
    entity: toEntitySummary(raw.entity),
    region: toPlace(raw.region),
    initialResult: toInitialResult(raw.initialResult),
    average: toAverage(raw.average),
    allSubmitted: raw.allSubmitted === true,
    pendingAssignmentIds: raw.pendingAssignmentIds ?? [],
    pendingSubstitutionIds: raw.pendingSubstitutionIds ?? [],
    evaluations: (raw.evaluations ?? []).map(toEvaluationSummary),
    decisions: (raw.decisions ?? []).map(toDecision),
    discussions: (raw.discussions ?? []).map(toDiscussion),
  }
}

export function toSecretaryEvaluation(raw = {}) {
  return {
    ...toCommitteeScores(raw),
    id: raw.id ?? null,
    member: {
      id: raw.member?.id ?? null,
      user: toPerson(raw.member?.user),
      status: raw.member?.status ?? null,
    },
    isSubstitute: raw.isSubstitute === true,
    submittedAt: raw.submittedAt ?? null,
    notes: toNotes(raw.notes),
    result: {
      total: raw.result?.total ?? null,
      maxTotal: raw.result?.maxTotal ?? null,
      percent: raw.result?.percent ?? null,
      main: raw.result?.main ?? [],
      sub: raw.result?.sub ?? [],
      points: raw.result?.points ?? [],
    },
  }
}

export function toResultRow(raw = {}) {
  return {
    assessmentId: raw.assessmentId ?? null,
    number: raw.number ?? '',
    status: toRequestStatus(raw.status),
    entity: toEntitySummary(raw.entity),
    region: toPlace(raw.region),
    averagePercent: raw.averagePercent ?? null,
    regionalRank: raw.regionalRank ?? null,
    nationalRank: raw.nationalRank ?? null,
    approvedAt: raw.approvedAt ?? null,
  }
}

function toRegionBoard(raw = {}) {
  return {
    ...toPlace(raw),
    approved: raw.approved === true,
    approvedAt: raw.approvedAt ?? null,
    total: raw.total ?? 0,
    ranked: raw.ranked ?? 0,
    pending: raw.pending ?? 0,
  }
}

export function toResultsMeta(raw = {}) {
  return {
    cycleStatus: raw.cycleStatus ?? null,
    regions: (raw.regions ?? []).map(toRegionBoard),
    national: {
      approved: raw.national?.approved === true,
      approvedAt: raw.national?.approvedAt ?? null,
      announcedAt: raw.national?.announcedAt ?? null,
    },
  }
}

export function toAwardOutcome(raw) {
  if (!raw) return null
  return {
    status: toRequestStatus(raw.status),
    region: toPlace(raw.region),
    regionalRank: raw.regionalRank ?? null,
    nationalRank: raw.nationalRank ?? null,
  }
}

export function toAdvancedResult(raw = {}) {
  const result = toAccreditedResult(raw)
  if (!result.available) return result
  return { ...result, award: toAwardOutcome(raw.award) }
}
