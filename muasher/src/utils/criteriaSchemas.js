import { string as yupString } from 'yup'
import { CRITERIA_NAME_MAX_LENGTH, CRITERIA_TEXT_MIN_LENGTH } from '@/constants/criteria'
import { compareWeights, formatWeight, isAcceptableWeight } from '@/utils/weight'

export function requiredText(t, max = CRITERIA_NAME_MAX_LENGTH) {
  return yupString()
    .trim()
    .required(t('validation.required'))
    .min(CRITERIA_TEXT_MIN_LENGTH, t('validation.minChars', { min: CRITERIA_TEXT_MIN_LENGTH }))
    .max(max, t('criteria.validation.maxChars', { max }))
}

export function pairedText(t, sibling, max = CRITERIA_NAME_MAX_LENGTH) {
  return yupString()
    .trim()
    .max(max, t('criteria.validation.maxChars', { max }))
    .test(
      'paired',
      t('criteria.validation.bothLanguages', { min: CRITERIA_TEXT_MIN_LENGTH }),
      function check(value) {
        const own = (value ?? '').trim()
        const other = String(this.parent[sibling] ?? '').trim()
        if (!own && !other) return true
        return own.length >= CRITERIA_TEXT_MIN_LENGTH
      },
    )
}

export function weightText(t, { max = null, locale = 'en' } = {}) {
  return yupString()
    .trim()
    .required(t('validation.required'))
    .test('weight', t('criteria.validation.weightFormat'), (value) => isAcceptableWeight(value))
    .test(
      'remaining',
      t('criteria.validation.exceedsRemaining', {
        remaining: max === null ? '' : formatWeight(max, locale),
      }),
      (value) => max === null || !isAcceptableWeight(value) || compareWeights(value, max) <= 0,
    )
}
