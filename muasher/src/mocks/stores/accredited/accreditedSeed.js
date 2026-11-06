import { ASSESSMENT_PATHS } from '@/constants/assessment'
import {
  ACCREDITED_FIXTURE_FOLLOWER_ID,
  accreditedRequestsFixture,
  cvTextsFixture,
  feedbackFixture,
} from '../../fixtures/accreditedRequests'
import { versionTree } from '../criteriaSerializers'
import { fixtureScores } from '../assessments/fixtureScores'
import { computeSelfResult } from '../assessments/selfScoring'
import { ACTORS, transition } from '../assessments/statusHistory'
import { assessmentSettings } from './assessmentSettingsStore'
import { STATES } from './accreditedStates'
import { addDays } from './dates'

const FINANCE_OFFICER_ID = 22

const MIME_TYPES = {
  pdf: 'application/pdf',
  xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  jpg: 'image/jpeg',
  png: 'image/png',
}

function seededUuid(recordId, index) {
  return `7c2e5a10-${String(recordId).padStart(4, '0')}-4b6d-9e8f-${String(index + 1).padStart(12, '0')}`
}

function mimeOf(name) {
  return MIME_TYPES[name.split('.').pop()] ?? 'application/pdf'
}

function evidenceRows(fixture) {
  return fixture.evidence.map((entry, index) => ({
    id: entry.id ?? seededUuid(fixture.id, index),
    subCode: entry.subCode,
    attachmentKey: entry.attachmentKey,
    fileName: entry.name,
    mimeType: mimeOf(entry.name),
    sizeBytes: 180000 + entry.order * 7300,
    scanStatus: entry.scanStatus,
    status: 'uploaded',
    storedFileId: null,
    createdAt: addDays(fixture.createdAt, 1),
  }))
}

function cvOf(fixture, tree) {
  const cvCodes = new Set(
    tree.mains.flatMap((main) => main.subs.filter((sub) => sub.hasCv).map((sub) => sub.code)),
  )
  return Object.fromEntries(
    fixture.cvCodes.filter((code) => cvCodes.has(code)).map((code) => [code, cvTextsFixture[code]]),
  )
}

function feedbackOf(fixture) {
  return Object.fromEntries(
    fixture.feedbackCodes.map((code) => [code, { ...feedbackFixture[code] }]),
  )
}

function assignmentRows(fixture) {
  return fixture.assignments.map((entry, index) => ({
    id: fixture.id * 10 + index,
    assessorId: entry.assessorId,
    assignedById: ACCREDITED_FIXTURE_FOLLOWER_ID,
    status: entry.status,
    reason: entry.reason ?? null,
    startedAt: entry.startedAt,
    endedAt: entry.endedAt,
    endReason: entry.endReason ?? null,
    createdAt: entry.assignedAt,
  }))
}

function completionRows(fixture) {
  return fixture.completionRequests.map((entry, index) => ({
    id: fixture.id * 10 + index,
    status: entry.status,
    notes: entry.notes,
    items: entry.items.map((item) => ({ ...item })),
    deadlineAt: entry.deadlineAt,
    expiryOutcome: entry.expiryOutcome,
    createdAt: entry.requestedAt,
    resolvedAt: entry.resolvedAt,
  }))
}

function openingSteps(record) {
  return [
    transition(null, STATES.AWAITING_PAYMENT, 'start', record.createdAt, ACTORS.SYSTEM),
    transition(
      STATES.AWAITING_PAYMENT,
      STATES.DRAFT,
      'open_draft',
      record.createdAt,
      ACTORS.SYSTEM,
    ),
  ]
}

function assignmentSteps(record) {
  return record.assignments.flatMap((assignment, index) => {
    const steps = [
      index === 0
        ? transition(
            STATES.SUBMITTED,
            STATES.ASSIGNED,
            'assign',
            assignment.createdAt,
            ACTORS.STAFF,
            assignment.assignedById,
          )
        : transition(
            STATES.ASSIGNED,
            STATES.ASSIGNED,
            'reassign',
            assignment.createdAt,
            ACTORS.STAFF,
            assignment.assignedById,
          ),
    ]
    if (assignment.startedAt) {
      steps.push(
        transition(
          STATES.ASSIGNED,
          STATES.UNDER_REVIEW,
          'start_review',
          assignment.startedAt,
          ACTORS.STAFF,
          assignment.assessorId,
        ),
      )
    }
    return steps
  })
}

function closingSteps(record) {
  const assessor = record.assignments.at(-1)?.assessorId ?? null
  const steps = record.completionRequests.map((completion) =>
    transition(
      STATES.UNDER_REVIEW,
      STATES.NEEDS_COMPLETION,
      'request_completion',
      completion.createdAt,
      ACTORS.STAFF,
      assessor,
    ),
  )
  if (record.status === STATES.COMPLETED) {
    steps.push(
      transition(
        STATES.UNDER_REVIEW,
        STATES.COMPLETED,
        'complete',
        record.completedAt,
        ACTORS.STAFF,
        assessor,
      ),
    )
  }
  if (record.status === STATES.REFUNDED) {
    steps.push(
      transition(
        STATES.UNDER_REVIEW,
        STATES.REFUNDED,
        'refund',
        record.refundedAt,
        ACTORS.STAFF,
        FINANCE_OFFICER_ID,
      ),
    )
  }
  return steps
}

function seedAccreditedTransitions(record) {
  const steps = openingSteps(record)
  if (record.submittedAt)
    steps.push(
      transition(STATES.DRAFT, STATES.SUBMITTED, 'submit', record.submittedAt, ACTORS.ENTITY),
    )
  return [...steps, ...assignmentSteps(record), ...closingSteps(record)]
}

function snapshotFor(record, tree) {
  const sheet = new Map(Object.entries(record.scores))
  return {
    version: 1,
    criteriaVersionId: record.criteriaVersionId,
    scaleKind: record.scaleKind,
    ...computeSelfResult(tree, sheet, record.scaleKind),
  }
}

function accreditedRecord(fixture, version) {
  const tree = versionTree(version)
  const slaDays = assessmentSettings().accreditedReviewSlaDays
  const record = {
    id: fixture.id,
    number: fixture.number,
    entityId: fixture.entityId,
    path: ASSESSMENT_PATHS.ACCREDITED,
    status: fixture.status,
    paymentId: fixture.paymentId,
    criteriaVersionId: version.id,
    scaleKind: version.scaleKind,
    scores: fixtureScores(tree, fixture.scoring),
    result: null,
    cv: cvOf(fixture, tree),
    evidence: evidenceRows(fixture),
    feedback: feedbackOf(fixture),
    assignments: assignmentRows(fixture),
    completionRequests: completionRows(fixture),
    bundles: [],
    createdAt: fixture.createdAt,
    updatedAt:
      fixture.completedAt ?? fixture.refundedAt ?? fixture.submittedAt ?? fixture.createdAt,
    submittedAt: fixture.submittedAt,
    completedAt: fixture.completedAt,
    refundedAt: fixture.refundedAt,
    dueAt: fixture.submittedAt ? addDays(fixture.submittedAt, slaDays) : null,
    reviewSlaDays: fixture.submittedAt ? slaDays : null,
    overdueNotifiedAt: null,
    criteriaUpdatePendingSince: null,
    criteriaMoves: [],
  }
  if (record.status === STATES.COMPLETED) record.result = snapshotFor(record, tree)
  record.transitions = seedAccreditedTransitions(record)
  return record
}

export function accreditedSeedRecords(version) {
  return accreditedRequestsFixture.map((fixture) => accreditedRecord(fixture, version))
}
