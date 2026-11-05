import { UNIFIED_NUMBER_PATTERN } from '@/constants/entityEnums'
import { unifiedNumberTaken } from './entitiesStore'

const FIELD = 'unifiedNumber'

function textOf(body) {
  return String(body[FIELD] ?? '').trim()
}

function issueOf(value, entityId) {
  if (!value) return 'field.required'
  if (!UNIFIED_NUMBER_PATTERN.test(value)) return 'field.unified_number'
  return unifiedNumberTaken(value, entityId) ? 'field.unified_number_taken' : null
}

export function checkUnifiedNumber(errors, body, { entityId = null, required = false } = {}) {
  if (!required && !(FIELD in body)) return
  const issue = issueOf(textOf(body), entityId)
  if (issue) errors.add(FIELD, issue)
}

export function unifiedNumberFrom(body) {
  return textOf(body)
}

export function applyUnifiedNumber(entity, body) {
  if (FIELD in body) entity.unifiedNumber = textOf(body)
  return entity
}
