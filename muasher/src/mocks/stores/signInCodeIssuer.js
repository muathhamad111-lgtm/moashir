import { CODE_CHANNELS } from './authSettingsStore'
import { ENTITY_STANDING, standingOf } from './entityRegistration'
import { OTP_PURPOSES, createChallenge } from './otpChallenges'
import { recordSignInSms } from './signInCodeChannel'
import { isStaff } from './signInFlow'
import { codeCooldownSecondsOf, lockedSecondsOf, recordIssuedCode } from './signInLockout'

const PURPOSE_BY_STANDING = Object.freeze({
  [ENTITY_STANDING.VERIFY]: OTP_PURPOSES.LOGIN,
  [ENTITY_STANDING.SIGN_IN]: OTP_PURPOSES.SIGN_IN_CODE,
})

function purposeFor(account) {
  if (!account || isStaff(account)) return null
  if (lockedSecondsOf(account) || codeCooldownSecondsOf(account)) return null
  return PURPOSE_BY_STANDING[standingOf(account.record)] ?? null
}

export function signInCodeChallenge(account, channel) {
  const purpose = purposeFor(account)
  if (!purpose) return createChallenge(null, channel, OTP_PURPOSES.SIGN_IN_CODE)
  const sentBy = purpose === OTP_PURPOSES.LOGIN ? CODE_CHANNELS.EMAIL : channel
  recordIssuedCode(account)
  recordSignInSms(sentBy)
  return createChallenge(account, sentBy, purpose)
}
