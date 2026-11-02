import {
  CRITERIA_VERSION_STATUSES as STATUS,
  CRITERION_GROUP_ORDER,
  EVALUATION_TYPES,
} from '@/constants/criteria'
import { fromHundredths } from '@/utils/weight'
import {
  deleteMain,
  deletePoint,
  deleteSub,
  findVersion,
  insertMain,
  insertPoint,
  insertSub,
  mainOfSub,
  mainsOf,
  nextCode,
  nextCriteriaId,
  pointsOf,
  replaceAttachments,
  subOfPoint,
  subsOf,
  versionWithStatus,
} from './criteriaStore'
import {
  activeTotal,
  assertCovers,
  assertFits,
  childWeight,
  rebalancePointsOf,
  rebalanceSubsOf,
  scoresGuidingPoints,
} from './criteriaRules'
import { assertDraft, refreshConsistency } from './criteriaVersions'
import { criteriaNotices } from './criteriaNotices'
import { fieldMessage, reject } from './criteriaRejection'
import {
  FieldCollector,
  prohibit,
  readAttachments,
  readBoolean,
  readDescription,
  readEnum,
  readLocalized,
  readOrderedIds,
  readWeight,
} from './criteriaValidation'

const EVALUATION_VALUES = Object.values(EVALUATION_TYPES)

function onVersion(version, actor, change) {
  assertDraft(version)
  const result = change()
  refreshConsistency(version, actor.id)
  return result
}

function currentDraft() {
  const draft = versionWithStatus(STATUS.DRAFT)
  if (!draft) throw reject.noDraft()
  return draft
}

function nextOrder(nodes) {
  return nodes.reduce((highest, node) => Math.max(highest, node.order), 0) + 1
}

function closeGaps(nodes) {
  nodes.forEach((node, index) => {
    node.order = index + 1
  })
}

function applyOrder(nodes, orderedIds) {
  const ids = readOrderedIds(
    { orderedIds },
    nodes.map((node) => node.id),
  )
  ids.forEach((id, index) => {
    nodes.find((node) => node.id === id).order = index + 1
  })
}

function readMain(body, required) {
  const errors = new FieldCollector()
  const values = {
    name: readLocalized(errors, body, 'name', { required }),
    description: readDescription(errors, body),
    weight: readWeight(errors, body, required),
    distributeEqually: readBoolean(errors, body, 'distributeEqually'),
    group: readEnum(errors, body, 'group', CRITERION_GROUP_ORDER),
    active: readBoolean(errors, body, 'active'),
  }
  if (!required) prohibit(errors, body, ['active'])
  errors.assertValid()
  return values
}

function readSub(body, required) {
  const errors = new FieldCollector()
  const values = {
    name: readLocalized(errors, body, 'name', { required }),
    description: readDescription(errors, body),
    assessorNoteLabel: readLocalized(errors, body, 'assessorNoteLabel'),
    weight: readWeight(errors, body),
    distributeEqually: readBoolean(errors, body, 'distributeEqually'),
    evaluationType: required
      ? readEnum(errors, body, 'evaluationType', EVALUATION_VALUES)
      : undefined,
    hasCv: readBoolean(errors, body, 'hasCv'),
    active: required ? readBoolean(errors, body, 'active') : undefined,
    attachments: readAttachments(errors, body),
  }
  if (!required) prohibit(errors, body, ['evaluationType', 'active'])
  errors.assertValid()
  return values
}

function readPoint(body, required) {
  const errors = new FieldCollector()
  const values = {
    description: readDescription(errors, body, 'description', required),
    weight: readWeight(errors, body),
    active: required ? readBoolean(errors, body, 'active') : undefined,
    attachments: readAttachments(errors, body),
  }
  if (!required) prohibit(errors, body, ['active'])
  errors.assertValid()
  return values
}

function defined(values) {
  return Object.fromEntries(Object.entries(values).filter(([, value]) => value !== undefined))
}

