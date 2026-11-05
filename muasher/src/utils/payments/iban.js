import { EXAMPLE_IBAN, IBAN_RULES } from '@/constants/bankTransfer'

const LETTER_OFFSET = 55
const CHECK_CHUNK = 7
const WHITESPACE = /\s+/g
const GROUP = new RegExp(`.{1,${IBAN_RULES.GROUP_SIZE}}`, 'g')
const LAST_DIGITS = 4

export const IBAN_ISSUES = Object.freeze({
  REQUIRED: 'required',
  CHARACTERS: 'characters',
  COUNTRY: 'country',
  LENGTH: 'length',
  FORMAT: 'format',
  CHECKSUM: 'checksum',
})

export function normalizeIban(value) {
  return String(value ?? '')
    .replace(WHITESPACE, '')
    .toUpperCase()
}

export function formatIban(value) {
  const compact = normalizeIban(value).slice(0, IBAN_RULES.LENGTH)
  return (compact.match(GROUP) ?? []).join(' ')
}

export function ibanLast4(value) {
  return normalizeIban(value).slice(-LAST_DIGITS)
}

export function isExampleIban(value) {
  return normalizeIban(value) === EXAMPLE_IBAN
}

function numericForm(iban) {
  const rearranged = `${iban.slice(4)}${iban.slice(0, 4)}`
  return [...rearranged]
    .map((character) =>
      /[A-Z]/.test(character) ? String(character.charCodeAt(0) - LETTER_OFFSET) : character,
    )
    .join('')
}

export function ibanChecksumValid(value) {
  const digits = numericForm(normalizeIban(value))
  let remainder = 0
  for (let index = 0; index < digits.length; index += CHECK_CHUNK) {
    remainder = Number(`${remainder}${digits.slice(index, index + CHECK_CHUNK)}`) % IBAN_RULES.MOD
  }
  return remainder === IBAN_RULES.REMAINDER
}

export function ibanIssue(value) {
  const iban = normalizeIban(value)
  if (!iban) return IBAN_ISSUES.REQUIRED
  if (!IBAN_RULES.ALLOWED_PATTERN.test(iban)) return IBAN_ISSUES.CHARACTERS
  if (!iban.startsWith(IBAN_RULES.COUNTRY)) return IBAN_ISSUES.COUNTRY
  if (iban.length !== IBAN_RULES.LENGTH) return IBAN_ISSUES.LENGTH
  if (!IBAN_RULES.BODY_PATTERN.test(iban)) return IBAN_ISSUES.FORMAT
  return ibanChecksumValid(iban) ? null : IBAN_ISSUES.CHECKSUM
}
