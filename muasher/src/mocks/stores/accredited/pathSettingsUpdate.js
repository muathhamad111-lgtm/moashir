import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { reasonError, throwIfErrors, trimmed } from './accreditedValidation'
import { EVIDENCE_TYPE_CATALOG, SLA_LIMITS, UPLOAD_LIMITS } from './assessmentSettingsStore'
import { TEXT_KEYS, writePathSettings } from './pathSettingsStore'
import { PATH_DESCRIPTION_MAX } from '../paths/pathIdentityStore'

const IDENTITY_RULES = { description: PATH_DESCRIPTION_MAX }
const SELF_KEYS = ['description', 'reason']

const INTEGER_RULES = {
  reviewSlaDays: SLA_LIMITS,
  uploadMaxFileMb: { min: 1, max: UPLOAD_LIMITS.maxFileMb },
  evidenceMaxFilesPerSub: { min: 1, max: UPLOAD_LIMITS.maxFilesPerSub },
}
const TEXT_MAX = 2000

function invalid(code = 'validation.invalid', params = {}) {
  return [fieldMessage(code, params, 'settings')]
}

function integerErrors(body, errors) {
  Object.entries(INTEGER_RULES).forEach(([key, { min, max }]) => {
    if (!(key in body)) return
    const value = body[key]
    if (!Number.isInteger(value) || value < min || value > max)
      errors[key] = invalid('validation.between', { min, max })
  })
}

function typesErrors(body, errors) {
  if (!('evidenceAllowedTypes' in body)) return
  const list = body.evidenceAllowedTypes
  const valid =
    Array.isArray(list) &&
    list.length > 0 &&
    new Set(list).size === list.length &&
    list.every((type) => EVIDENCE_TYPE_CATALOG.includes(type))
  if (!valid) errors.evidenceAllowedTypes = invalid()
}

function priceErrors(body, errors) {
  if ('price' in body) errors.price = invalid()
}

function textErrors(body, errors) {
  if (!('entityTexts' in body)) return
  const submitted = body.entityTexts
  if (typeof submitted !== 'object' || submitted === null) {
    errors.entityTexts = invalid()
    return
  }
  Object.entries(submitted).forEach(([key, value]) => {
    if (!TEXT_KEYS.includes(key)) errors.entityTexts = invalid()
    else if (value === null) return
    else
      ['ar', 'en'].forEach((language) => {
        const text = trimmed(value?.[language])
        if (!text) errors[`entityTexts.${key}.${language}`] = [fieldMessage('path_texts.pair')]
        else if (text.length > TEXT_MAX)
          errors[`entityTexts.${key}.${language}`] = invalid('validation.max_chars', {
            max: TEXT_MAX,
          })
      })
  })
}

// Path names are fixed: a name in the request is refused, as on the server.
function nameErrors(body, errors) {
  if ('name' in body) errors.name = invalid('validation.prohibited')
}

function identityErrors(body, errors) {
  Object.entries(IDENTITY_RULES).forEach(([field, max]) => {
    if (!(field in body)) return
    ;['ar', 'en'].forEach((language) => {
      const text = trimmed(body[field]?.[language])
      if (!text) errors[`${field}.${language}`] = invalid('validation.required')
      else if (text.length > max)
        errors[`${field}.${language}`] = invalid('validation.max_chars', { max })
    })
  })
}

function selfErrors(path, body, errors) {
  if (path !== 'self') return
  Object.keys(body)
    .filter((key) => !SELF_KEYS.includes(key))
    .forEach((key) => {
      errors[key] = invalid()
    })
}

export function updatePathSettings(user, path, body) {
  const errors = {}
  nameErrors(body, errors)
  identityErrors(body, errors)
  selfErrors(path, body, errors)
  integerErrors(body, errors)
  typesErrors(body, errors)
  priceErrors(body, errors)
  textErrors(body, errors)
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
  const keys = Object.keys(body).filter((key) => key !== 'reason')
  if (!keys.length) return
  if (user.superAdmin && !trimmed(body.reason)) throw assessmentReject.reasonRequired()
  writePathSettings(path, body)
}
