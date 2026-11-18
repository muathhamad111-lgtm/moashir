import { fromHundredths } from '@/utils/weight'
import { findVersion, mainOfSub, pointsOf, subsOf } from './criteriaStore'
import { assertCovers, activeTotal, rebalancePointsOf, scoresGuidingPoints } from './criteriaRules'
import { assertDraft, refreshConsistency } from './criteriaVersions'
import { reject } from './criteriaRejection'
import { FieldCollector, readWeight } from './criteriaValidation'

function readAssignments(body) {
  const errors = new FieldCollector()
  const items = Array.isArray(body.weights) ? body.weights : null
  if (!items?.length) errors.add('weights', 'field.required')
  const assignments = (items ?? []).map((item, index) => {
    const itemErrors = new FieldCollector()
    const weight = readWeight(itemErrors, item ?? {}, true)
    if (itemErrors.fields.weight)
      errors.fields[`weights.${index}.weight`] = itemErrors.fields.weight
    return { id: Number(item?.id), weight }
  })
  errors.assertValid()
  return assignments
}

function plannedChanges(children, capacity, body) {
  const assignments = readAssignments(body)
  const byId = new Map(children.map((child) => [child.id, child]))
  assignments.forEach((assignment, index) => {
    if (!byId.has(assignment.id)) throw reject.notAChild(index)
  })
  const planned = new Map(assignments.map((assignment) => [assignment.id, assignment.weight]))
  const total = children
    .filter((child) => child.active)
    .reduce((sum, child) => sum + (planned.get(child.id) ?? child.weight), 0)
  if (total > capacity) {
    throw reject.childrenExceedParent(fromHundredths(total), fromHundredths(capacity))
  }
  return assignments.filter((assignment) => byId.get(assignment.id).weight !== assignment.weight)
}

function onDraftOf(versionId, actor, change) {
  const version = findVersion(versionId)
  assertDraft(version)
  const result = change()
  refreshConsistency(version, actor.id)
  return result
}

export function distributeSubWeights(actor, main, body) {
  return onDraftOf(main.versionId, actor, () => {
    if (main.distributeEqually) throw reject.distributedEqually()
    const subs = subsOf(main.id)
    plannedChanges(subs, main.weight, body).forEach(({ id, weight }) => {
      const sub = subs.find((candidate) => candidate.id === id)
      if (scoresGuidingPoints(sub) && !sub.distributeEqually) {
        assertCovers(weight, activeTotal(pointsOf(sub.id)))
      }
      sub.weight = weight
      rebalancePointsOf(sub)
    })
    return subsOf(main.id)
  })
}

export function distributePointWeights(actor, sub, body) {
  return onDraftOf(mainOfSub(sub).versionId, actor, () => {
    if (!scoresGuidingPoints(sub)) throw reject.subEvaluatedDirectly()
    if (sub.distributeEqually) throw reject.distributedEqually()
    const points = pointsOf(sub.id)
    plannedChanges(points, sub.weight, body).forEach(({ id, weight }) => {
      points.find((candidate) => candidate.id === id).weight = weight
    })
    return pointsOf(sub.id)
  })
}
