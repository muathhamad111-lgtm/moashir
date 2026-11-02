import { EVALUATION_TYPES } from '@/constants/criteria'
import { SCORE_LEVELS } from '@/constants/assessment'
import { toWesternDigits } from '@/utils/numerals'
import { toHundredths } from '@/utils/weight'

const RAW_SCORE = /^\d{1,3}(?:\.\d{1,2})?$/
const WIRE_GROUPS = { [SCORE_LEVELS.SUB]: 'subs', [SCORE_LEVELS.POINT]: 'points' }
const LEVEL_OF_GROUP = { subs: SCORE_LEVELS.SUB, points: SCORE_LEVELS.POINT }
const SERVER_FIELD = /^scores\.(subs|points)\.(\d+)$/

export function scoreFieldName(unit) {
  return `${unit.level}-${unit.id}`
}

function unitOf(level, node, main, sub) {
  return {
    level,
    id: node.id,
    code: node.code,
    mainId: main.id,
    subId: sub.id,
    label: level === SCORE_LEVELS.POINT ? node.description : node.name,
  }
}

export function scorableUnits(criteria) {
  return (criteria?.mains ?? []).flatMap((main) =>
    main.subs.flatMap((sub) =>
      sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS
        ? sub.points.map((point) => unitOf(SCORE_LEVELS.POINT, point, main, sub))
        : [unitOf(SCORE_LEVELS.SUB, sub, main, sub)],
    ),
  )
}

export function savedScoreOf(scores, unit) {
  return scores?.[WIRE_GROUPS[unit.level]]?.[String(unit.id)] ?? null
}

export function sheetValues(scores, units) {
  return Object.fromEntries(units.map((unit) => [scoreFieldName(unit), savedScoreOf(scores, unit)?.rawValue ?? '']))
}

export function normalizedRawValue(value) {
  return toWesternDigits(value ?? '').replace(/٫|,/g, '.')
}

export function isRawScore(value) {
  return RAW_SCORE.test(normalizedRawValue(value))
}

export function withinScale(value, maximum) {
  return toHundredths(normalizedRawValue(value)) <= toHundredths(maximum)
}

export function isFilled(value) {
  return normalizedRawValue(value) !== ''
}

export function sheetPayload(values, units) {
  const payload = { subs: {}, points: {} }
  units.forEach((unit) => {
    const value = values[scoreFieldName(unit)]
    payload[WIRE_GROUPS[unit.level]][String(unit.id)] = isFilled(value) ? normalizedRawValue(value) : null
  })
  return payload
}

export function changedSheetPayload(values, units, scores) {
  const payload = { subs: {}, points: {} }
  units.forEach((unit) => {
    const value = values[scoreFieldName(unit)]
    const saved = savedScoreOf(scores, unit)?.rawValue ?? ''
    if (sameRawValue(value, saved)) return
    payload[WIRE_GROUPS[unit.level]][String(unit.id)] = isFilled(value) ? normalizedRawValue(value) : null
  })
  return payload
}

export function isEmptyPayload(payload) {
  return !Object.keys(payload.subs).length && !Object.keys(payload.points).length
}

export function unscoredUnits(values, units) {
  return units.filter((unit) => !isFilled(values[scoreFieldName(unit)]))
}

export function fieldNameOfServerPath(path) {
  const match = SERVER_FIELD.exec(path)
  return match ? `${LEVEL_OF_GROUP[match[1]]}-${match[2]}` : null
}

export function sheetFieldErrors(fieldErrors) {
  return Object.fromEntries(
    Object.entries(fieldErrors ?? {}).flatMap(([path, messages]) => {
      const name = fieldNameOfServerPath(path)
      if (!name) return []
      return [[name, Array.isArray(messages) ? messages[0] : messages]]
    }),
  )
}

export function sameRawValue(left, right) {
  const a = normalizedRawValue(left)
  const b = normalizedRawValue(right)
  if (isRawScore(a) && isRawScore(b)) return toHundredths(a) === toHundredths(b)
  return a === b
}
