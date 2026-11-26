import { toCanonicalStatus } from '@/utils/canonicalStatus'
import { assessmentReject } from '../assessments/assessmentRejection'
import { boundTree, exposesResult, sheetOf } from '../assessments/assessmentsStore'
import { parseScoreSheet, validateScoreRequest } from '../assessments/scoreInput'
import {
  computeSelfResult,
  decimalRaw,
  displayPercent,
  scorableUnits,
  unitKey,
} from '../assessments/selfScoring'
import { certificateSettingsResource, LEVEL_ORDER } from '../certificateSettingsStore'

// Mirrors the API's override preview (design §B.3.5): the total and level before and after the
// edited scores, computed without saving anything.
function levelOf(percent) {
  const thresholds = certificateSettingsResource().levelThresholds
  return (
    [...LEVEL_ORDER].reverse().find((level) => Number(percent) >= Number(thresholds[level])) ?? null
  )
}

function summaryOf(record, sheet) {
  const percent = displayPercent(
    computeSelfResult(boundTree(record), sheet, record.scaleKind).percent,
  )
  return { percent, level: levelOf(percent) }
}

function changesOf(record, before, after) {
  return scorableUnits(boundTree(record))
    .map((unit) => ({ unit, key: unitKey(unit.level, unit.id) }))
    .filter(({ key }) => before.get(key) !== after.get(key))
    .map(({ unit, key }) => ({
      code: unit.code ?? null,
      field: `${unit.level}-${unit.id}`,
      before: before.has(key) ? decimalRaw(before.get(key)) : null,
      after: after.has(key) ? decimalRaw(after.get(key)) : null,
    }))
}

export function previewOverride(record, body) {
  const input = validateScoreRequest(body, record, 'present')
  if (!exposesResult(record))
    throw assessmentReject.illegalTransition(toCanonicalStatus(record.status), 'override_score')
  const before = sheetOf(record)
  const after = new Map([
    ...before,
    ...parseScoreSheet(input.scores, boundTree(record), record.scaleKind),
  ])
  return {
    before: summaryOf(record, before),
    after: summaryOf(record, after),
    changes: changesOf(record, before, after),
  }
}
