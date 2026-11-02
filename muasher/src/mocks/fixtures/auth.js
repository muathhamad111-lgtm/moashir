export const loginModesFixture = {
  emailPassword: true,
  emailOtp: true,
  smsOtp: true,
}

export const DEV_OTP_CODE = '482913'

export const DEV_TOTP_CODE = '246810'

export const OTP_CONFIG = {
  length: 6,
  expirySeconds: 120,
  resendCooldownSeconds: 30,
}

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
