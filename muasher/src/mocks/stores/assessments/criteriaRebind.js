import { CRITERIA_EDIT_SCOPES, CRITERIA_VERSION_STATUSES } from '@/constants/criteria'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { editEvents, findVersion, versionWithStatus } from '../criteriaStore'
import { versionTree } from '../criteriaSerializers'
import { registerCriteriaEditListener, registerOpenAssessmentCounter } from '../criteriaEditHooks'
import { registerEntityRestoreStep } from '../entityRestoreSteps'
import { dispatchQueuedJob } from '../mockQueue'
import { allAssessments, boundTree, isFrozen, isOpen, openRequestsOf } from './assessmentsStore'
import { scorableUnits, unitKey } from './selfScoring'
import { MOVE_TRIGGERS, recordMove } from './criteriaMoves'

const STRUCTURAL = 'structural'

const SCOPE_STRICTNESS = {
  [CRITERIA_EDIT_SCOPES.NEW_ONLY]: 0,
  [CRITERIA_EDIT_SCOPES.ALL]: 1,
  [CRITERIA_EDIT_SCOPES.RESET_OPEN]: 2,
}

function openBoundDrafts(versionId) {
  return allAssessments().filter(
    (record) =>
      record.path !== ASSESSMENT_PATHS.ADVANCED &&
      isOpen(record) &&
      !isFrozen(record) &&
      record.criteriaVersionId === versionId,
  )
}

function carriedScores(record, toTree, rescoredCodes) {
  const fromUnits = new Map(
    scorableUnits(boundTree(record)).map((unit) => [unitKey(unit.level, unit.id), unit]),
  )
  const targets = new Map(scorableUnits(toTree).map((unit) => [unit.code, unit]))
  const rescored = new Set(rescoredCodes)
  return Object.fromEntries(
    Object.entries(record.scores).flatMap(([key, raw]) => {
      const unit = fromUnits.get(key)
      const target = unit && !rescored.has(unit.code) ? targets.get(unit.code) : null
      return target && target.level === unit.level ? [[unitKey(target.level, target.id), raw]] : []
    }),
  )
}

function rebind(record, { toVersionId, scope, rescoredCodes, trigger }) {
  const fromTree = boundTree(record)
  const previousScores = record.scores
  const toTree = versionTree(findVersion(toVersionId))
  record.scores =
    scope === CRITERIA_EDIT_SCOPES.ALL ? carriedScores(record, toTree, rescoredCodes) : {}
  record.criteriaVersionId = toVersionId
  record.updatedAt = new Date().toISOString()
  recordMove(record, { fromTree, toTree, previousScores, scope, trigger })
}

function rebindAfterCriteriaEdit(event) {
  if (event.kind !== STRUCTURAL || event.scope === CRITERIA_EDIT_SCOPES.NEW_ONLY) return
  openBoundDrafts(event.fromVersionId).forEach((record) =>
    rebind(record, {
      toVersionId: event.toVersionId,
      scope: event.scope,
      rescoredCodes: event.rescoredCodes ?? [],
      trigger: MOVE_TRIGGERS.CRITERIA_EDIT,
    }),
  )
}

function missedPublications(versionId) {
  const structural = editEvents().filter((event) => event.kind === STRUCTURAL)
  const chain = []
  let current = versionId
  let next = structural.find((event) => event.fromVersionId === current)
  while (next && !chain.includes(next)) {
    chain.push(next)
    current = next.toVersionId
    next = structural.find((event) => event.fromVersionId === current)
  }
  return chain
}

function strictestScope(chain) {
  return chain.reduce(
    (strictest, event) =>
      SCOPE_STRICTNESS[event.scope] > SCOPE_STRICTNESS[strictest] ? event.scope : strictest,
    CRITERIA_EDIT_SCOPES.NEW_ONLY,
  )
}

function settleRestoredDraft(record) {
  if (!isOpen(record) || isFrozen(record)) return
  const published = versionWithStatus(CRITERIA_VERSION_STATUSES.PUBLISHED)
  const chain = missedPublications(record.criteriaVersionId)
  const scope = strictestScope(chain)
  if (
    published &&
    published.id !== record.criteriaVersionId &&
    scope !== CRITERIA_EDIT_SCOPES.NEW_ONLY
  ) {
    rebind(record, {
      toVersionId: published.id,
      scope,
      rescoredCodes: chain.flatMap((event) => event.rescoredCodes ?? []),
      trigger: MOVE_TRIGGERS.ENTITY_RESTORE,
    })
  }
  record.criteriaUpdatePendingSince = null
}

export function settleRestoredDrafts(entityId) {
  openRequestsOf(entityId)
    .filter((record) => record.criteriaUpdatePendingSince !== null)
    .forEach(settleRestoredDraft)
}

function holdOpenDrafts(entity) {
  const drafts = openRequestsOf(entity.id).filter(
    (record) => record.path !== ASSESSMENT_PATHS.ADVANCED,
  )
  if (!drafts.length) return
  const since = new Date().toISOString()
  drafts.forEach((record) => {
    record.criteriaUpdatePendingSince = since
  })
  dispatchQueuedJob(() => settleRestoredDrafts(entity.id))
}

registerOpenAssessmentCounter((versionId) => openBoundDrafts(versionId).length)
registerCriteriaEditListener(rebindAfterCriteriaEdit)
registerEntityRestoreStep(holdOpenDrafts)
