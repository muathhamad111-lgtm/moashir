import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { readJson } from '../stores/entityRejection'
import { currentSessionUser } from '../stores/sessionStore'
import { findEntity, findEntityByVerifiedPhone, markPhoneVerified } from '../stores/entitiesStore'
import {
  OTP_PURPOSES,
  acceptsCode,
  challengePayload,
  consumeChallenge,
  createChallenge,
  findChallenge,
  isExpired,
} from '../stores/otpChallenges'
import { codeCooldownSecondsOf, recordIssuedCode } from '../stores/signInLockout'
import { smsState } from '../stores/integrations/integrationsState'
import { authReject, authRoute } from './authRoute'

const LATENCY_MS = 320

function ownEntity() {
  const user = currentSessionUser()
  if (!user) throw authReject.unauthenticated()
  const entity = user.entityId ? findEntity(user.entityId) : null
  if (!entity?.owner.phone) throw authReject.conflict('phone.missing')
  return entity
}

function smsAvailable() {
  return Object.values(smsState().rows).some((row) => row?.isActive === true)
}

function issueSmsCode(entity) {
  const account = { kind: 'entity', record: entity }
  const wait = codeCooldownSecondsOf(account)
  if (wait) throw authReject.cooldown(wait)
  recordIssuedCode(account)
  return createChallenge(account, 'sms', OTP_PURPOSES.PHONE)
}

function verifiedChallenge(entity, body) {
  const challenge = findChallenge(body.challengeId, OTP_PURPOSES.PHONE)
  if (challenge?.account?.record.id !== entity.id) throw authReject.code('otp.challenge_invalid')
  if (isExpired(challenge)) throw authReject.code('otp.expired')
  if (!acceptsCode(challenge, body.code)) throw authReject.code('otp.invalid')
  return challenge
}

function assertPhoneFree(entity) {
  const holder = findEntityByVerifiedPhone(entity.owner.phone)
  if (holder && holder.id !== entity.id) throw authReject.conflict('phone.in_use')
}

export const phoneVerificationHandlers = [
  http.post(
    '/api/v1/me/phone/verification',
    authRoute(() => {
      const entity = ownEntity()
      if (entity.owner.phoneVerifiedAt) throw authReject.conflict('phone.already_verified')
      if (!smsAvailable()) throw authReject.unavailable('phone.sms_unavailable')
      return envelope(challengePayload(issueSmsCode(entity)))
    }, LATENCY_MS),
  ),

  http.post(
    '/api/v1/me/phone/verification/confirm',
    authRoute(async ({ request }) => {
      const entity = ownEntity()
      const challenge = verifiedChallenge(entity, await readJson(request))
      assertPhoneFree(entity)
      consumeChallenge(challenge.id)
      markPhoneVerified(entity)
      return envelope({ phoneVerified: true, phoneVerifiedAt: entity.owner.phoneVerifiedAt })
    }, LATENCY_MS),
  ),
]
