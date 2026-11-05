import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { cvTextsFixture } from '../../fixtures/accreditedRequests'
import { AWARD_STAFF, advancedRequestsFixture } from '../../fixtures/awardCycles'
import { versionTree } from '../criteriaSerializers'
import { findEntity } from '../entitiesStore'
import { fixtureScores } from '../assessments/fixtureScores'
import { computeSelfResult, displayPercent } from '../assessments/selfScoring'
import { ACTORS, transition } from '../assessments/statusHistory'
import { addDays } from '../accredited/dates'
import { ADVANCED_STATES as S } from './advancedStates'
import { findCycle } from './cycleStore'

const RIYADH_OFFSET_MS = 3 * 60 * 60 * 1000
const MIME_TYPES = {
  pdf: 'application/pdf',
  xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  jpg: 'image/jpeg',
}
const ASSESSED = [S.AWAITING_FILTERING, S.QUALIFIED, S.NOT_QUALIFIED]

function riyadhYear(iso) {
  return new Date(new Date(iso).getTime() + RIYADH_OFFSET_MS).getUTCFullYear()
}

function numberOf(fixture, createdAt) {
  return `ADV-${riyadhYear(createdAt)}-${String(fixture.sequence).padStart(6, '0')}`
}

function seededUuid(recordId, index) {
  return `5a3c7e21-${String(recordId).padStart(4, '0')}-4c8d-8e9f-${String(index + 1).padStart(12, '0')}`
}

function evidenceRows(fixture, createdAt) {
  return fixture.evidence.map((entry, index) => ({
    id: seededUuid(fixture.id, index),
    subCode: entry.subCode,
    attachmentKey: entry.attachmentKey,
    fileName: entry.name,
    mimeType: MIME_TYPES[entry.name.split('.').pop()] ?? 'application/pdf',
    sizeBytes: 160000 + entry.order * 6100,
    scanStatus: entry.scanStatus,
    status: 'uploaded',
    storedFileId: null,
    createdAt: addDays(createdAt, 1),
  }))
}

function scoresOf(fixture, tree) {
  const { pattern, count } = fixture.scoring
  if (count === null || count >= 0) return fixtureScores(tree, { pattern, count })
  const all = fixtureScores(tree, { pattern, count: null })
  return Object.fromEntries(Object.entries(all).slice(0, count))
}

function assignmentsOf(fixture, anchor) {
  return fixture.assignments.map((entry, index) => ({
    id: fixture.id * 10 + index,
    assessorId: entry.assessorId,
    assignedById: AWARD_STAFF.follower,
    status: entry.status,
    reason: null,
    startedAt: entry.startedOffset === null ? null : addDays(anchor, entry.startedOffset),
    endedAt: entry.endedOffset === null ? null : addDays(anchor, entry.endedOffset),
    endReason: null,
    createdAt: addDays(anchor, entry.assignedOffset),
  }))
}

function completionsOf(fixture, anchor) {
  return fixture.completionRequests.map((entry, index) => ({
    id: fixture.id * 10 + index,
    status: entry.status,
    notes: entry.notes,
    items: entry.items.map((item) => ({ ...item })),
    deadlineAt: addDays(anchor, entry.deadlineOffset),
    expiryOutcome: entry.expiryOutcome,
    createdAt: addDays(anchor, entry.requestedOffset),
    resolvedAt: entry.status === 'open' ? null : addDays(anchor, entry.deadlineOffset),
  }))
}

function decisionsOf(fixture, record, cycle, anchor) {
  if (!fixture.decision) return []
  return [
    {
      id: fixture.id * 10,
      outcome: fixture.decision.outcome,
      isOverride: false,
      initialPercent: displayPercent(record.result.percent),
      thresholdPercent: cycle.disqualificationScore,
      reason: fixture.decision.reason,
      note: null,
      deciderId: fixture.decision.deciderId,
      decidedAt: addDays(anchor, fixture.decision.offset),
    },
  ]
}

