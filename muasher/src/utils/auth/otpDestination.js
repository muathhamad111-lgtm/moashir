import { AUTH_FLOW_INTENTS } from '@/constants/loginSteps'
import { OTP_CHANNELS, SIGN_IN_IDENTIFIERS } from '@/constants/signIn'
import { identifierForRequest, identifierTypeOf, maskEmail, maskMobile } from './signInIdentifier'

const CODE_KEYS = Object.freeze({
  TYPED_EMAIL: 'auth.otp.code.subtitleTypedEmail',
  TYPED_MOBILE: 'auth.otp.code.subtitleTypedMobile',
  REGISTERED_EMAIL: 'auth.otp.code.subtitleRegisteredEmail',
  GENERIC: 'auth.otp.code.subtitleGeneric',
})

const CHANNEL_ICONS = Object.freeze({
  [OTP_CHANNELS.EMAIL]: 'envelope',
  [OTP_CHANNELS.SMS]: 'send',
})

function codeDestinationOf(channel, identifier) {
  const type = identifierTypeOf(identifier)
  if (channel === OTP_CHANNELS.SMS && type === SIGN_IN_IDENTIFIERS.MOBILE)
    return { subtitleKey: CODE_KEYS.TYPED_MOBILE, target: maskMobile(identifier) }
  if (channel === OTP_CHANNELS.EMAIL && type === SIGN_IN_IDENTIFIERS.EMAIL)
    return {
      subtitleKey: CODE_KEYS.TYPED_EMAIL,
      target: maskEmail(identifierForRequest(identifier)),
    }
  if (channel === OTP_CHANNELS.EMAIL) return { subtitleKey: CODE_KEYS.REGISTERED_EMAIL, target: '' }
  return { subtitleKey: CODE_KEYS.GENERIC, target: '' }
}

function signInDestinationOf(officialEmail) {
  const target = maskEmail(officialEmail)
  const subtitleKey = target ? 'auth.otp.signInSubtitle' : 'auth.otp.signInSubtitleGeneric'
  return { subtitleKey, target }
}

export function otpDestinationOf({ intent, channel, identifier, officialEmail }) {
  if (intent === AUTH_FLOW_INTENTS.REGISTER)
    return { subtitleKey: 'auth.otp.registrationSubtitle', target: officialEmail, inline: true }
  if (intent === AUTH_FLOW_INTENTS.CODE)
    return { ...codeDestinationOf(channel, identifier), inline: false }
  return { ...signInDestinationOf(officialEmail), inline: true }
}

export function channelIconOf(channel) {
  return CHANNEL_ICONS[channel] ?? CHANNEL_ICONS[OTP_CHANNELS.EMAIL]
}
