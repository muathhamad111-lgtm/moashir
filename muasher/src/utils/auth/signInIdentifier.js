import { SIGN_IN_IDENTIFIERS } from '@/constants/signIn'
import { UNIFIED_NUMBER_PATTERN } from '@/constants/entityEnums'
import { toWesternDigits } from '@/utils/numerals'

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const SAUDI_MOBILE_INPUT = /^(?:\+?966|00966|0)?5\d{8}$/
const SAUDI_MOBILE_SUBSCRIBER = /(5\d{8})$/
const SEPARATORS = /[\s-]/g
const VISIBLE_LOCAL_CHARACTERS = 2
const VISIBLE_MOBILE_DIGITS = 2
const MASK = '•••'
const MOBILE_MASK = '•••••'
const NATIONAL_PREFIX = '0'

function compact(value) {
  return toWesternDigits(value).replace(SEPARATORS, '')
}

export function identifierTypeOf(value) {
  const text = toWesternDigits(value)
  if (text.includes('@')) return EMAIL_PATTERN.test(text) ? SIGN_IN_IDENTIFIERS.EMAIL : null
  const digits = compact(text)
  if (UNIFIED_NUMBER_PATTERN.test(digits)) return SIGN_IN_IDENTIFIERS.UNIFIED_NUMBER
  return SAUDI_MOBILE_INPUT.test(digits) ? SIGN_IN_IDENTIFIERS.MOBILE : null
}

export function identifierForRequest(value) {
  const text = toWesternDigits(value)
  return identifierTypeOf(text) === SIGN_IN_IDENTIFIERS.EMAIL ? text.toLowerCase() : compact(text)
}

export function maskEmail(email) {
  const [local, domain] = String(email ?? '')
    .trim()
    .split('@')
  if (!local || !domain) return ''
  return `${local.slice(0, VISIBLE_LOCAL_CHARACTERS)}${MASK}@${domain}`
}

export function maskMobile(mobile) {
  if (identifierTypeOf(mobile) !== SIGN_IN_IDENTIFIERS.MOBILE) return ''
  const national = `${NATIONAL_PREFIX}${SAUDI_MOBILE_SUBSCRIBER.exec(compact(mobile))[1]}`
  return `${national.slice(0, VISIBLE_MOBILE_DIGITS)}${MOBILE_MASK}${national.slice(-VISIBLE_MOBILE_DIGITS)}`
}
