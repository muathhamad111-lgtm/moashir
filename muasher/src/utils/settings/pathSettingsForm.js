import { array, object, string } from 'yup'
import { PATH_DESCRIPTION_MAX, PATH_ENTITY_TEXTS, PATH_TEXT_MAX } from '@/constants/paths'
import { integerField } from '@/utils/accredited/assessmentSettingsForm'
import { toWesternDigits } from '@/utils/numerals'

const INTEGER_FIELDS = {
  reviewSlaDays: (settings) => settings.reviewSlaDays,
  uploadMaxFileMb: (settings) => settings.uploadLimits.maxFileMb,
  evidenceMaxFilesPerSub: (settings) => settings.uploadLimits.maxFilesPerSub,
}

function textOf(value) {
  return (value ?? '').trim()
}

function textPair(t) {
  const side = string().max(
    PATH_TEXT_MAX,
    t('settings.paths.validation.textMax', { max: PATH_TEXT_MAX }),
  )
  return object({ ar: side, en: side }).test(
    'pair',
    t('settings.paths.validation.textPair'),
    (value, context) => {
      const filled = [textOf(value?.ar), textOf(value?.en)].filter(Boolean).length
      if (filled !== 1) return true
      const missing = textOf(value?.ar) ? 'en' : 'ar'
      return context.createError({ path: `${context.path}.${missing}` })
    },
  )
}

// Path names are fixed; only the description is managed here.
const IDENTITY_FIELDS = Object.freeze({ description: PATH_DESCRIPTION_MAX })

function requiredPair(t, max) {
  const side = string()
    .trim()
    .required(t('settings.paths.validation.required'))
    .max(max, t('settings.paths.validation.textMax', { max }))
  return object({ ar: side, en: side })
}

function identitySchema(t) {
  return Object.fromEntries(
    Object.entries(IDENTITY_FIELDS).map(([field, max]) => [field, requiredPair(t, max)]),
  )
}

// The self assessment has no reviewer and no evidence: only its description is managed.
function managesReview(settings) {
  return settings?.reviewSlaLimits !== undefined
}

export function pathSettingsSchema(t, settings) {
  if (!managesReview(settings)) return object(identitySchema(t))
  const limits = settings?.reviewSlaLimits ?? { min: 1, max: 120 }
  const bounds = settings?.uploadLimitBounds ?? { maxFileMb: 100, maxFilesPerSub: 50 }
  return object({
    ...identitySchema(t),
    reviewSlaDays: integerField(t, limits),
    uploadMaxFileMb: integerField(t, { min: 1, max: bounds.maxFileMb }),
    evidenceMaxFilesPerSub: integerField(t, { min: 1, max: bounds.maxFilesPerSub }),
    evidenceAllowedTypes: array().min(1, t('settings.assessment.validation.types')),
    entityTexts: object(Object.fromEntries(PATH_ENTITY_TEXTS.map((key) => [key, textPair(t)]))),
  })
}

function identityValues(settings) {
  return Object.fromEntries(
    Object.keys(IDENTITY_FIELDS).map((field) => [
      field,
      { ar: settings[field]?.ar ?? '', en: settings[field]?.en ?? '' },
    ]),
  )
}

export function pathSettingsValues(settings) {
  if (!managesReview(settings)) return identityValues(settings)
  return {
    ...identityValues(settings),
    ...Object.fromEntries(
      Object.entries(INTEGER_FIELDS).map(([field, read]) => [field, String(read(settings) ?? '')]),
    ),
    evidenceAllowedTypes: [...settings.uploadLimits.allowedTypes],
    entityTexts: Object.fromEntries(
      PATH_ENTITY_TEXTS.map((key) => [
        key,
        { ar: settings.entityTexts[key]?.ar ?? '', en: settings.entityTexts[key]?.en ?? '' },
      ]),
    ),
  }
}

function sameTypes(left, right) {
  return left.length === right.length && left.every((type) => right.includes(type))
}

function textChange(stored, value) {
  const next = { ar: textOf(value?.ar), en: textOf(value?.en) }
  const empty = !next.ar && !next.en
  if (empty) return stored ? null : undefined
  if (stored && stored.ar === next.ar && stored.en === next.en) return undefined
  return next
}

function changedTexts(settings, values) {
  const changes = {}
  for (const key of PATH_ENTITY_TEXTS) {
    const change = textChange(settings.entityTexts[key], values.entityTexts?.[key])
    if (change !== undefined) changes[key] = change
  }
  return changes
}

function changedIntegers(settings, values) {
  return Object.fromEntries(
    Object.entries(INTEGER_FIELDS)
      .map(([field, read]) => [
        field,
        Number(toWesternDigits(values[field]).trim()),
        read(settings),
      ])
      .filter(([, next, current]) => next !== current)
      .map(([field, next]) => [field, next]),
  )
}

function changedIdentity(settings, values) {
  const changes = {}
  for (const field of Object.keys(IDENTITY_FIELDS)) {
    const next = { ar: textOf(values[field]?.ar), en: textOf(values[field]?.en) }
    const stored = settings[field] ?? {}
    if (next.ar !== (stored.ar ?? '') || next.en !== (stored.en ?? '')) changes[field] = next
  }
  return changes
}

export function changedPathSettings(settings, values) {
  if (!managesReview(settings)) return changedIdentity(settings, values)
  const changes = { ...changedIdentity(settings, values), ...changedIntegers(settings, values) }
  if (!sameTypes(values.evidenceAllowedTypes, settings.uploadLimits.allowedTypes))
    changes.evidenceAllowedTypes = [...values.evidenceAllowedTypes]
  const texts = changedTexts(settings, values)
  if (Object.keys(texts).length) changes.entityTexts = texts
  return changes
}