function seedTransitions(record) {
  const steps = [transition(null, S.DRAFT, 'start', record.createdAt, ACTORS.ENTITY)]
  if (record.status === S.REJECTED_CONDITIONS)
    return [
      transition(null, S.REJECTED_CONDITIONS, 'reject_conditions', record.createdAt, ACTORS.ENTITY),
    ]
  if (record.submittedAt)
    steps.push(
      transition(S.DRAFT, S.AWAITING_ASSIGNMENT, 'submit', record.submittedAt, ACTORS.ENTITY),
    )
  record.completionRequests.forEach((completion) =>
    steps.push(
      transition(
        S.AWAITING_ASSIGNMENT,
        S.NEEDS_COMPLETION,
        'return_for_completion',
        completion.createdAt,
        ACTORS.STAFF,
        AWARD_STAFF.follower,
      ),
    ),
  )
  record.assignments.forEach((assignment) => {
    steps.push(
      transition(
        S.AWAITING_ASSIGNMENT,
        S.ASSIGNED,
        'assign',
        assignment.createdAt,
        ACTORS.STAFF,
        assignment.assignedById,
      ),
    )
    if (assignment.startedAt)
      steps.push(
        transition(
          S.ASSIGNED,
          S.UNDER_INITIAL_ASSESSMENT,
          'start_review',
          assignment.startedAt,
          ACTORS.STAFF,
          assignment.assessorId,
        ),
      )
    if (assignment.status === 'completed')
      steps.push(
        transition(
          S.UNDER_INITIAL_ASSESSMENT,
          S.AWAITING_FILTERING,
          'complete',
          assignment.endedAt,
          ACTORS.STAFF,
          assignment.assessorId,
        ),
      )
  })
  return steps
}

function closingSteps(record) {
  const decision = record.filteringDecisions.at(-1)
  if (decision)
    return [
      transition(
        S.AWAITING_FILTERING,
        record.status,
        decision.outcome === 'qualified' ? 'qualify' : 'disqualify',
        decision.decidedAt,
        ACTORS.STAFF,
        decision.deciderId,
      ),
    ]
  if (record.status === S.WITHDRAWN)
    return [
      transition(
        S.AWAITING_ASSIGNMENT,
        S.WITHDRAWN,
        'withdraw',
        record.withdrawnAt,
        ACTORS.STAFF,
        AWARD_STAFF.follower,
      ),
    ]
  if (record.status === S.DECLINED)
    return [
      transition(
        S.NEEDS_COMPLETION,
        S.DECLINED,
        'expire_completion',
        record.completionRequests.at(-1).resolvedAt,
        ACTORS.SYSTEM,
      ),
    ]
  return []
}

function snapshotOf(record, tree) {
  const sheet = new Map(Object.entries(record.scores))
  return {
    version: 1,
    criteriaVersionId: record.criteriaVersionId,
    scaleKind: record.scaleKind,
    ...computeSelfResult(tree, sheet, record.scaleKind),
  }
}

function advancedRecord(fixture, version, anchor) {
  const tree = versionTree(version)
  const cycle = findCycle(fixture.cycleId)
  const createdAt = addDays(anchor, fixture.offsets.createdAt)
  const assignments = assignmentsOf(fixture, anchor)
  const active = assignments.find((assignment) => assignment.status === 'active') ?? null
  const record = {
    id: fixture.id,
    number: numberOf(fixture, createdAt),
    entityId: fixture.entityId,
    path: ASSESSMENT_PATHS.ADVANCED,
    status: fixture.status,
    awardCycleId: cycle.id,
    regionId: findEntity(fixture.entityId)?.regionId ?? null,
    conditionFailure: fixture.conditionFailure ?? null,
    criteriaVersionId: version.id,
    scaleKind: cycle.scaleKind,
    scores: scoresOf(fixture, tree),
    result: null,
    cv: Object.fromEntries(fixture.cvCodes.map((code) => [code, cvTextsFixture[code]])),
    evidence: evidenceRows(fixture, createdAt),
    feedback: {},
    assignments,
    completionRequests: completionsOf(fixture, anchor),
    filteringDecisions: [],
    bundles: [],
    createdAt,
    updatedAt: createdAt,
    submittedAt:
      fixture.offsets.submittedAt === null ? null : addDays(anchor, fixture.offsets.submittedAt),
    completedAt: null,
    initialAssessedAt: null,
    withdrawnAt: fixture.withdrawal ? addDays(anchor, fixture.withdrawal.offset) : null,
    withdrawalReason: fixture.withdrawal?.reason ?? null,
    dueAt: active ? addDays(active.createdAt, cycle.initialReviewSlaDays) : null,
    reviewSlaDays: active ? cycle.initialReviewSlaDays : null,
    overdueNotifiedAt: null,
    criteriaUpdatePendingSince: null,
    criteriaMoves: [],
  }
  if (ASSESSED.includes(record.status)) {
    record.result = snapshotOf(record, tree)
    record.initialAssessedAt = assignments.at(-1).endedAt
  }
  record.filteringDecisions = decisionsOf(fixture, record, cycle, anchor)
  record.transitions = [...seedTransitions(record), ...closingSteps(record)]
  record.updatedAt = record.transitions.at(-1)?.at ?? createdAt
  return record
}

export function advancedSeedRecords(version, anchor = new Date().toISOString()) {
  return advancedRequestsFixture.map((fixture) => advancedRecord(fixture, version, anchor))
}
