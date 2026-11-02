import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse, noContent } from '../contract/responses'
import { requestLocale } from '../contract/locale'
import {
  loginModesFixture,
  DEV_TOTP_CODE,
  OTP_CONFIG,
  MOCK_PASSWORD,
  MOCK_RECOVERY_CODES,
  MOCK_MANUAL_ENTRY_KEY,
} from '../fixtures/auth'
import { entityNotices } from '../fixtures/entityMessages'
import { descriptorFor, findAccountByEmail, sessionPayload } from '../stores/accounts'
import { currentSessionUser, endMockSession, startMockSession } from '../stores/sessionStore'
import {
  acceptsCode,
  challengePayload,
  consumeChallenge,
  createChallenge,
  findChallenge,
  isExpired,
} from '../stores/otpChallenges'
import { ENTITY_STANDING, completeVerification, registerEntity, standingOf } from '../stores/entityRegistration'
import { entityRoute, readJson } from '../stores/entityRejection'
import { findEntity } from '../stores/entitiesStore'
import { failsRandomly } from '../contract/faults'

const STEPS = {
  AUTHENTICATED: 'authenticated',
  MFA_CHALLENGE: 'mfa_challenge',
  MFA_ENROLLMENT: 'mfa_enrollment',
  OTP_REQUIRED: 'otp_required',
}

const MOCK_QR_SVG =
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 21 21"><rect width="21" height="21" fill="#ffffff"/><path d="M1 1h7v7H1zM13 1h7v7h-7zM1 13h7v7H1zM10 10h2v2h-2zM14 14h2v2h-2zM17 11h2v2h-2zM11 16h2v3h-2z" fill="#0d3b45"/></svg>'

let pendingSignIn = null

const maybeFail = failsRandomly(0.06)

function invalidCredentials() {
  return errorResponse(422, 'credentials.invalid', 'These credentials do not match our records.', {
    email: ['These credentials do not match our records.'],
  })
}

function accountDeactivated() {
  return errorResponse(403, 'account_deactivated', 'This account is not active.')
}

function invalidCode(message = 'The verification code is incorrect.', code = 'otp.invalid') {
  return errorResponse(422, code, message, { code: [message] })
}

function isStaff(account) {
  return account?.kind === 'staff'
}

function otpRequired(challenge, extra = {}) {
  return envelope({ step: STEPS.OTP_REQUIRED, requiresOtp: true, otp: challengePayload(challenge), ...extra })
}

function signIn(account, extra = {}) {
  const descriptor = descriptorFor(account)
  startMockSession(descriptor)
  pendingSignIn = null
  return envelope({ step: STEPS.AUTHENTICATED, user: sessionPayload(descriptor), ...extra })
}

function continueSignIn(account) {
  if (!isStaff(account)) return signIn(account)
  const step = account.record.mfaEnrolled === false ? STEPS.MFA_ENROLLMENT : STEPS.MFA_CHALLENGE
  pendingSignIn = { account, step }
  return envelope(
    step === STEPS.MFA_CHALLENGE ? { step, mfaRequired: true } : { step, mfaEnrollmentRequired: true },
  )
}

function isStaffBlocked(account) {
  return isStaff(account) && ['deactivated', 'inactive', 'suspended'].includes(account.record.status)
}

function canReceiveCode(account) {
  if (!account || isStaffBlocked(account)) return false
  if (isStaff(account)) return true
  return [ENTITY_STANDING.SIGN_IN, ENTITY_STANDING.VERIFY].includes(standingOf(account.record))
}

function requestOtp(email, channel) {
  const account = findAccountByEmail(email)
  return otpRequired(createChallenge(canReceiveCode(account) ? account : null, channel ?? 'email'))
}

function passwordSignIn(account) {
  if (isStaff(account)) return continueSignIn(account)
  const standing = standingOf(account.record)
  if (standing === ENTITY_STANDING.CLOSED) return invalidCredentials()
  if (standing === ENTITY_STANDING.DEACTIVATED) return accountDeactivated()
  if (standing === ENTITY_STANDING.VERIFY) return otpRequired(createChallenge(account, 'email'))
  return continueSignIn(account)
}

function verifiedAccount(challenge) {
  if (challenge.account.kind !== 'entity') return challenge.account
  const entity = findEntity(challenge.account.record.id)
  return { kind: 'entity', record: completeVerification(entity) }
}

