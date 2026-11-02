import { CONSISTENCY_PROBLEMS as PROBLEM, EVALUATION_TYPES } from '@/constants/criteria'
import { fromHundredths } from '@/utils/weight'
import { mainsOf, pointsOf, subsOf } from './criteriaStore'
import { reject } from './criteriaRejection'

export function activeTotal(nodes, excludingId = null) {
  return nodes
    .filter((node) => node.active && node.id !== excludingId)
    .reduce((total, node) => total + node.weight, 0)
}

export function scoresGuidingPoints(sub) {
  return sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS
}

function remainingOf(capacity, allocatedToOthers) {
  return Math.max(0, capacity - allocatedToOthers)
}

export function assertFits(capacity, allocatedToOthers, requested) {
  const remaining = remainingOf(capacity, allocatedToOthers)
  if (requested > remaining) throw reject.exceedsRemaining(fromHundredths(remaining))
}

export function assertCovers(capacity, allocated) {
  if (capacity < allocated) throw reject.belowAllocated(fromHundredths(allocated))
}

export function childWeight(
  { capacity, allocatedToOthers, distributesEqually },
  requested,
  isActive,
) {
  if (distributesEqually) return requested ?? 1
  if (requested === null || requested === undefined) throw reject.weightRequired()
  if (isActive) assertFits(capacity, allocatedToOthers, requested)
  return requested
}

function assignShares(nodes, total) {
  const active = nodes.filter((node) => node.active)
  if (!active.length) return
  if (total < active.length) throw reject.tooSmallToDistribute(fromHundredths(total), active.length)
  const base = Math.floor(total / active.length)
  const remainder = total - base * active.length
  active.forEach((node, index) => {
    node.weight = base + (index < remainder ? 1 : 0)
  })
}

export function rebalancePointsOf(sub) {
  if (!sub.distributeEqually || !scoresGuidingPoints(sub)) return
  assignShares(pointsOf(sub.id), sub.weight)
}

export function rebalanceSubsOf(main) {
  if (!main.distributeEqually) return
  const subs = subsOf(main.id)
  assignShares(subs, main.weight)
  subs.filter((sub) => sub.active).forEach(rebalancePointsOf)
}

function problem(nodeType, node, code, expected = null, actual = null) {
  return {
    nodeType,
    nodeId: node?.id ?? null,
    code: node?.code ?? null,
    problem: code,
    expectedWeight: expected === null ? null : fromHundredths(expected),
    actualWeight: actual === null ? null : fromHundredths(actual),
  }
}

function subProblem(sub) {
  if (!sub.active || !scoresGuidingPoints(sub)) return null
  const points = pointsOf(sub.id)
  if (!points.some((point) => point.active)) return problem('sub', sub, PROBLEM.NO_ACTIVE_POINT)
  const allocated = activeTotal(points)
  return allocated > sub.weight
    ? problem('sub', sub, PROBLEM.POINTS_EXCEED_SUB, sub.weight, allocated)
    : null
}

function mainProblems(main) {
  const subs = subsOf(main.id)
  const allocated = activeTotal(subs)
  const found =
    allocated === main.weight
      ? []
      : [problem('main', main, PROBLEM.WEIGHTS_MISMATCH, main.weight, allocated)]
  return [...found, ...subs.map(subProblem).filter(Boolean)]
}

export function consistencyProblems(versionId) {
  const activeMains = mainsOf(versionId).filter((main) => main.active)
  if (!activeMains.length) return [problem('main', null, PROBLEM.NO_ACTIVE_MAIN)]
  return activeMains.flatMap(mainProblems)
}

export function problemFieldKey(entry) {
  return entry.nodeId === null ? 'nodes.version' : `nodes.${entry.nodeType}.${entry.nodeId}`
}
