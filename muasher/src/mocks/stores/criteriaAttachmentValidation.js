import { CRITERIA_NAME_MAX_LENGTH, CRITERIA_TEXT_MIN_LENGTH } from '@/constants/criteria'
import { EVIDENCE_TYPE_EXTENSIONS } from '@/constants/accredited'

const ATTACHMENT_KEY = /^[a-z0-9_]{1,40}$/
const GUIDANCE_MAX_LENGTH = 500
const MAX_FILES = 50
const FILE_TYPES = Object.keys(EVIDENCE_TYPE_EXTENSIONS)
const LOCALES = ['ar', 'en']

function isAbsent(value) {
  return value === undefined || value === null
}

function isText(value, max) {
  return (
    typeof value === 'string' &&
    value.trim().length >= CRITERIA_TEXT_MIN_LENGTH &&
    value.length <= max
  )
}

function checkText(errors, field, value, max) {
  LOCALES.forEach((locale) => {
    if (!isText(value?.[locale], max)) errors.add(`${field}.${locale}`, 'criteria.text_required')
  })
}

function hasValidTypes(types) {
  return (
    Array.isArray(types) &&
    types.length > 0 &&
    new Set(types).size === types.length &&
    types.every((type) => FILE_TYPES.includes(type))
  )
}

function hasValidLimit(limit) {
  return Number.isInteger(limit) && limit >= 1 && limit <= MAX_FILES
}

function checkExtras(errors, field, item) {
  if (!isAbsent(item.guidance)) {
    checkText(errors, `${field}.guidance`, item.guidance, GUIDANCE_MAX_LENGTH)
  }
  if (!isAbsent(item.allowedTypes) && !hasValidTypes(item.allowedTypes)) {
    errors.add(`${field}.allowedTypes`, 'field.invalid')
  }
  if (!isAbsent(item.maxFiles) && !hasValidLimit(item.maxFiles)) {
    errors.add(`${field}.maxFiles`, 'field.invalid')
  }
}

function checkAttachment(errors, item, index) {
  const field = `attachments.${index}`
  if (!item || typeof item !== 'object' || typeof item.required !== 'boolean') {
    errors.add(field, 'field.invalid')
    return
  }
  if (!isAbsent(item.key) && !ATTACHMENT_KEY.test(item.key)) {
    errors.add(`${field}.key`, 'field.invalid')
  }
  checkText(errors, `${field}.name`, item.name, CRITERIA_NAME_MAX_LENGTH)
  checkExtras(errors, field, item)
}

export function readAttachments(errors, body) {
  if (body.attachments === undefined) return undefined
  if (!Array.isArray(body.attachments)) {
    errors.add('attachments', 'field.invalid')
    return undefined
  }
  body.attachments.forEach((item, index) => checkAttachment(errors, item, index))
  return body.attachments
}