export function createMain(actor, body) {
  const draft = currentDraft()
  const values = readMain(body, true)
  return onVersion(draft, actor, () => {
    const main = insertMain({
      id: nextCriteriaId(),
      versionId: draft.id,
      code: nextCode('M'),
      name: values.name,
      description: values.description ?? null,
      weight: values.weight,
      distributeEqually: values.distributeEqually ?? false,
      group: values.group ?? CRITERION_GROUP_ORDER[0],
      active: values.active ?? true,
      order: nextOrder(mainsOf(draft.id)),
    })
    criteriaNotices.mainCreated(actor.id, main)
    return main
  })
}

export function updateMain(actor, main, body) {
  const values = readMain(body, false)
  return onVersion(findVersion(main.versionId), actor, () => {
    const previous = main.weight
    const next = { ...main, ...defined(values) }
    if (!next.distributeEqually) assertCovers(next.weight, activeTotal(subsOf(main.id)))
    Object.assign(main, next)
    rebalanceSubsOf(main)
    if (previous !== main.weight) {
      criteriaNotices.mainWeightChanged(
        actor.id,
        main,
        fromHundredths(previous),
        fromHundredths(main.weight),
      )
    }
    return main
  })
}

export function toggleMain(actor, main, active) {
  return onVersion(findVersion(main.versionId), actor, () => Object.assign(main, { active }))
}

export function removeMain(actor, main) {
  const version = findVersion(main.versionId)
  onVersion(version, actor, () => {
    deleteMain(main.id)
    closeGaps(mainsOf(version.id))
  })
}

export function reorderMains(actor, orderedIds) {
  const draft = currentDraft()
  return onVersion(draft, actor, () => {
    applyOrder(mainsOf(draft.id), orderedIds)
    return mainsOf(draft.id)
  })
}

function subBudget(main, excludingId = null) {
  return {
    capacity: main.weight,
    allocatedToOthers: activeTotal(subsOf(main.id), excludingId),
    distributesEqually: main.distributeEqually,
  }
}

export function createSub(actor, main, body) {
  const values = readSub(body, true)
  const active = values.active ?? true
  return onVersion(findVersion(main.versionId), actor, () => {
    const sub = insertSub({
      id: nextCriteriaId(),
      mainId: main.id,
      code: nextCode(`${main.code}.`),
      name: values.name,
      description: values.description ?? null,
      weight: childWeight(subBudget(main), values.weight, active),
      distributeEqually: values.distributeEqually ?? false,
      evaluationType: values.evaluationType ?? EVALUATION_TYPES.GUIDING_POINTS,
      hasCv: values.hasCv ?? true,
      assessorNoteLabel: values.assessorNoteLabel ?? null,
      active,
      order: nextOrder(subsOf(main.id)),
    })
    replaceAttachments('sub', sub.id, values.attachments ?? [])
    rebalanceSubsOf(main)
    criteriaNotices.subCreated(actor.id, main, sub)
    return sub
  })
}

export function updateSub(actor, sub, body) {
  const values = readSub(body, false)
  const main = mainOfSub(sub)
  return onVersion(findVersion(main.versionId), actor, () => {
    const next = { ...sub, ...defined({ ...values, attachments: undefined }) }
    next.weight = childWeight(subBudget(main, sub.id), values.weight ?? sub.weight, sub.active)
    if (scoresGuidingPoints(sub) && !next.distributeEqually && !main.distributeEqually) {
      assertCovers(next.weight, activeTotal(pointsOf(sub.id)))
    }
    Object.assign(sub, next)
    if (values.attachments) replaceAttachments('sub', sub.id, values.attachments)
    rebalanceSubsOf(main)
    rebalancePointsOf(sub)
    return sub
  })
}

export function toggleSub(actor, sub, active) {
  const main = mainOfSub(sub)
  return onVersion(findVersion(main.versionId), actor, () => {
    if (sub.active === active) return sub
    if (active && !main.distributeEqually)
      assertFits(main.weight, activeTotal(subsOf(main.id), sub.id), sub.weight)
    sub.active = active
    rebalanceSubsOf(main)
    return sub
  })
}

