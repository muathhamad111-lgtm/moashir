import {
  CRITERIA_DESCRIPTION_MAX_LENGTH,
  CRITERIA_NAME_MAX_LENGTH,
  CRITERIA_REASON_MAX_LENGTH,
  CRITERIA_REASON_MIN_LENGTH,
  CRITERIA_TEXT_MIN_LENGTH,
} from '@/constants/criteria'
import { isAcceptableWeight, toHundredths } from '@/utils/weight'
import { fieldMessage, reject } from './criteriaRejection'

const ATTACHMENT_KEY = /^[a-z0-9_]{1,40}$/

export class FieldCollector {
  constructor() {
    this.fields = {}
  }

  add(field, code) {
    this.fields[field] = [fieldMessage(code)]
  }

  assertValid() {
    if (Object.keys(this.fields).length) throw reject.validation(this.fields)
  }
}

function isText(value, max) {
  return (
    typeof value === 'string' &&
    value.trim().length >= CRITERIA_TEXT_MIN_LENGTH &&
    value.length <= max
  )
}

export function isLocalizedText(value, max = CRITERIA_NAME_MAX_LENGTH) {
  return (
    Boolean(value) && typeof value === 'object' && isText(value.ar, max) && isText(value.en, max)
  )
}

export function readLocalized(
  errors,
  body,
  field,
  { required = false, max = CRITERIA_NAME_MAX_LENGTH } = {},
) {
  const value = body[field]
  if (value === undefined) {
    if (required) errors.add(field, 'criteria.text_required')
    return undefined
  }
  if (value === null && !required) return null
  if (!isLocalizedText(value, max)) {
    errors.add(field, 'criteria.text_required')
    return undefined
  }
  return { ar: value.ar.trim(), en: value.en.trim() }
}

export function readDescription(errors, body, field = 'description', required = false) {
  return readLocalized(errors, body, field, { required, max: CRITERIA_DESCRIPTION_MAX_LENGTH })
}

export function readWeight(errors, body, required = false) {
  const value = body.weight
  if (value === undefined || value === null || value === '') {
    if (required) errors.add('weight', 'field.required')
    return null
  }
  if (!isAcceptableWeight(value)) {
    errors.add('weight', 'field.weight')
    return null
  }
  return toHundredths(value)
}

export function readBoolean(errors, body, field) {
  const value = body[field]
  if (value === undefined) return undefined
  if (typeof value !== 'boolean') {
    errors.add(field, 'field.invalid')
    return undefined
  }
  return value
}

export function readEnum(errors, body, field, allowed) {
  const value = body[field]
  if (value === undefined) return undefined
  if (!allowed.includes(value)) {
    errors.add(field, 'field.invalid')
    return undefined
  }
  return value
}

function isAttachment(item) {
  if (!item || typeof item !== 'object' || typeof item.required !== 'boolean') return false
  const keyValid = item.key === undefined || item.key === null || ATTACHMENT_KEY.test(item.key)
  return keyValid && isLocalizedText(item.name)
}

export function readAttachments(errors, body) {
  if (body.attachments === undefined) return undefined
  if (!Array.isArray(body.attachments)) {
    errors.add('attachments', 'field.invalid')
    return undefined
  }
  body.attachments.forEach((item, index) => {
    if (!isAttachment(item)) errors.add(`attachments.${index}.name`, 'criteria.text_required')
  })
  return body.attachments
}

export function prohibit(errors, body, fields) {
  fields.forEach((field) => {
    if (body[field] !== undefined) errors.add(field, 'field.prohibited')
  })
}

export function readReason(errors, body) {
  const reason = typeof body.reason === 'string' ? body.reason.trim() : ''
  if (!reason) return null
  if (reason.length < CRITERIA_REASON_MIN_LENGTH || reason.length > CRITERIA_REASON_MAX_LENGTH) {
    errors.add('reason', 'criteria.reason_length')
  }
  return reason
}

export function readOrderedIds(body, expectedIds) {
  const ids = Array.isArray(body.orderedIds) ? body.orderedIds.map(Number) : []
  const complete = ids.length === expectedIds.length && new Set(ids).size === ids.length
  if (!complete || !expectedIds.every((id) => ids.includes(id))) throw reject.orderIncomplete()
  return ids
}
