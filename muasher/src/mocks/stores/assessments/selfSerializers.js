import { SCORE_LEVELS } from '@/constants/assessment'
import { pathLabelOf } from '../../fixtures/pathLabels'
import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { entitySummary } from '../entitySerializers'
import { findMain, findPoint, findSub } from '../criteriaStore'
import { boundTree, entityOf, exposesResult, isFrozen, isOpen, sheetOf } from './assessmentsStore'
import { criteriaUpdateOf } from './criteriaMoves'
import { statusHistoryOf } from './statusHistory'
import { readsEveryAssessment } from './assessmentAccess'
import { isMaskedFor, maskedStatus, visibleTransitions } from '../award/statusMask'
import {
  decimalRaw,
  decimalWeight,
  displayEarned,
  displayPercent,
  rawPercent,
  scaleMaximumHundredths,
  scorableUnits,
  unitKey,
} from './selfScoring'

function resultSummary(result) {
  if (!result) return null
  return {
    total: displayEarned(result.total),
    maxTotal: decimalWeight(result.totalWeight),
    percent: displayPercent(result.percent),
  }
}

export function assessmentResource(record) {
  return {
    id: record.id,
    number: record.number,
    path: record.path,
    pathLabel: pathLabelOf(record.path),
    status: maskedStatus(record),
    isOpen: isOpen(record) || isMaskedFor(record),
    isFrozen: isFrozen(record),
    criteriaUpdateInProgress: record.criteriaUpdatePendingSince !== null,
    criteriaVersionId: record.criteriaVersionId,
    scaleKind: record.scaleKind,
    awardCycleId: record.awardCycleId ?? null,
    entity: entitySummary(entityOf(record)),
    result: exposesResult(record) ? resultSummary(record.result) : null,
    createdAt: record.createdAt,
    updatedAt: record.updatedAt,
    submittedAt: record.submittedAt,
    completedAt: record.completedAt,
  }
}

function scoresByLevel(record, units) {
  const sheet = sheetOf(record)
  const byLevel = { [SCORE_LEVELS.SUB]: {}, [SCORE_LEVELS.POINT]: {} }
  units.forEach((unit) => {
    const raw = sheet.get(unitKey(unit.level, unit.id))
    if (raw === undefined) return
    byLevel[unit.level][String(unit.id)] = {
      rawValue: decimalRaw(raw),
      percent: displayPercent(rawPercent(raw, record.scaleKind)),
    }
  })
  return { subs: byLevel[SCORE_LEVELS.SUB], points: byLevel[SCORE_LEVELS.POINT] }
}

export function scoresResource(record) {
  const units = scorableUnits(boundTree(record))
  const sheet = sheetOf(record)
  return {
    assessment: assessmentResource(record),
    scale: {
      kind: toCanonicalStatus(record.scaleKind),
      maximum: decimalRaw(scaleMaximumHundredths(record.scaleKind)),
    },
    scores: scoresByLevel(record, units),
    progress: {
      scored: units.filter((unit) => sheet.has(unitKey(unit.level, unit.id))).length,
      required: units.length,
    },
  }
}

export function recordResource(record, viewer) {
  const visible = { ...record, transitions: visibleTransitions(record, viewer) }
  return {
    ...assessmentResource(record),
    statusHistory: statusHistoryOf(visible, { staffView: readsEveryAssessment(viewer) }),
  }
}

export function formResource(record) {
  return {
    ...scoresResource(record),
    criteria: boundTree(record),
    criteriaUpdate: criteriaUpdateOf(record),
  }
}

function figures(node) {
  return {
    weight: decimalWeight(node.weight),
    earned: displayEarned(node.earned),
    percent: displayPercent(node.percent),
  }
}

function nullableRaw(raw) {
  return raw === null ? null : decimalRaw(raw)
}

export function mainRows(result) {
  return result.mains.map((main) => ({
    criterionId: main.id,
    code: main.code,
    name: findMain(main.id)?.name ?? null,
    ...figures(main),
  }))
}

export function subRows(result) {
  return result.mains.flatMap((main) =>
    main.subs.map((sub) => ({
      subId: sub.id,
      mainCriterionId: main.id,
      code: sub.code,
      name: findSub(sub.id)?.name ?? null,
      evaluationType: sub.evaluationType,
      rawValue: nullableRaw(sub.raw),
      ...figures(sub),
    })),
  )
}

export function pointRows(result) {
  return result.mains.flatMap((main) =>
    main.subs.flatMap((sub) =>
      sub.points.map((point) => ({
        pointId: point.id,
        subCriterionId: sub.id,
        code: point.code,
        description: findPoint(point.id)?.description ?? null,
        rawValue: nullableRaw(point.raw),
        ...figures(point),
      })),
    ),
  )
}

function resultIdentity(record) {
  return {
    id: record.id,
    number: record.number,
    path: record.path,
    pathLabel: pathLabelOf(record.path),
    status: maskedStatus(record),
    isFrozen: isFrozen(record),
  }
}

export function resultResource(record) {
  if (!exposesResult(record)) return { ...resultIdentity(record), available: false }
  return snapshotResource(record)
}

export function snapshotResource(record) {
  const { result } = record
  return {
    ...resultIdentity(record),
    available: true,
    criteriaVersionId: result.criteriaVersionId,
    scaleKind: result.scaleKind,
    resultVersion: result.version,
    submittedAt: record.submittedAt,
    completedAt: record.completedAt,
    total: displayEarned(result.total),
    maxTotal: decimalWeight(result.totalWeight),
    percent: displayPercent(result.percent),
    main: mainRows(result),
    sub: subRows(result),
    points: pointRows(result),
  }
}

export function requestSearchHit(record) {
  return {
    id: record.id,
    number: record.number,
    path: record.path,
    pathLabel: pathLabelOf(record.path),
    status: record.status,
    isFrozen: isFrozen(record),
    entity: entitySummary(entityOf(record)),
  }
}