export function switchEvaluationType(actor, sub, { evaluationType, confirmDeletePoints = false }) {
  if (!EVALUATION_VALUES.includes(evaluationType))
    throw reject.validation({ evaluationType: [fieldMessage('field.invalid')] })
  const main = mainOfSub(sub)
  return onVersion(findVersion(main.versionId), actor, () => {
    if (sub.evaluationType === evaluationType) return sub
    const points = pointsOf(sub.id)
    if (evaluationType === EVALUATION_TYPES.DIRECT && points.length && !confirmDeletePoints) {
      throw reject.pointsConfirmRequired(points.length)
    }
    if (evaluationType === EVALUATION_TYPES.DIRECT) points.forEach((point) => deletePoint(point.id))
    sub.evaluationType = evaluationType
    criteriaNotices.evaluationSwitched(
      actor.id,
      main,
      sub,
      evaluationType === EVALUATION_TYPES.GUIDING_POINTS,
    )
    return sub
  })
}

export function removeSub(actor, sub) {
  const main = mainOfSub(sub)
  onVersion(findVersion(main.versionId), actor, () => {
    deleteSub(sub.id)
    closeGaps(subsOf(main.id))
    rebalanceSubsOf(main)
  })
}

export function reorderSubs(actor, main, orderedIds) {
  return onVersion(findVersion(main.versionId), actor, () => {
    applyOrder(subsOf(main.id), orderedIds)
    return subsOf(main.id)
  })
}

function pointBudget(sub, excludingId = null) {
  return {
    capacity: sub.weight,
    allocatedToOthers: activeTotal(pointsOf(sub.id), excludingId),
    distributesEqually: sub.distributeEqually,
  }
}

function versionOfSub(sub) {
  return findVersion(mainOfSub(sub).versionId)
}

export function createPoint(actor, sub, body) {
  const values = readPoint(body, true)
  const active = values.active ?? true
  return onVersion(versionOfSub(sub), actor, () => {
    if (!scoresGuidingPoints(sub)) throw reject.subEvaluatedDirectly()
    const point = insertPoint({
      id: nextCriteriaId(),
      subId: sub.id,
      code: nextCode(`${sub.code}.`),
      description: values.description,
      weight: childWeight(pointBudget(sub), values.weight, active),
      active,
      order: nextOrder(pointsOf(sub.id)),
    })
    replaceAttachments('point', point.id, values.attachments ?? [])
    rebalancePointsOf(sub)
    return point
  })
}

export function updatePoint(actor, point, body) {
  const values = readPoint(body, false)
  const sub = subOfPoint(point)
  return onVersion(versionOfSub(sub), actor, () => {
    const weight = childWeight(
      pointBudget(sub, point.id),
      values.weight ?? point.weight,
      point.active,
    )
    Object.assign(point, defined({ description: values.description }), { weight })
    if (values.attachments) replaceAttachments('point', point.id, values.attachments)
    rebalancePointsOf(sub)
    return point
  })
}

export function togglePoint(actor, point, active) {
  const sub = subOfPoint(point)
  return onVersion(versionOfSub(sub), actor, () => {
    if (point.active === active) return point
    if (active && !sub.distributeEqually)
      assertFits(sub.weight, activeTotal(pointsOf(sub.id), point.id), point.weight)
    point.active = active
    rebalancePointsOf(sub)
    return point
  })
}

export function removePoint(actor, point) {
  const sub = subOfPoint(point)
  return onVersion(versionOfSub(sub), actor, () => {
    deletePoint(point.id)
    closeGaps(pointsOf(sub.id))
    rebalancePointsOf(sub)
    return sub
  })
}

export function reorderPoints(actor, sub, orderedIds) {
  return onVersion(versionOfSub(sub), actor, () => {
    applyOrder(pointsOf(sub.id), orderedIds)
    return pointsOf(sub.id)
  })
}
