import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'
import { reasonError, throwIfErrors, trimmed } from './accreditedValidation'
import { EVIDENCE_TYPE_CATALOG, SLA_LIMITS, UPLOAD_LIMITS } from './assessmentSettingsStore'
import { TEXT_KEYS, writePathSettings } from './pathSettingsStore'

const INTEGER_RULES = {
  reviewSlaDays: SLA_LIMITS,
  uploadMaxFileMb: { min: 1, max: UPLOAD_LIMITS.maxFileMb },
  evidenceMaxFilesPerSub: { min: 1, max: UPLOAD_LIMITS.maxFilesPerSub },
}
const PRICE = /^\d{1,7}\.\d{2}$/
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

function priceErrors(body, isPaid, errors) {
  if (!('price' in body)) return
  if (!isPaid) errors.price = invalid()
  else if (typeof body.price !== 'string' || !PRICE.test(body.price) || Number(body.price) <= 0)
    errors.price = invalid()
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

export function updatePathSettings(user, path, isPaid, body) {
  const errors = {}
  integerErrors(body, errors)
  typesErrors(body, errors)
  priceErrors(body, isPaid, errors)
  textErrors(body, errors)
  const reason = reasonError(body.reason)
  if (reason) errors.reason = reason
  throwIfErrors(errors)
  const keys = Object.keys(body).filter((key) => key !== 'reason')
  if (!keys.length) return
  if (user.superAdmin && !trimmed(body.reason)) throw assessmentReject.reasonRequired()
  writePathSettings(path, body)
}
