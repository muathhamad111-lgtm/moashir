import { findVersion } from '../criteriaStore'
import { versionTree } from '../criteriaSerializers'
import { scorableUnits, unitKey } from './selfScoring'

export const MOVE_TRIGGERS = { CRITERIA_EDIT: 'criteria_edit', ENTITY_RESTORE: 'entity_restore' }

function scoredUnits(tree, scores) {
  return scorableUnits(tree).filter((unit) => Object.hasOwn(scores, unitKey(unit.level, unit.id)))
}

function entryOf(unit) {
  return { level: unit.level, code: unit.code }
}

export function recordMove(record, { fromTree, toTree, previousScores, scope, trigger }) {
  const before = scoredUnits(fromTree, previousScores)
  const keptCodes = new Set(scoredUnits(toTree, record.scores).map((unit) => unit.code))
  const fromCodes = new Set(scorableUnits(fromTree).map((unit) => unit.code))
  const move = {
    fromVersionId: fromTree.versionId,
    toVersionId: toTree.versionId,
    movedAt: new Date().toISOString(),
    scope,
    trigger,
    kept: before.filter((unit) => keptCodes.has(unit.code)).map(entryOf),
    reset: before.filter((unit) => !keptCodes.has(unit.code)).map(entryOf),
    added: scorableUnits(toTree).filter((unit) => !fromCodes.has(unit.code)).map(entryOf),
  }
  record.criteriaMoves = [...(record.criteriaMoves ?? []), move]
}

function labelsOf(tree) {
  const subs = tree.mains.flatMap((main) => main.subs)
  const points = subs.flatMap((sub) => sub.points ?? [])
  return new Map([
    ...subs.map((sub) => [sub.code, sub.name ?? null]),
    ...points.map((point) => [point.code, point.description ?? null]),
  ])
}

function labelled(entries, current, previous) {
  return entries.map((entry) => ({ ...entry, label: current.get(entry.code) ?? previous.get(entry.code) ?? null }))
}

export function criteriaUpdateOf(record) {
  const move = record.criteriaMoves?.at(-1)
  if (!move) return null
  const fromVersion = findVersion(move.fromVersionId)
  const toVersion = findVersion(move.toVersionId)
  const current = labelsOf(versionTree(findVersion(record.criteriaVersionId)))
  const previous = labelsOf(versionTree(fromVersion))
  return {
    fromVersionId: move.fromVersionId,
    fromVersionNumber: fromVersion.versionNumber,
    toVersionId: move.toVersionId,
    toVersionNumber: toVersion.versionNumber,
    movedAt: move.movedAt,
    scope: move.scope,
    trigger: move.trigger,
    kept: labelled(move.kept, current, previous),
    reset: labelled(move.reset, current, previous),
    added: labelled(move.added, current, previous),
  }
}