export const authHandlers = [
  http.get('/api/v1/auth/login-modes', async () => {
    await delay(200)
    if (maybeFail(0.04)) return errorResponse(503, 'service_unavailable', 'Service unavailable')
    return envelope({
      ...loginModesFixture,
      otpLength: OTP_CONFIG.length,
      otpExpirySeconds: OTP_CONFIG.expirySeconds,
      resendCooldownSeconds: OTP_CONFIG.resendCooldownSeconds,
      ssoProviders: [],
    })
  }),

  http.post(
    '/api/v1/auth/register',
    entityRoute(async ({ request }) => {
      const created = registerEntity(await readJson(request))
      const account = created ? { kind: 'entity', record: created } : null
      const message = entityNotices.registrationOtpSent[requestLocale(request)]
      const otp = challengePayload(createChallenge(account, 'email'))
      return envelope({ step: STEPS.OTP_REQUIRED, requiresOtp: true, otp, message }, {}, { status: 201 })
    }),
  ),

  http.post('/api/v1/auth/login', async ({ request }) => {
    await delay(460)
    if (maybeFail()) return errorResponse(500, 'server_error', 'Login failed, please try again')
    const body = await request.json()
    if (body.mode === 'otp') return requestOtp(body.email, body.channel)
    const account = findAccountByEmail(body.email)
    if (!account || body.password !== MOCK_PASSWORD || isStaffBlocked(account)) return invalidCredentials()
    return passwordSignIn(account)
  }),

  http.post('/api/v1/auth/otp/request', async ({ request }) => {
    await delay(360)
    if (maybeFail()) return errorResponse(500, 'server_error', 'Could not send the code')
    const body = await request.json()
    return requestOtp(body.email, body.channel)
  }),

  http.post('/api/v1/auth/otp/verify', async ({ request }) => {
    await delay(360)
    if (maybeFail(0.05)) return errorResponse(500, 'server_error', 'Verification failed, please try again')
    const body = await request.json()
    const challenge = findChallenge(body.challengeId)
    if (!challenge) return invalidCode()
    if (isExpired(challenge)) return invalidCode('The verification code has expired.', 'otp.expired')
    if (!acceptsCode(challenge, body.code)) return invalidCode()
    consumeChallenge(challenge.id)
    return continueSignIn(verifiedAccount(challenge))
  }),

  http.post('/api/v1/auth/otp/resend', async ({ request }) => {
    await delay(320)
    if (maybeFail(0.05)) return errorResponse(500, 'server_error', 'Could not resend the code')
    const body = await request.json()
    const previous = findChallenge(body.challengeId)
    if (!previous) return invalidCode('This verification session is invalid.')
    if (Date.now() < previous.resendAvailableAt) {
      return errorResponse(429, 'rate_limited', 'Please wait before requesting a new code.')
    }
    consumeChallenge(previous.id)
    return otpRequired(createChallenge(previous.account, body.channel ?? previous.channel))
  }),

  http.post('/api/v1/auth/mfa/challenge', async ({ request }) => {
    await delay(320)
    if (pendingSignIn?.step !== STEPS.MFA_CHALLENGE) {
      return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    }
    const body = await request.json()
    const accepted = body.recoveryCode
      ? MOCK_RECOVERY_CODES.includes(String(body.recoveryCode).toUpperCase())
      : String(body.code) === DEV_TOTP_CODE
    if (!accepted) return invalidCode('The verification code is incorrect.', 'mfa.invalid')
    return signIn(pendingSignIn.account)
  }),

  http.post('/api/v1/auth/mfa/enroll', async () => {
    await delay(320)
    if (pendingSignIn?.step !== STEPS.MFA_ENROLLMENT) {
      return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    }
    return envelope({ qrSvg: MOCK_QR_SVG, manualEntryKey: MOCK_MANUAL_ENTRY_KEY })
  }),

  http.post('/api/v1/auth/mfa/confirm', async ({ request }) => {
    await delay(320)
    if (pendingSignIn?.step !== STEPS.MFA_ENROLLMENT) {
      return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    }
    const body = await request.json()
    if (String(body.code) !== DEV_TOTP_CODE) return invalidCode('The verification code is incorrect.', 'mfa.invalid')
    return signIn(pendingSignIn.account, { recoveryCodes: [...MOCK_RECOVERY_CODES] })
  }),

  http.post('/api/v1/auth/logout', async () => {
    await delay(200)
    endMockSession()
    return noContent()
  }),

  http.get('/api/v1/auth/session', async () => {
    await delay(120)
    const user = currentSessionUser()
    if (!user) return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
    return envelope(sessionPayload(user))
  }),
]
