import { SIGN_IN_SMS_CAP } from '../fixtures/auth'
import { CODE_CHANNELS, smsAllowedForSignInCodes } from './authSettingsStore'
import { smsGatewayAvailable } from './smsAvailability'

const MS_PER_SECOND = 1000
const MOBILE_IDENTIFIER = 'mobile'

let smsSentAt = []

export function resetSignInSmsCap() {
  smsSentAt = []
}

function smsCapReached() {
  const windowStart = Date.now() - SIGN_IN_SMS_CAP.windowSeconds * MS_PER_SECOND
  smsSentAt = smsSentAt.filter((at) => at > windowStart)
  return smsSentAt.length >= SIGN_IN_SMS_CAP.perHour
}

function smsUsable() {
  return smsAllowedForSignInCodes() && smsGatewayAvailable()
}

function smsSendable() {
  return smsUsable() && !smsCapReached()
}

export function availableCodeChannels() {
  return smsUsable() ? [CODE_CHANNELS.EMAIL, CODE_CHANNELS.SMS] : [CODE_CHANNELS.EMAIL]
}

export function codeChannelFor(identifier) {
  return identifier.type === MOBILE_IDENTIFIER && smsSendable()
    ? CODE_CHANNELS.SMS
    : CODE_CHANNELS.EMAIL
}

function stillVerifiedFor(challenge) {
  return !challenge.account || Boolean(challenge.account.record.owner?.phoneVerifiedAt)
}

export function resendChannelFor(challenge) {
  return challenge.channel === CODE_CHANNELS.SMS && smsSendable() && stillVerifiedFor(challenge)
    ? CODE_CHANNELS.SMS
    : CODE_CHANNELS.EMAIL
}

export function recordSignInSms(channel) {
  if (channel === CODE_CHANNELS.SMS) smsSentAt.push(Date.now())
}
