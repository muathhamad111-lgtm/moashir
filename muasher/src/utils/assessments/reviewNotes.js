import { SCORING_TYPES } from '@/constants/criteria'
import { isFilled, normalizedRawValue } from '@/utils/assessments/scoreSheet'
import { toHundredths } from '@/utils/weight'

export const REVIEW_NOTE_LEVELS = Object.freeze({ MAIN: 'main', SUB: 'sub', POINT: 'point' })
export const REVIEW_NOTE_MAX = 500

// The unit an internal note belongs to: a main criterion, a sub-criterion or an indicator.
export function noteUnit({ main, sub = null, point = null }) {
  if (point)
    return {
      level: REVIEW_NOTE_LEVELS.POINT,
      mainCriterionId: main.id,
      subCriterionId: sub.id,
      guidingPointId: point.id,
    }
  if (sub)
    return {
      level: REVIEW_NOTE_LEVELS.SUB,
      mainCriterionId: main.id,
      subCriterionId: sub.id,
      guidingPointId: null,
    }
  return {
    level: REVIEW_NOTE_LEVELS.MAIN,
    mainCriterionId: main.id,
    subCriterionId: null,
    guidingPointId: null,
  }
}

export function noteKey(unit) {
  return [
    unit.level,
    unit.mainCriterionId,
    unit.subCriterionId ?? 0,
    unit.guidingPointId ?? 0,
  ].join('-')
}

// The notes of a review per unit: the viewer's own note and the other authors' notes.
export function notesByUnit(notes) {
  const byUnit = new Map()
  for (const note of notes ?? []) {
    const key = noteKey(note)
    const entry = byUnit.get(key) ?? { mine: null, others: [] }
    if (note.isMine) entry.mine = note
    else entry.others.push(note)
    byUnit.set(key, entry)
  }
  return byUnit
}

// «غير متحقق» on an achievement item opens its note for the reason (operator 2026-11-22).
export function isNotAchieved(scoringType, value) {
  if (scoringType !== SCORING_TYPES.ACHIEVEMENT || !isFilled(value)) return false
  return toHundredths(normalizedRawValue(value)) === 0
}

export function isAchieved(scoringType, value) {
  return (
    scoringType === SCORING_TYPES.ACHIEVEMENT &&
    isFilled(value) &&
    !isNotAchieved(scoringType, value)
  )
}
