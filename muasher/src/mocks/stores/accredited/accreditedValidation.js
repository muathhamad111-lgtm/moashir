import { assessmentReject, fieldMessage } from '../assessments/assessmentRejection'

const REASON = { MIN: 10, MAX: 1000 }
const POSITIVE_INTEGER = /^[1-9]\d*$/

export function isPlainObject(value) {
  return Object.prototype.toString.call(value) === '[object Object]'
}

export function isPositiveInteger(value) {
  return POSITIVE_INTEGER.test(String(value ?? ''))
}

export function trimmed(value) {
  return typeof value === 'string' ? value.trim() : ''
}

export function lengthError(value, { min = 0, max, attribute }) {
  const text = trimmed(value)
  if (typeof value !== 'string' || text.length < min || text.length > max) {
    return [fieldMessage('validation.between_chars', { min, max }, attribute)]
  }
  return null
}

export function reasonError(reason, { required = false, min = REASON.MIN } = {}) {
  if (reason === undefined || reason === null || reason === '') return required ? [fieldMessage('validation.required', {}, 'reason')] : null
  return lengthError(reason, { min, max: REASON.MAX, attribute: 'reason' })
}

export function throwIfErrors(errors) {
  if (Object.keys(errors).length) throw assessmentReject.validation(errors)
}

export function optionalReason(body, options) {
  const error = reasonError(body.reason, options)
  if (error) throw assessmentReject.validation({ reason: error })
  return trimmed(body.reason) || null
}
