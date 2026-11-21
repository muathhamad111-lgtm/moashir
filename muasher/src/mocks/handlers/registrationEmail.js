import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { readJson } from '../stores/entityRejection'
import {
  OTP_PURPOSES,
  acceptsCode,
  challengePayload,
  consumeChallenge,
  findChallenge,
  isExpired,
  recordWrongAttempt,
} from '../stores/otpChallenges'
import {
  issueEmailVerificationToken,
  issueRegistrationEmailChallenge,
  normalizedEmail,
} from '../stores/registrationEmail'
import { authField, authReject, authRoute } from './authRoute'

const BASE = '/api/v1/auth/register/email-code'
const EMAIL_SHAPE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const LATENCY = 320

function emailOf(body) {
  const email = normalizedEmail(body.officialEmail)
  if (!EMAIL_SHAPE.test(email))
    throw authReject.validation({ officialEmail: [authField('field.email')] })
  return email
}

function challengeOf(body) {
  const challenge = findChallenge(body.challengeId, OTP_PURPOSES.REGISTRATION_EMAIL)
  if (!challenge) throw authReject.code('otp.challenge_invalid')
  return challenge
}

function assertCode(challenge, code) {
  if (isExpired(challenge)) throw authReject.code('otp.expired')
  if (acceptsCode(challenge, code)) return
  if (!recordWrongAttempt(challenge)) throw authReject.code('otp.invalid')
  consumeChallenge(challenge.id)
  throw authReject.attemptsExceeded()
}

// Mirrors the server: always the same answer, whether or not the address already has an account.
export const registrationEmailHandlers = [
  http.post(
    BASE,
    authRoute(async ({ request }) => {
      const email = emailOf(await readJson(request))
      return envelope(challengePayload(issueRegistrationEmailChallenge(email)))
    }, LATENCY),
  ),

  http.post(
    `${BASE}/resend`,
    authRoute(async ({ request }) => {
      const challenge = challengeOf(await readJson(request))
      const wait = Math.ceil((challenge.resendAvailableAt - Date.now()) / 1000)
      if (wait > 0) throw authReject.cooldown(wait)
      consumeChallenge(challenge.id)
      const next = issueRegistrationEmailChallenge(challenge.account.email)
      return envelope(challengePayload(next))
    }, LATENCY),
  ),

  http.post(
    `${BASE}/verify`,
    authRoute(async ({ request }) => {
      const body = await readJson(request)
      const challenge = challengeOf(body)
      assertCode(challenge, body.code)
      consumeChallenge(challenge.id)
      return envelope({
        emailVerificationToken: issueEmailVerificationToken(challenge.account.email),
      })
    }, LATENCY),
  ),
]
