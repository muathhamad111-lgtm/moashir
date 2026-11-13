import { DEV_OTP_CODE, OTP_CONFIG } from '../fixtures/auth'

const challenges = new Map()

export const OTP_PURPOSES = {
  LOGIN: 'login',
  SIGN_IN_CODE: 'sign_in_code',
  EMAIL_CHANGE: 'email_change',
  PHONE: 'phone',
}

export const SIGN_IN_PURPOSES = Object.freeze([OTP_PURPOSES.LOGIN, OTP_PURPOSES.SIGN_IN_CODE])

export function createChallenge(account, channel = 'email', purpose = OTP_PURPOSES.LOGIN) {
  const now = Date.now()
  const challenge = {
    id: crypto.randomUUID(),
    account,
    channel,
    purpose,
    attempts: 0,
    expiresAt: now + OTP_CONFIG.expirySeconds * 1000,
    resendAvailableAt: now + OTP_CONFIG.resendCooldownSeconds * 1000,
  }
  challenges.set(challenge.id, challenge)
  return challenge
}

export function findChallenge(id, purposes = OTP_PURPOSES.LOGIN) {
  const challenge = challenges.get(String(id)) ?? null
  return [purposes].flat().includes(challenge?.purpose) ? challenge : null
}

export function consumeChallenge(id) {
  challenges.delete(String(id))
}

export function isExpired(challenge) {
  return Date.now() > challenge.expiresAt
}

export function acceptsCode(challenge, code) {
  return Boolean(challenge?.account) && !isExpired(challenge) && String(code) === DEV_OTP_CODE
}

export function recordWrongAttempt(challenge) {
  challenge.attempts += 1
  return challenge.attempts >= OTP_CONFIG.maxAttempts
}

export function challengePayload(challenge) {
  return {
    challengeId: challenge.id,
    channel: challenge.channel,
    length: OTP_CONFIG.length,
    expiresAt: new Date(challenge.expiresAt).toISOString(),
    resendAvailableAt: new Date(challenge.resendAvailableAt).toISOString(),
  }
}
