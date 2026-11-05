import { fieldMessage } from '../assessments/assessmentRejection'
import { findRegion } from '../regionsStore'
import { isPlainObject, isPositiveInteger, reasonError } from '../accredited/accreditedValidation'

export const DATE_FIELDS = [
  'submissionStart',
  'submissionEnd',
  'committeeStart',
  'committeeEnd',
  'announcementAt',
]
const REQUIRED_DATES = ['submissionStart', 'submissionEnd', 'committeeStart', 'committeeEnd']
const SCALE_KINDS = ['of-10', 'relative']
const SCORE_FORMAT = /^\d{1,3}(\.\d{1,2})?$/
const NAME_MAX = 200
const REGIONS_MAX = 13

function has(body, field) {
  return Object.hasOwn(body, field)
}

function filled(value) {
  return value !== undefined && value !== null && value !== ''
}

function isDate(value) {
  return typeof value === 'string' && !Number.isNaN(Date.parse(value))
}

function integerBetween(value, min, max) {
  return (
    Number.isInteger(Number(value)) &&
    String(value).trim() !== '' &&
    Number(value) >= min &&
    Number(value) <= max
  )
}

function nameErrors(body, required, errors) {
  if (!has(body, 'name')) {
    if (required) errors.name = [fieldMessage('validation.required', {}, 'name')]
    return
  }
  if (!isPlainObject(body.name)) {
    errors.name = [fieldMessage('validation.required', {}, 'name')]
    return
  }
  ;['ar', 'en'].forEach((locale) => {
    const text = typeof body.name[locale] === 'string' ? body.name[locale].trim() : ''
    if (!text || text.length > NAME_MAX)
      errors[`name.${locale}`] = [
        fieldMessage('validation.between_chars', { min: 1, max: NAME_MAX }, 'name'),
      ]
  })
}

function dateErrors(body, required, errors) {
  DATE_FIELDS.forEach((field) => {
    if (!has(body, field)) {
      if (required && REQUIRED_DATES.includes(field))
        errors[field] = [fieldMessage('validation.required', {}, field)]
      return
    }
    const optional = field === 'announcementAt' && !filled(body[field])
    if (!optional && !isDate(body[field]))
      errors[field] = [fieldMessage('validation.required', {}, field)]
  })
}

function regionErrors(body, required, errors) {
  if (!has(body, 'regionIds')) {
    if (required) errors.regionIds = [fieldMessage('validation.required', {}, 'regionIds')]
    return
  }
  const ids = Array.isArray(body.regionIds) ? body.regionIds : null
  if (!ids || ids.length < 1 || ids.length > REGIONS_MAX || new Set(ids).size !== ids.length) {
    errors.regionIds = [
      fieldMessage('validation.between', { min: 1, max: REGIONS_MAX }, 'regionIds'),
    ]
    return
  }
  ids.forEach((id, index) => {
    if (!isPositiveInteger(id) || !findRegion(id))
      errors[`regionIds.${index}`] = [fieldMessage('validation.invalid', {}, 'regionIds')]
  })
}

function conditionErrors(body, errors) {
  if (filled(body.year) && !integerBetween(body.year, 2000, 2200))
    errors.year = [fieldMessage('validation.between', { min: 2000, max: 2200 }, 'year')]
  if (filled(body.scaleKind) && !SCALE_KINDS.includes(body.scaleKind))
    errors.scaleKind = [fieldMessage('validation.invalid', {}, 'scaleKind')]
  if (filled(body.minEntityAgeYears) && !integerBetween(body.minEntityAgeYears, 0, 100)) {
    errors.minEntityAgeYears = [
      fieldMessage('validation.between', { min: 0, max: 100 }, 'minEntityAgeYears'),
    ]
  }
  const score = filled(body.disqualificationScore) ? String(body.disqualificationScore) : null
  if (score !== null && (!SCORE_FORMAT.test(score) || Number(score) > 100)) {
    errors.disqualificationScore = [
      fieldMessage('validation.between', { min: 0, max: 100 }, 'disqualificationScore'),
    ]
  }
  if (filled(body.initialReviewSlaDays) && !integerBetween(body.initialReviewSlaDays, 1, 120)) {
    errors.initialReviewSlaDays = [
      fieldMessage('validation.between', { min: 1, max: 120 }, 'initialReviewSlaDays'),
    ]
  }
  if (
    has(body, 'participationCertificateEnabled') &&
    typeof body.participationCertificateEnabled !== 'boolean'
  ) {
    errors.participationCertificateEnabled = [
      fieldMessage('validation.boolean', {}, 'participationCertificateEnabled'),
    ]
  }
}

export function cycleErrors(body, { required }) {
  const errors = {}
  nameErrors(body, required, errors)
  dateErrors(body, required, errors)
  regionErrors(body, required, errors)
  if (has(body, 'criteriaVersionId') || required) {
    if (!isPositiveInteger(body.criteriaVersionId))
      errors.criteriaVersionId = [fieldMessage('validation.required', {}, 'criteriaVersionId')]
  }
  conditionErrors(body, errors)
  if (has(body, 'status')) errors.status = [fieldMessage('validation.invalid', {}, 'toStatus')]
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  return errors
}

export function storeDateOrderErrors(body) {
  const errors = {}
  const time = (field) => Date.parse(body[field])
  if (time('submissionEnd') <= time('submissionStart'))
    errors.submissionEnd = [fieldMessage('validation.date_order', {}, 'submissionEnd')]
  if (time('committeeStart') < time('submissionEnd'))
    errors.committeeStart = [fieldMessage('validation.date_order', {}, 'committeeStart')]
  if (time('committeeEnd') <= time('committeeStart'))
    errors.committeeEnd = [fieldMessage('validation.date_order', {}, 'committeeEnd')]
  if (filled(body.announcementAt) && time('announcementAt') < time('committeeEnd'))
    errors.announcementAt = [fieldMessage('validation.date_order', {}, 'announcementAt')]
  return errors
}

export function datesOrdered(dates, { submissionsOpen }) {
  const time = (field) => (dates[field] ? Date.parse(dates[field]) : null)
  const ordered =
    time('submissionEnd') > time('submissionStart') &&
    time('committeeStart') >= time('submissionEnd') &&
    time('committeeEnd') > time('committeeStart') &&
    (time('announcementAt') === null || time('announcementAt') >= time('committeeEnd'))
  return ordered && (!submissionsOpen || time('submissionEnd') > Date.now())
}

export function normalizedScore(value) {
  return Number(value).toFixed(2)
}
