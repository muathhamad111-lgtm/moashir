import { object, string } from 'yup'
import { isFilled, isRawScore, scoreFieldName } from './scoreSheet'
import { allowsRawValue, isAchievement, maximumOf, unitScoringType } from './scoringTypes'
import { compactDecimal } from '@/utils/percentText'

function rangeMessage(t, type) {
  if (isAchievement(type)) return t('assessment.self.validation.achievement')
  return t('assessment.self.validation.range', { max: compactDecimal(maximumOf(type)) })
}

function unitField(t, type) {
  return string()
    .nullable()
    .test(
      'raw-format',
      t('assessment.self.validation.format'),
      (value) => !isFilled(value) || isRawScore(value),
    )
    .test(
      'raw-range',
      rangeMessage(t, type),
      (value) => !isFilled(value) || !isRawScore(value) || allowsRawValue(value, type),
    )
}

export function scoreSheetSchema(t, units, scale) {
  return object(
    Object.fromEntries(
      units.map((unit) => [scoreFieldName(unit), unitField(t, unitScoringType(unit, scale))]),
    ),
  )
}
