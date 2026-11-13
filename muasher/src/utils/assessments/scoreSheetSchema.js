import { object, string } from 'yup'
import { isFilled, isRawScore, scoreFieldName, withinScale } from './scoreSheet'

export function scoreSheetSchema(t, units, maximum) {
  const field = string()
    .nullable()
    .test(
      'raw-format',
      t('assessment.self.validation.format'),
      (value) => !isFilled(value) || isRawScore(value),
    )
    .test(
      'raw-range',
      t('assessment.self.validation.range', { max: maximum }),
      (value) => !isFilled(value) || !isRawScore(value) || withinScale(value, maximum),
    )
  return object(Object.fromEntries(units.map((unit) => [scoreFieldName(unit), field])))
}
