import {
  INTEGRATION_ERROR_CODES,
  PROVIDER_ERROR_CODE,
  PROVIDER_ERROR_KINDS,
  UNKNOWN_PROVIDER_ERROR,
} from '@/constants/integrations'

export const TEST_FAILURES = Object.freeze({
  PROVIDER: 'provider',
  RATE_LIMITED: 'rate_limited',
  FORBIDDEN: 'forbidden',
  REJECTED: 'rejected',
  INVALID: 'invalid',
  UNEXPECTED: 'unexpected',
})

const RATE_LIMITED_STATUS = 429
const FORBIDDEN_STATUS = 403
const INVALID_STATUS = 422
const ERROR_KIND_FIELD = 'providerErrorKind'

function firstValue(value) {
  return Array.isArray(value) ? value[0] : value
}

export function knownErrorKind(kind) {
  return PROVIDER_ERROR_KINDS.includes(kind) ? kind : UNKNOWN_PROVIDER_ERROR
}

export function knownErrorCode(code) {
  return INTEGRATION_ERROR_CODES.includes(code) ? code : null
}

export function errorCodeOf(error) {
  return error?.normalized?.code ?? null
}

export function providerErrorKindOf(error) {
  return knownErrorKind(firstValue(error?.normalized?.fieldErrors?.[ERROR_KIND_FIELD]))
}

export function failedResultOf(error) {
  return error?.normalized?.meta?.result ?? null
}

export function retryAfterSecondsOf(error) {
  const header =
    error?.response?.headers?.['retry-after'] ?? error?.response?.headers?.get?.('retry-after')
  const seconds = Number.parseInt(header, 10)
  return Number.isFinite(seconds) && seconds > 0 ? seconds : null
}

export function testFailureOf(error) {
  const status = error?.response?.status
  if (errorCodeOf(error) === PROVIDER_ERROR_CODE) return TEST_FAILURES.PROVIDER
  if (status === RATE_LIMITED_STATUS) return TEST_FAILURES.RATE_LIMITED
  if (status === FORBIDDEN_STATUS) return TEST_FAILURES.FORBIDDEN
  if (knownErrorCode(errorCodeOf(error))) return TEST_FAILURES.REJECTED
  if (status === INVALID_STATUS) return TEST_FAILURES.INVALID
  return TEST_FAILURES.UNEXPECTED
}

export function firstFieldMessage(error) {
  const entries = Object.entries(error?.normalized?.fieldErrors ?? {}).filter(
    ([field]) => field !== ERROR_KIND_FIELD,
  )
  return entries.length ? (firstValue(entries[0][1]) ?? '') : ''
}

export function integrationErrorKey(code) {
  return knownErrorCode(code) ? `integrations.errors.${code}` : null
}
