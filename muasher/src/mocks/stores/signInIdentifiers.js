import { UNIFIED_NUMBER_PATTERN } from '@/constants/entityEnums'
import { findAccountByEmail } from './accounts'
import { findEntityByUnifiedNumber, findEntityByVerifiedPhone } from './entitiesStore'

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const SAUDI_MOBILE_INPUT = /^(?:\+?966|00966|0)?(5\d{8})$/
const SEPARATORS = /[\s-]/g

function entityAccount(entity) {
  return entity ? { kind: 'entity', record: entity } : null
}

export function parseIdentifier(raw) {
  const text = String(raw ?? '').trim()
  if (text.includes('@')) return EMAIL_PATTERN.test(text) ? { type: 'email', value: text } : null
  const compact = text.replace(SEPARATORS, '')
  if (UNIFIED_NUMBER_PATTERN.test(compact)) return { type: 'unified_number', value: compact }
  const mobile = SAUDI_MOBILE_INPUT.exec(compact)
  return mobile ? { type: 'mobile', value: `+966${mobile[1]}` } : null
}

const RESOLVERS = {
  email: (value) => findAccountByEmail(value),
  unified_number: (value) => entityAccount(findEntityByUnifiedNumber(value)),
  mobile: (value) => entityAccount(findEntityByVerifiedPhone(value)),
}

export function accountOfIdentifier(identifier) {
  return RESOLVERS[identifier.type](identifier.value)
}
