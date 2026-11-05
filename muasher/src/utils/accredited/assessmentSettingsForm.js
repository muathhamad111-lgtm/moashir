import { object, string } from 'yup'

import { ENTITY_VISIBILITY_LIST } from '@/constants/committee'
import { toWesternDigits } from '@/utils/numerals'

export const UPLOAD_BOUNDS = {
  maxFileMb: { min: 1, max: 100 },
  maxFilesPerSub: { min: 1, max: 50 },
}
export const ADVANCED_BOUNDS = {
  minEntityAgeYears: { min: 0, max: 100 },
  disqualificationScore: { min: 0, max: 100 },
}

const INTEGER_FIELDS = ['generalUploadMaxFileMb', 'advancedMinEntityAgeYears']
const INTEGER = /^\d+$/
const SCORE = /^\d{1,3}(\.\d{1,2})?$/

function normalizedNumber(value) {
  return toWesternDigits(String(value ?? '')).trim()
}

export function integerField(t, { min, max }) {
  return string()
    .trim()
    .required(t('validation.required'))
    .test('range', t('settings.assessment.validation.range', { min, max }), (value) => {
      const text = normalizedNumber(value)
      return INTEGER.test(text) && Number(text) >= min && Number(text) <= max
    })
}

function scoreField(t) {
  const { min, max } = ADVANCED_BOUNDS.disqualificationScore
  return string()
    .trim()
    .required(t('validation.required'))
    .test(
      'score',
      t('settings.assessment.validation.score', { min, max }),
      (value) => SCORE.test(normalizedNumber(value)) && Number(normalizedNumber(value)) <= max,
    )
}

function sameScore(left, right) {
  return Number(normalizedNumber(left)).toFixed(2) === Number(right).toFixed(2)
}

export function assessmentSettingsSchema(t) {
  return object({
    generalUploadMaxFileMb: integerField(t, UPLOAD_BOUNDS.maxFileMb),
    advancedMinEntityAgeYears: integerField(t, ADVANCED_BOUNDS.minEntityAgeYears),
    advancedDisqualificationScore: scoreField(t),
    committeeEntityDataVisibility: string().oneOf(ENTITY_VISIBILITY_LIST, t('validation.required')),
  })
}

export function settingsFormValues(settings) {
  return {
    ...Object.fromEntries(INTEGER_FIELDS.map((field) => [field, String(settings[field] ?? '')])),
    advancedDisqualificationScore: String(settings.advancedDisqualificationScore ?? ''),
    committeeEntityDataVisibility: settings.committeeEntityDataVisibility ?? '',
  }
}

function isKnown(value) {
  return value !== null && value !== undefined
}

export function changedAssessmentSettings(settings, values) {
  const changes = Object.fromEntries(
    INTEGER_FIELDS.filter(
      (field) =>
        isKnown(settings[field]) && Number(normalizedNumber(values[field])) !== settings[field],
    ).map((field) => [field, Number(normalizedNumber(values[field]))]),
  )
  if (
    isKnown(settings.advancedDisqualificationScore) &&
    !sameScore(values.advancedDisqualificationScore, settings.advancedDisqualificationScore)
  ) {
    changes.advancedDisqualificationScore = Number(
      normalizedNumber(values.advancedDisqualificationScore),
    ).toFixed(2)
  }
  if (
    isKnown(settings.committeeEntityDataVisibility) &&
    values.committeeEntityDataVisibility !== settings.committeeEntityDataVisibility
  )
    changes.committeeEntityDataVisibility = values.committeeEntityDataVisibility
  return changes
}
