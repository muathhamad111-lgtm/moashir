import { CRITERIA_VERSION_STATUSES } from '@/constants/criteria'
import { ASSESSMENT_PATHS, SELF_STATUSES } from '@/constants/assessment'
import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { versionWithStatus } from '../criteriaStore'
import { findEntity, isDeleted } from '../entitiesStore'
import { assessmentReject, fieldMessage } from './assessmentRejection'
import {
  boundTree,
  entityOf,
  insertAssessment,
  nextRequestNumber,
  openSelfDraftsOf,
  sheetOf,
  snapshotOf,
} from './assessmentsStore'
import { parseScoreSheet, validateScoreRequest } from './scoreInput'
import { scorableUnits, unitKey } from './selfScoring'
import { recordStart, recordSubmission } from './statusHistory'

const ACTIVE_ENTITY_STATUS = 'active'

const VERBS = { SAVE_DRAFT: 'save_draft', SUBMIT: 'submit' }

function now() {
  return new Date().toISOString()
}

function assertEntityMayAct(entity) {
  if (isDeleted(entity)) throw assessmentReject.entityDeleted()
  if (entity.status !== ACTIVE_ENTITY_STATUS) throw assessmentReject.entityNotActive()
}

function enterWriteGate(user, record, { verb, reason }) {
  assertEntityMayAct(entityOf(record))
  if (user.superAdmin && !reason) throw assessmentReject.reasonRequired()
  if (record.status !== SELF_STATUSES.DRAFT)
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), verb)
}

function validateStart(body) {
  if (body.path === ASSESSMENT_PATHS.SELF) return
  const code =
    body.path === undefined || body.path === null || body.path === ''
      ? 'validation.required'
      : 'validation.invalid'
  throw assessmentReject.validation({ path: [fieldMessage(code, {}, 'path')] })
}

export function startSelfAssessment(user, body) {
  validateStart(body)
  const entity = user.entityId === null ? null : findEntity(user.entityId)
  if (!entity) throw assessmentReject.entityRequired()
  assertEntityMayAct(entity)
  const version = versionWithStatus(CRITERIA_VERSION_STATUSES.PUBLISHED)
  if (!version) throw assessmentReject.noPublishedVersion()
  const existing = openSelfDraftsOf(entity.id)[0]
  if (existing) throw assessmentReject.draftExists(existing.id)
  const timestamp = now()
  const record = insertAssessment({
    number: nextRequestNumber(),
    entityId: entity.id,
    path: ASSESSMENT_PATHS.SELF,
    status: SELF_STATUSES.DRAFT,
    criteriaVersionId: version.id,
    scaleKind: version.scaleKind,
    scores: {},
    result: null,
    createdAt: timestamp,
    updatedAt: timestamp,
    submittedAt: null,
    completedAt: null,
    criteriaUpdatePendingSince: null,
    criteriaMoves: [],
  })
  recordStart(record, user, timestamp)
  return record
}

function recordSheet(record, sheet) {
  record.scores = Object.fromEntries(sheet)
  record.updatedAt = now()
}

export function saveSelfDraft(user, record, body) {
  const input = validateScoreRequest(body, record, 'present')
  enterWriteGate(user, record, { verb: VERBS.SAVE_DRAFT, reason: input.reason })
  recordSheet(record, parseScoreSheet(input.scores, boundTree(record), record.scaleKind))
  return record
}

function missingUnits(record, tree) {
  const sheet = sheetOf(record)
  return scorableUnits(tree).filter((unit) => !sheet.has(unitKey(unit.level, unit.id)))
}

export function submitSelfAssessment(user, record, body) {
  const input = validateScoreRequest(body, record, 'sometimes')
  enterWriteGate(user, record, { verb: VERBS.SUBMIT, reason: input.reason })
  if (record.criteriaUpdatePendingSince !== null) throw assessmentReject.criteriaUpdateInProgress()
  const tree = boundTree(record)
  const sheet = input.scores
    ? parseScoreSheet(input.scores, tree, record.scaleKind)
    : sheetOf(record)
  const draft = { ...record, scores: Object.fromEntries(sheet) }
  const missing = missingUnits(draft, tree)
  if (missing.length) throw assessmentReject.scoresIncomplete(missing)
  const timestamp = now()
  Object.assign(record, {
    scores: draft.scores,
    status: SELF_STATUSES.RESULT_AVAILABLE,
    result: snapshotOf(draft, tree),
    submittedAt: timestamp,
    completedAt: timestamp,
    updatedAt: timestamp,
  })
  recordSubmission(record, user, timestamp)
  return record
}
