export const SIGN_IN_IDENTIFIERS = Object.freeze({
  EMAIL: 'email',
  MOBILE: 'mobile',
  UNIFIED_NUMBER: 'unified_number',
})

export const SIGN_IN_IDENTIFIER_VALUES = Object.freeze(Object.values(SIGN_IN_IDENTIFIERS))

export const SIGN_IN_FAILURES = Object.freeze({
  CREDENTIALS: 'credentials',
  LOCKED: 'locked',
  DEACTIVATED: 'deactivated',
  THROTTLED: 'throttled',
  UNEXPECTED: 'unexpected',
})

export const CODE_FAILURES = Object.freeze({
  INVALID: 'invalid',
  EXPIRED: 'expired',
  CHALLENGE: 'challenge',
  ATTEMPTS: 'attempts',
  LOCKED: 'locked',
  COOLDOWN: 'cooldown',
  UNEXPECTED: 'unexpected',
})

export const AUTH_ERROR_CODES = Object.freeze({
  CREDENTIALS_INVALID: 'credentials.invalid',
  ACCOUNT_LOCKED: 'account_locked',
  ACCOUNT_DEACTIVATED: 'account_deactivated',
  RATE_LIMITED: 'rate_limited',
  OTP_INVALID: 'otp.invalid',
  OTP_EXPIRED: 'otp.expired',
  OTP_CHALLENGE_INVALID: 'otp.challenge_invalid',
  OTP_ATTEMPTS_EXCEEDED: 'otp.attempts_exceeded',
  OTP_COOLDOWN: 'otp.cooldown',
})

export const SIGN_IN_REJECTIONS = Object.freeze([403, 422, 423, 429])

export const IDENTIFIER_MAX_LENGTH = 254
