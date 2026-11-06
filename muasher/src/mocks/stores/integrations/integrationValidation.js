import { fieldMessage, integrationReject } from './integrationRejection'
import { SECRET_PATCH, patchKindOf } from './integrationSecrets'

const REASON = { min: 10, max: 1000 }
const NO_WHITESPACE = /^\S+$/
const SAUDI_MOBILE = /^\+9665\d{8}$/
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const HOSTNAME = /^(?=.{1,253}$)(?!-)[A-Za-z0-9-]{1,63}(?<!-)(?:\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))+$/

export function fieldErrors() {
  const fields = {}
  return {
    add(path, code, params = {}) {
      fields[path] = [...(fields[path] ?? []), fieldMessage(code, params)]
    },
    throwIfAny() {
      if (Object.keys(fields).length) throw integrationReject.validation(fields)
    },
  }
}

export function has(body, key) {
  return body !== null && typeof body === 'object' && Object.hasOwn(body, key)
}

function isBlank(value) {
  return value === null || value === undefined || value === ''
}

export function checkToken(
  errors,
  path,
  value,
  { min, max, prefix = '', allowWhitespace = false },
) {
  if (isBlank(value)) return
  const text = String(value)
  if (text.length < min || text.length > max) errors.add(path, 'field.length', { min, max })
  else if (prefix && !text.startsWith(prefix)) errors.add(path, 'field.prefix', { prefix })
  else if (!allowWhitespace && !NO_WHITESPACE.test(text)) errors.add(path, 'field.invalid')
}

export function checkSecret(errors, path, value, rules) {
  if (value && typeof value === 'object') {
    if (patchKindOf(value) !== SECRET_PATCH.CLEAR) errors.add(path, 'field.invalid')
    return
  }
  if (patchKindOf(value) === SECRET_PATCH.REPLACE) checkToken(errors, path, value, rules)
}

export function checkChoice(errors, body, key, allowed, path = key) {
  if (has(body, key) && !allowed.includes(body[key])) errors.add(path, 'field.invalid')
}

export function checkNullableChoice(errors, body, key, allowed, path = key) {
  if (has(body, key) && body[key] !== null && !allowed.includes(body[key]))
    errors.add(path, 'field.invalid')
}

export function checkSubset(errors, body, key, allowed, { min = 0 } = {}) {
  if (!has(body, key)) return
  const values = body[key]
  if (!Array.isArray(values) || values.some((value) => !allowed.includes(value)))
    errors.add(key, 'field.invalid')
  else if (new Set(values).size !== values.length) errors.add(key, 'field.invalid')
  else if (values.length < min) errors.add(key, 'field.required')
}

export function checkText(errors, path, value, { min = 0, max }) {
  if (isBlank(value)) return
  const text = String(value)
  if (text.length < min || text.length > max) errors.add(path, 'field.length', { min, max })
}

export function checkLocalized(errors, body, key, { max }) {
  if (!has(body, key) || body[key] === null) return
  for (const locale of ['ar', 'en']) {
    const text = String(body[key]?.[locale] ?? '').trim()
    if (!text) errors.add(`${key}.${locale}`, 'field.required')
    else if (text.length > max) errors.add(`${key}.${locale}`, 'field.length', { min: 1, max })
  }
}

export function checkInteger(errors, body, key, { min, max, allowed = null, path = key }) {
  if (!has(body, key)) return
  const number = body[key]
  const valid =
    Number.isInteger(number) &&
    (allowed ? allowed.includes(number) : number >= min && number <= max)
  if (!valid) errors.add(path, 'field.invalid')
}

export function checkBoolean(errors, body, key, path = key) {
  if (has(body, key) && typeof body[key] !== 'boolean') errors.add(path, 'field.invalid')
}

export function checkEmail(errors, body, key) {
  if (!has(body, key) || isBlank(body[key])) return
  if (!EMAIL.test(String(body[key]))) errors.add(key, 'field.invalid')
}

export function checkHostname(errors, body, key) {
  if (!has(body, key) || isBlank(body[key])) return
  if (!HOSTNAME.test(String(body[key]))) errors.add(key, 'field.invalid')
}

export function isSaudiMobile(value) {
  return SAUDI_MOBILE.test(String(value ?? ''))
}

export function checkReason(errors, body) {
  if (isBlank(body.reason)) return
  const length = String(body.reason).trim().length
  if (length < REASON.min || length > REASON.max)
    errors.add('reason', 'field.reason_min', { min: REASON.min })
}

export function hasSecretChange(values) {
  return values.some((value) => patchKindOf(value) !== SECRET_PATCH.KEEP)
}

export function requireReason(body, needed) {
  if (needed && isBlank(String(body.reason ?? '').trim()))
    throw integrationReject.field('reason_required', 'reason')
}
