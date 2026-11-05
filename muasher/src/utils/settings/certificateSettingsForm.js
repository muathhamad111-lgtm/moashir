import { boolean, object, string } from 'yup'
import { CERTIFICATE_LEVELS, LEVEL_THRESHOLD_PATTERN } from '@/constants/certificates'
import { toWesternDigits } from '@/utils/numerals'

const PERCENT_MAX = 100
const HUNDREDTH = 0.01

function normalized(value) {
  return toWesternDigits(String(value ?? '')).trim()
}

function displayOf(value) {
  return Number(normalized(value)).toFixed(2)
}

function isPercent(value) {
  const text = normalized(value)
  return LEVEL_THRESHOLD_PATTERN.test(text) && Number(text) > 0 && Number(text) <= PERCENT_MAX
}

function thresholdField(t) {
  return string()
    .required(t('validation.required'))
    .test('percent', t('settings.certificates.validation.percent'), isPercent)
}

export function isAscending(thresholds) {
  const values = CERTIFICATE_LEVELS.map((level) => Number(normalized(thresholds?.[level])))
  return values.every((value, index) => index === 0 || value > values[index - 1])
}

export function certificateSettingsSchema(t) {
  return object({
    accreditedIssuanceEnabled: boolean(),
    levelThresholds: object(
      Object.fromEntries(CERTIFICATE_LEVELS.map((level) => [level, thresholdField(t)])),
    ).test('ascending', t('settings.certificates.validation.ascending'), (value, context) => {
      if (!CERTIFICATE_LEVELS.every((level) => isPercent(value?.[level]))) return true
      if (isAscending(value)) return true
      const broken = CERTIFICATE_LEVELS.find(
        (level, index) =>
          index > 0 &&
          Number(normalized(value[level])) <=
            Number(normalized(value[CERTIFICATE_LEVELS[index - 1]])),
      )
      return context.createError({ path: `${context.path}.${broken}` })
    }),
  })
}

export function certificateSettingsValues(settings) {
  return {
    accreditedIssuanceEnabled: settings.accreditedIssuanceEnabled,
    levelThresholds: { ...settings.levelThresholds },
  }
}

export function changedCertificateSettings(settings, values) {
  const changes = {}
  if (values.accreditedIssuanceEnabled !== settings.accreditedIssuanceEnabled)
    changes.accreditedIssuanceEnabled = values.accreditedIssuanceEnabled
  const next = Object.fromEntries(
    CERTIFICATE_LEVELS.map((level) => [level, displayOf(values.levelThresholds[level])]),
  )
  if (CERTIFICATE_LEVELS.some((level) => next[level] !== settings.levelThresholds[level]))
    changes.levelThresholds = next
  return changes
}

export function levelRangesOf(thresholds) {
  if (!CERTIFICATE_LEVELS.every((level) => isPercent(thresholds?.[level]))) return []
  if (!isAscending(thresholds)) return []
  return CERTIFICATE_LEVELS.map((level, index) => {
    const next = CERTIFICATE_LEVELS[index + 1]
    return {
      level,
      from: displayOf(thresholds[level]),
      to: next ? (Number(displayOf(thresholds[next])) - HUNDREDTH).toFixed(2) : null,
    }
  })
}
