import { AUTH_ERROR_CODES as CODES, CODE_FAILURES, SIGN_IN_FAILURES } from '@/constants/signIn'
import { retryAfterSecondsOf } from '@/utils/integrations/providerError'

const SECONDS_PER_MINUTE = 60
const TOO_MANY_REQUESTS = 429

const SIGN_IN_BY_CODE = Object.freeze({
  [CODES.CREDENTIALS_INVALID]: SIGN_IN_FAILURES.CREDENTIALS,
  [CODES.ACCOUNT_LOCKED]: SIGN_IN_FAILURES.LOCKED,
  [CODES.ACCOUNT_DEACTIVATED]: SIGN_IN_FAILURES.DEACTIVATED,
  [CODES.RATE_LIMITED]: SIGN_IN_FAILURES.THROTTLED,
  [CODES.OTP_COOLDOWN]: SIGN_IN_FAILURES.THROTTLED,
})

const CODE_BY_ERROR = Object.freeze({
  [CODES.OTP_INVALID]: CODE_FAILURES.INVALID,
  [CODES.OTP_EXPIRED]: CODE_FAILURES.EXPIRED,
  [CODES.OTP_CHALLENGE_INVALID]: CODE_FAILURES.CHALLENGE,
  [CODES.OTP_ATTEMPTS_EXCEEDED]: CODE_FAILURES.ATTEMPTS,
  [CODES.ACCOUNT_LOCKED]: CODE_FAILURES.LOCKED,
  [CODES.OTP_COOLDOWN]: CODE_FAILURES.COOLDOWN,
  [CODES.RATE_LIMITED]: CODE_FAILURES.COOLDOWN,
})

const ENDS_THE_CHALLENGE = Object.freeze([
  CODE_FAILURES.CHALLENGE,
  CODE_FAILURES.ATTEMPTS,
  CODE_FAILURES.LOCKED,
])

function errorCodeOf(error) {
  return error?.normalized?.code ?? null
}

function isTooManyRequests(error) {
  return (error?.response?.status ?? error?.normalized?.status) === TOO_MANY_REQUESTS
}

export function waitMinutesOf(error) {
  const seconds = retryAfterSecondsOf(error)
  return seconds ? Math.ceil(seconds / SECONDS_PER_MINUTE) : null
}

export function signInFailureOf(error) {
  const fallback = isTooManyRequests(error)
    ? SIGN_IN_FAILURES.THROTTLED
    : SIGN_IN_FAILURES.UNEXPECTED
  return { kind: SIGN_IN_BY_CODE[errorCodeOf(error)] ?? fallback, minutes: waitMinutesOf(error) }
}

export function codeFailureOf(error) {
  const fallback = isTooManyRequests(error) ? CODE_FAILURES.COOLDOWN : CODE_FAILURES.UNEXPECTED
  return { kind: CODE_BY_ERROR[errorCodeOf(error)] ?? fallback, minutes: waitMinutesOf(error) }
}

export function endsTheChallenge(failure) {
  return ENDS_THE_CHALLENGE.includes(failure.kind)
}

export function failureText(t, group, failure) {
  const text = t(`auth.failures.${group}.${failure.kind}`)
  if (!failure.minutes) return text
  return `${text} ${t('auth.failures.retryIn', { count: failure.minutes }, failure.minutes)}`
}
