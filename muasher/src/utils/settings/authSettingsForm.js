import { array, object, string } from 'yup'
import { OTP_CHANNELS, OTP_CHANNEL_VALUES, SMS_STATUSES } from '@/constants/signIn'
import { normalizedCodeChannels } from '@/utils/auth/codeChannels'

function sameChannels(first, second) {
  return first.join() === second.join()
}

export function authSettingsSchema() {
  return object({ entitySignInCodeChannels: array(string().oneOf(OTP_CHANNEL_VALUES)) })
}

export function authSettingsValues(settings) {
  return { entitySignInCodeChannels: normalizedCodeChannels(settings.entitySignInCodeChannels) }
}

export function changedAuthSettings(settings, values) {
  const next = normalizedCodeChannels(values.entitySignInCodeChannels)
  const current = normalizedCodeChannels(settings.entitySignInCodeChannels)
  return sameChannels(next, current) ? {} : { entitySignInCodeChannels: next }
}

export function withSmsChannel(channels, allowed) {
  const others = (channels ?? []).filter((channel) => channel !== OTP_CHANNELS.SMS)
  return normalizedCodeChannels(allowed ? [...others, OTP_CHANNELS.SMS] : others)
}

export function smsChannelLocked(settings) {
  return (
    settings.smsStatus === SMS_STATUSES.UNAVAILABLE &&
    !settings.entitySignInCodeChannels.includes(OTP_CHANNELS.SMS)
  )
}
