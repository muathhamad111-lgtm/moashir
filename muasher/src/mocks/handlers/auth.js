import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse, noContent } from '../contract/responses'
import { requestLocale } from '../contract/locale'
import { MOCK_PASSWORD, OTP_CONFIG, SIGN_IN_IDENTIFIER_KINDS } from '../fixtures/auth'
import { entityNotices } from '../fixtures/entityMessages'
import { sessionPayload } from '../stores/accounts'
import { currentSessionUser, endMockSession } from '../stores/sessionStore'
import {
  OTP_PURPOSES,
  SIGN_IN_PURPOSES,
  acceptsCode,
  challengePayload,
  consumeChallenge,
  createChallenge,
  findChallenge,
  isExpired,
  recordWrongAttempt,
} from '../stores/otpChallenges'
import {
  ENTITY_STANDING,
  completeVerification,
  registerEntity,
  standingOf,
} from '../stores/entityRegistration'
import { entityRoute, readJson } from '../stores/entityRejection'
import { findEntity } from '../stores/entitiesStore'
import { accountOfIdentifier, parseIdentifier } from '../stores/signInIdentifiers'
import {
  codeCooldownSecondsOf,
  lockedSecondsOf,
  recordFailure,
  recordIssuedCode,
} from '../stores/signInLockout'
import {
  availableCodeChannels,
  recordSignInSms,
  resendChannelFor,
} from '../stores/signInCodeChannel'
import { SIGN_IN_STEPS, continueSignIn, isStaff, otpRequired, signIn } from '../stores/signInFlow'
import { failsRandomly } from '../contract/faults'
import { authField, authReject, authRoute } from './authRoute'
import { mfaHandlers } from './mfa'
import { signInCodeHandlers } from './signInCode'
import { authSettingsHandlers } from './authSettings'
import { phoneVerificationHandlers } from './phoneVerification'
import { passwordRecoveryHandlers } from './passwordRecovery'

const LATENCY = { LOGIN: 460, CODE: 360, RESEND: 320 }
const BLOCKED_STAFF_STATUSES = ['deactivated', 'inactive', 'suspended']

const maybeFail = failsRandomly(0.06)

function isStaffBlocked(account) {
  return isStaff(account) && BLOCKED_STAFF_STATUSES.includes(account.record.status)
}

function assertCredentials(body) {
  const fields = {}
  const identifier = parseIdentifier(body.identifier)
  if (!String(body.identifier ?? '').trim()) fields.identifier = [authField('field.required')]
  else if (!identifier) fields.identifier = [authField('field.identifier')]
  if (!String(body.password ?? '')) fields.password = [authField('field.required')]
  if (Object.keys(fields).length) throw authReject.validation(fields)
  return identifier
}

function assertNotLocked(account) {
  const seconds = account ? lockedSecondsOf(account) : 0
  if (seconds) throw authReject.locked(seconds)
}

function rejectFailure(account) {
  const seconds = account ? recordFailure(account) : 0
  if (seconds) throw authReject.locked(seconds)
}

function issueCode(account, channel = 'email', purpose = OTP_PURPOSES.LOGIN) {
  const seconds = codeCooldownSecondsOf(account)
  if (seconds) throw authReject.cooldown(seconds)
  recordIssuedCode(account)
  recordSignInSms(channel)
  return createChallenge(account, channel, purpose)
}

function entitySignIn(account) {
  const standing = standingOf(account.record)
  if (standing === ENTITY_STANDING.CLOSED) throw authReject.credentials()
  if (standing === ENTITY_STANDING.DEACTIVATED) throw authReject.deactivated()
  if (standing === ENTITY_STANDING.VERIFY) return otpRequired(issueCode(account))
  return continueSignIn(account)
}

function passwordSignIn(account) {
  return isStaff(account) ? continueSignIn(account) : entitySignIn(account)
}

function verifiedAccount(challenge) {
  if (challenge.account.kind !== 'entity') return challenge.account
  const entity = findEntity(challenge.account.record.id)
  return { kind: 'entity', record: completeVerification(entity) }
}

function completeSignIn(challenge) {
  if (challenge.purpose === OTP_PURPOSES.SIGN_IN_CODE) return signIn(challenge.account)
  return continueSignIn(verifiedAccount(challenge))
}

function rejectWrongCode(challenge) {
  rejectFailure(challenge.account)
  if (!recordWrongAttempt(challenge)) throw authReject.code('otp.invalid')
  consumeChallenge(challenge.id)
  throw authReject.attemptsExceeded()
}

function assertCodeAccepted(challenge, code) {
  if (!challenge) throw authReject.code('otp.challenge_invalid')
  assertNotLocked(challenge.account)
  if (isExpired(challenge)) throw authReject.code('otp.expired')
  if (!acceptsCode(challenge, code)) rejectWrongCode(challenge)
}

function assertResendable(challenge) {
  if (!challenge) throw authReject.code('otp.challenge_invalid')
  const wait = Math.ceil((challenge.resendAvailableAt - Date.now()) / 1000)
  if (wait > 0) throw authReject.cooldown(wait)
  assertNotLocked(challenge.account)
}

function nextChallenge(previous) {
  const channel = resendChannelFor(previous)
  if (!previous.account) return createChallenge(null, channel, previous.purpose)
  return issueCode(previous.account, channel, previous.purpose)
}

export const authHandlers = [
  http.get('/api/v1/auth/login-modes', async () => {
    await delay(200)
    if (maybeFail(0.04)) return errorResponse(503, 'service_unavailable', 'Service unavailable')
    return envelope({
      identifiers: [...SIGN_IN_IDENTIFIER_KINDS],
      codeChannels: availableCodeChannels(),
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
      return envelope(
        { step: SIGN_IN_STEPS.OTP_REQUIRED, requiresOtp: true, otp, message },
        {},
        { status: 201 },
      )
    }),
  ),

  http.post(
    '/api/v1/auth/login',
    authRoute(async ({ request }) => {
      if (maybeFail()) return errorResponse(500, 'server_error', 'Login failed, please try again')
      const body = await readJson(request)
      const account = accountOfIdentifier(assertCredentials(body))
      if (!account || isStaffBlocked(account)) throw authReject.credentials()
      assertNotLocked(account)
      if (body.password !== MOCK_PASSWORD) {
        rejectFailure(account)
        throw authReject.credentials()
      }
      return passwordSignIn(account)
    }, LATENCY.LOGIN),
  ),

  ...signInCodeHandlers,

  http.post(
    '/api/v1/auth/otp/verify',
    authRoute(async ({ request }) => {
      if (maybeFail(0.05))
        return errorResponse(500, 'server_error', 'Verification failed, please try again')
      const body = await readJson(request)
      const challenge = findChallenge(body.challengeId, SIGN_IN_PURPOSES)
      assertCodeAccepted(challenge, body.code)
      consumeChallenge(challenge.id)
      return completeSignIn(challenge)
    }, LATENCY.CODE),
  ),

  http.post(
    '/api/v1/auth/otp/resend',
    authRoute(async ({ request }) => {
      if (maybeFail(0.05)) return errorResponse(500, 'server_error', 'Could not resend the code')
      const body = await readJson(request)
      const previous = findChallenge(body.challengeId, SIGN_IN_PURPOSES)
      assertResendable(previous)
      const next = nextChallenge(previous)
      consumeChallenge(previous.id)
      return otpRequired(next)
    }, LATENCY.RESEND),
  ),

  ...mfaHandlers,

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

  ...passwordRecoveryHandlers({ continueSignIn }),
  ...authSettingsHandlers,
  ...phoneVerificationHandlers,
]
