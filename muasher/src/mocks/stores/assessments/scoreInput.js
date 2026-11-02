import { SCORE_LEVELS } from '@/constants/assessment'
import { assessmentReject, fieldMessage } from './assessmentRejection'
import { decimalRaw, parseRawHundredths, scaleMaximumHundredths, scorableUnits, unitKey } from './selfScoring'

const MAX_ITEMS = 500
const REASON_MIN = 10
const REASON_MAX = 1000
const NUMERIC = /^-?\d+(?:\.\d+)?$/
const DECIMAL_0_2 = /^-?\d+(?:\.\d{1,2})?$/
const GROUPS = { subs: SCORE_LEVELS.SUB, points: SCORE_LEVELS.POINT }

function isPlainObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]'
}

function scoreValueError(value, maximum) {
  if (value === null) return null
  const text = typeof value === 'number' || typeof value === 'string' ? String(value).trim() : ''
  if (!NUMERIC.test(text)) return ['validation.numeric', {}]
  if (!DECIMAL_0_2.test(text)) return ['validation.decimal', { decimal: '0-2' }]
  if (text.startsWith('-')) return ['validation.min', { min: 0 }]
  if (parseRawHundredths(text) > maximum) return ['validation.max', { max: decimalRaw(maximum) }]
  return null
}

function collectGroupErrors(errors, scores, group, maximum) {
  const values = scores[group]
  if (values === undefined) return
  if (!isPlainObject(values) || Object.keys(values).length > MAX_ITEMS) {
    errors[`scores.${group}`] = [fieldMessage('validation.invalid', {}, `scores.${group}`)]
    return
  }
  Object.entries(values).forEach(([key, value]) => {
    const failure = scoreValueError(value, maximum)
    if (failure) errors[`scores.${group}.${key}`] = [fieldMessage(failure[0], failure[1], `scores.${group}`)]
  })
}

function collectReasonError(errors, reason) {
  if (reason === undefined || reason === null || reason === '') return
  const length = typeof reason === 'string' ? reason.trim().length : -1
  if (length < REASON_MIN || length > REASON_MAX) errors.reason = [fieldMessage('validation.reason_length', {}, 'reason')]
}

function collectScoresErrors(errors, body, presence, maximum) {
  if (!('scores' in body)) {
    if (presence === 'present') errors.scores = [fieldMessage('validation.required', {}, 'scores')]
    return
  }
  const { scores } = body
  const keysAllowed = isPlainObject(scores) && Object.keys(scores).every((key) => key in GROUPS)
  if (!keysAllowed) {
    errors.scores = [fieldMessage('validation.invalid', {}, 'scores')]
    return
  }
  Object.keys(GROUPS).forEach((group) => collectGroupErrors(errors, scores, group, maximum))
}

export function validateScoreRequest(body, record, presence) {
  const errors = {}
  collectScoresErrors(errors, body, presence, scaleMaximumHundredths(record.scaleKind))
  collectReasonError(errors, body.reason)
  if (Object.keys(errors).length) throw assessmentReject.validation(errors)
  return {
    scores: isPlainObject(body.scores) ? body.scores : null,
    reason: typeof body.reason === 'string' && body.reason.trim() ? body.reason.trim() : null,
  }
}

function subsScoredThroughPoints(tree) {
  return new Set(
    tree.mains.flatMap((main) => main.subs.filter((sub) => sub.points.length).map((sub) => String(sub.id))),
  )
}

export function parseScoreSheet(scores, tree, scaleKind) {
  const units = new Map(scorableUnits(tree).map((unit) => [unitKey(unit.level, unit.id), unit]))
  const throughPoints = subsScoredThroughPoints(tree)
  const sheet = new Map()
  const reasons = {}
  Object.entries(GROUPS).forEach(([group, level]) => {
    Object.entries(scores[group] ?? {}).forEach(([key, value]) => {
      const field = `scores.${group}.${key}`
      if (!units.has(unitKey(level, key))) {
        reasons[field] = level === SCORE_LEVELS.SUB && throughPoints.has(key) ? 'score.use_points' : 'score.item_not_in_version'
        return
      }
      if (value === null) return
      const raw = parseRawHundredths(String(value))
      if (raw === null || raw > scaleMaximumHundredths(scaleKind)) reasons[field] = 'score.out_of_range'
      else sheet.set(unitKey(level, key), raw)
    })
  })
  if (Object.keys(reasons).length) throw assessmentReject.invalidScoreItems(reasons)
  return sheet
}
