import { fromCanonicalStatus } from '@/utils/canonicalStatus'
import { toRequestStatus } from './accreditedStatus'

export function toEntitySummary(entity) {
  if (!entity) return null
  return {
    id: entity.id,
    reference: entity.reference ?? '',
    name: entity.name ?? null,
    status: entity.status ?? null,
  }
}

function toResultSummary(result) {
  if (!result) return null
  return { total: result.total ?? null, maxTotal: result.maxTotal ?? null, percent: result.percent ?? null }
}

export function toAssessment(raw = {}) {
  return {
    id: raw.id ?? null,
    number: raw.number ?? '',
    path: raw.path ?? null,
    status: toRequestStatus(raw.status),
    isOpen: raw.isOpen === true,
    isFrozen: raw.isFrozen === true,
    criteriaUpdateInProgress: raw.criteriaUpdateInProgress === true,
    criteriaVersionId: raw.criteriaVersionId ?? null,
    scaleKind: raw.scaleKind ?? null,
    awardCycleId: raw.awardCycleId ?? null,
    entity: toEntitySummary(raw.entity),
    result: toResultSummary(raw.result),
    createdAt: raw.createdAt ?? null,
    updatedAt: raw.updatedAt ?? null,
    submittedAt: raw.submittedAt ?? null,
    completedAt: raw.completedAt ?? null,
  }
}

function toActorStaff(raw) {
  if (!raw) return null
  return { name: raw.name ?? null, roles: (raw.roles ?? []).map((role) => ({ key: role.key, label: role.label ?? null })) }
}

function toStatusChange(raw) {
  const change = {
    from: toRequestStatus(raw.from),
    to: toRequestStatus(raw.to),
    verb: raw.verb ?? null,
    at: raw.at ?? null,
    actor: raw.actor ?? null,
  }
  return Object.hasOwn(raw, 'actorStaff') ? { ...change, actorStaff: toActorStaff(raw.actorStaff) } : change
}

export function toAssessmentRecord(raw = {}) {
  return { ...toAssessment(raw), statusHistory: (raw.statusHistory ?? []).map(toStatusChange) }
}

function toUpdateItem(raw) {
  return { level: raw.level ?? null, code: raw.code ?? '', label: raw.label ?? null }
}

export function toCriteriaUpdate(raw) {
  if (!raw) return null
  return {
    fromVersionId: raw.fromVersionId ?? null,
    fromVersionNumber: raw.fromVersionNumber ?? null,
    toVersionId: raw.toVersionId ?? null,
    toVersionNumber: raw.toVersionNumber ?? null,
    movedAt: raw.movedAt ?? null,
    scope: raw.scope ?? null,
    trigger: raw.trigger ?? null,
    kept: (raw.kept ?? []).map(toUpdateItem),
    reset: (raw.reset ?? []).map(toUpdateItem),
    added: (raw.added ?? []).map(toUpdateItem),
  }
}

function toScoreMap(scores) {
  return Object.fromEntries(
    Object.entries(scores ?? {}).map(([id, score]) => [
      id,
      { rawValue: score?.rawValue ?? null, percent: score?.percent ?? null },
    ]),
  )
}

export function toAssessmentScores(raw = {}) {
  return {
    assessment: toAssessment(raw.assessment),
    scale: { kind: fromCanonicalStatus(raw.scale?.kind ?? null), maximum: raw.scale?.maximum ?? null },
    scores: { subs: toScoreMap(raw.scores?.subs), points: toScoreMap(raw.scores?.points) },
    progress: { scored: raw.progress?.scored ?? 0, required: raw.progress?.required ?? 0 },
  }
}

export function toAssessmentForm(raw = {}) {
  return { ...toAssessmentScores(raw), criteria: raw.criteria ?? null, criteriaUpdate: toCriteriaUpdate(raw.criteriaUpdate) }
}

export function toAssessmentResult(raw = {}) {
  const identity = {
    id: raw.id ?? null,
    number: raw.number ?? '',
    path: raw.path ?? null,
    status: toRequestStatus(raw.status),
    isFrozen: raw.isFrozen === true,
    available: raw.available === true,
  }
  if (!identity.available) return identity
  return {
    ...identity,
    criteriaVersionId: raw.criteriaVersionId ?? null,
    scaleKind: raw.scaleKind ?? null,
    resultVersion: raw.resultVersion ?? null,
    submittedAt: raw.submittedAt ?? null,
    completedAt: raw.completedAt ?? null,
    total: raw.total ?? null,
    maxTotal: raw.maxTotal ?? null,
    percent: raw.percent ?? null,
    main: raw.main ?? [],
    sub: raw.sub ?? [],
    points: raw.points ?? [],
  }
}
