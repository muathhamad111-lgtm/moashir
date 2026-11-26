import { OTP_PURPOSES, createChallenge } from './otpChallenges'

// Mirrors the server: the official e-mail is proven inside the registration form; the code check
// returns a single-use token bound to that address, which the registration must carry.
const TOKEN_LIFETIME_MS = 30 * 60 * 1000
const tokens = new Map()

export const REGISTRATION_EMAIL_PURPOSE = OTP_PURPOSES.REGISTRATION_EMAIL

export function normalizedEmail(value) {
  return String(value ?? '')
    .trim()
    .toLowerCase()
}

export function issueRegistrationEmailChallenge(email) {
  return createChallenge({ kind: 'registration', email }, 'email', REGISTRATION_EMAIL_PURPOSE)
}

export function issueEmailVerificationToken(email, now = Date.now()) {
  const token = crypto.randomUUID()
  tokens.set(token, { email, expiresAt: now + TOKEN_LIFETIME_MS })
  return token
}

export function emailTokenValid(token, email, now = Date.now()) {
  const entry = tokens.get(String(token ?? ''))
  return Boolean(entry) && entry.expiresAt >= now && entry.email === email
}

export function consumeEmailVerificationToken(token) {
  tokens.delete(String(token ?? ''))
}

export function resetRegistrationEmail() {
  tokens.clear()
}
