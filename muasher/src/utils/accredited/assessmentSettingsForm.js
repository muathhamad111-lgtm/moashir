import { array, object, string } from 'yup'

export const UPLOAD_BOUNDS = { maxFileMb: { min: 1, max: 100 }, maxFilesPerSub: { min: 1, max: 50 } }

const INTEGER_FIELDS = ['accreditedReviewSlaDays', 'uploadMaxFileMb', 'evidenceMaxFilesPerSub']
const INTEGER = /^\d+$/

function integerField(t, { min, max }) {
  return string()
    .trim()
    .required(t('validation.required'))
    .test('range', t('settings.assessment.validation.range', { min, max }), (value) => INTEGER.test(value ?? '') && Number(value) >= min && Number(value) <= max)
}

export function assessmentSettingsSchema(t, slaLimits) {
  return object({
    accreditedReviewSlaDays: integerField(t, slaLimits),
    uploadMaxFileMb: integerField(t, UPLOAD_BOUNDS.maxFileMb),
    evidenceMaxFilesPerSub: integerField(t, UPLOAD_BOUNDS.maxFilesPerSub),
    evidenceAllowedTypes: array().min(1, t('settings.assessment.validation.types')),
  })
}

export function settingsFormValues(settings) {
  return {
    ...Object.fromEntries(INTEGER_FIELDS.map((field) => [field, String(settings[field] ?? '')])),
    evidenceAllowedTypes: [...settings.evidenceAllowedTypes],
  }
}

function sameTypes(left, right) {
  return left.length === right.length && left.every((type) => right.includes(type))
}

export function changedAssessmentSettings(settings, values) {
  const changes = Object.fromEntries(
    INTEGER_FIELDS.filter((field) => Number(values[field]) !== settings[field]).map((field) => [field, Number(values[field])]),
  )
  if (!sameTypes(values.evidenceAllowedTypes, settings.evidenceAllowedTypes)) changes.evidenceAllowedTypes = [...values.evidenceAllowedTypes]
  return changes
}
