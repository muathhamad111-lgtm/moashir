import { allEntities } from './entitiesStore'

const LICENSE_PATTERN = /^\d{3,20}$/
const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/
const EARLIEST = '1900-01-01'
const WEBSITE_MAX = 255
export const REGISTRY_KEYS = ['licenseNumber', 'foundedOn', 'websiteUrl']

function cleared(value) {
  return value === null || String(value).trim() === ''
}

function todayInRiyadh() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(new Date())
}

function websiteIssue(value) {
  const text = String(value).trim()
  if (text.length > WEBSITE_MAX || !text.startsWith('https://') || /\s/.test(text))
    return 'field.website'
  try {
    return new URL(text).hostname.includes('.') ? null : 'field.website'
  } catch {
    return 'field.website'
  }
}

function licenseIssue(value, entityId) {
  const text = String(value).trim()
  if (!LICENSE_PATTERN.test(text)) return 'field.license'
  const taken = allEntities().some(
    (entity) => entity.id !== entityId && entity.licenseNumber === text,
  )
  return taken ? 'field.license_taken' : null
}

function foundedIssue(value) {
  const text = String(value).trim()
  return DATE_PATTERN.test(text) && text >= EARLIEST && text <= todayInRiyadh()
    ? null
    : 'field.founded_on'
}

const CHECKS = { licenseNumber: licenseIssue, foundedOn: foundedIssue, websiteUrl: websiteIssue }

export function checkRegistry(errors, body, entityId) {
  REGISTRY_KEYS.filter((key) => key in body && !cleared(body[key])).forEach((key) => {
    const issue = CHECKS[key](body[key], entityId)
    if (issue) errors.add(key, issue)
  })
}

export function applyRegistry(entity, body) {
  REGISTRY_KEYS.filter((key) => key in body).forEach((key) => {
    entity[key] = cleared(body[key]) ? null : String(body[key]).trim()
  })
  if (entity.foundedOn) entity.foundingYear = Number(entity.foundedOn.slice(0, 4))
  return entity
}
