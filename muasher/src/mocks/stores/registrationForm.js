import { readJson } from './entityRejection'

const FORM_TYPE = /multipart\/form-data/i
const BOOLEAN_KEYS = Object.freeze([
  'termsAccepted',
  'marketingConsent',
  'hasGovernanceGrade',
  'hasStrategicPlan',
])
const BRACKETED = /^([^[]+)\[([^\]]+)\]$/
const FORM_BOOLEANS = Object.freeze({ 1: true, 0: false })

function fieldValue(key, value) {
  if (value === '') return null
  if (BOOLEAN_KEYS.includes(key) && value in FORM_BOOLEANS) return FORM_BOOLEANS[value]
  return value
}

function assign(body, key, value) {
  const nested = BRACKETED.exec(key)
  if (!nested) {
    body[key] = fieldValue(key, value)
    return
  }
  const [, parent, child] = nested
  body[parent] = { ...body[parent], [child]: fieldValue(child, value) }
}

/** The registration body the way the API reads it: a multipart form with its files, or JSON for older callers. */
export async function readRegistration(request) {
  if (!FORM_TYPE.test(request.headers.get('content-type') ?? '')) return readJson(request)
  const body = {}
  ;(await request.formData()).forEach((value, key) => assign(body, key, value))
  return body
}
