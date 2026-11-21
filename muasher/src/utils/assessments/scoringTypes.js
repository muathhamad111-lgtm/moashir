import { SCORING_TYPES } from '@/constants/criteria'
import { SCALE_KINDS } from '@/constants/assessment'
import { toHundredths } from '@/utils/weight'
import { isRawScore, normalizedRawValue, withinScale } from './scoreSheet'

const STAMPED_TYPES = Object.freeze({
  [SCALE_KINDS.OF_10]: SCORING_TYPES.SCORE,
  [SCALE_KINDS.RELATIVE]: SCORING_TYPES.RELATIVE,
})

const MAXIMUMS = Object.freeze({
  [SCORING_TYPES.RELATIVE]: '100.00',
  [SCORING_TYPES.SCORE]: '10.00',
  [SCORING_TYPES.ACHIEVEMENT]: '1.00',
})

const WHOLE = 100

export function unitScoringType(node, scale) {
  return STAMPED_TYPES[scale?.kind] ?? node?.scoringType ?? SCORING_TYPES.SCORE
}

export function maximumOf(type) {
  return MAXIMUMS[type] ?? MAXIMUMS[SCORING_TYPES.SCORE]
}

export function isAchievement(type) {
  return type === SCORING_TYPES.ACHIEVEMENT
}

export function allowsRawValue(value, type) {
  if (!isRawScore(value) || !withinScale(value, maximumOf(type))) return false
  return !isAchievement(type) || toHundredths(normalizedRawValue(value)) % WHOLE === 0
}
