export const SIGN_IN_IDENTIFIER_KINDS = Object.freeze(['email', 'mobile', 'unified_number'])

export const DEV_OTP_CODE = '482913'

export const DEV_TOTP_CODE = '246810'

export const OTP_CONFIG = {
  length: 6,
  expirySeconds: 300,
  resendCooldownSeconds: 60,
  maxAttempts: 5,
}

export const LOCKOUT_POLICY = Object.freeze({ maxAttempts: 5, lockSeconds: 15 * 60 })

export const CODE_LIMITS = Object.freeze({ perTenMinutes: 5, windowSeconds: 10 * 60 })

export const CODE_REQUEST_LIMITS = Object.freeze({
  perIdentifier: 3,
  perAddress: 10,
  windowSeconds: 10 * 60,
})

export const SIGN_IN_SMS_CAP = Object.freeze({ perHour: 100, windowSeconds: 60 * 60 })

export const MOCK_PASSWORD = 'Muasher@2026'

export const MOCK_RECOVERY_CODES = [
  'K7QX-2M9P',
  'R4TZ-8WN3',
  'H6JD-5VC1',
  'P2LS-9FB7',
  'W8NE-3QK5',
  'D5YR-7HT2',
  'M3GU-6XA8',
  'C9VB-4ZL6',
]

export const MOCK_MANUAL_ENTRY_KEY = 'MOCKONLYNOTASECRETKEY'

export const SESSION_ABSOLUTE_HOURS = 12

export const IDLE_TIMEOUT_MINUTES = {
  staff: 30,
  entity: 60,
}

export const RECOVERY_TOKENS = Object.freeze({
  EXPIRED_RESET: 'expired-reset-link-2026',
  INVITATIONS: {
    'invite-sarah-whitfield-2026': 4,
    'invite-expired-2026': 'expired',
    'invite-used-2026': 'used',
  },
})

export const PASSWORD_RULES = Object.freeze({ MIN: 12, MAX: 128 })
