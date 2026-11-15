import { array, boolean, object, string } from 'yup'
import { ROLE_KEYS } from '@/constants/roles'
import { OTP_CHANNELS, OTP_CHANNEL_VALUES, SMS_STATUSES } from '@/constants/signIn'
import { normalizedCodeChannels } from '@/utils/auth/codeChannels'

function sameChannels(first, second) {
  return first.join() === second.join()
}

export function authSettingsSchema() {
  return object({
    entitySignInCodeChannels: array(string().oneOf(OTP_CHANNEL_VALUES)),
    generalEmailNotifications: boolean(),
    twoFactorEnabled: boolean(),
    twoFactorRoles: array(string().oneOf(ROLE_KEYS)),
  })
}

export function authSettingsValues(settings) {
  return {
    entitySignInCodeChannels: normalizedCodeChannels(settings.entitySignInCodeChannels),
    generalEmailNotifications: settings.generalEmailNotifications !== false,
    twoFactorEnabled: settings.twoFactorEnabled !== false,
    twoFactorRoles: orderedRoles(settings.twoFactorRoles),
  }
}

function orderedRoles(roles) {
  return ROLE_KEYS.filter((role) => (roles ?? []).includes(role))
}

function changedTwoFactor(settings, values) {
  const changes = {}
  const enabled = Boolean(values.twoFactorEnabled)
  if (enabled !== (settings.twoFactorEnabled !== false)) changes.twoFactorEnabled = enabled
  const roles = orderedRoles(values.twoFactorRoles)
  if (roles.join() !== orderedRoles(settings.twoFactorRoles).join()) changes.twoFactorRoles = roles
  return changes
}

function changedChannels(settings, values) {
  const next = normalizedCodeChannels(values.entitySignInCodeChannels)
  const current = normalizedCodeChannels(settings.entitySignInCodeChannels)
  return sameChannels(next, current) ? {} : { entitySignInCodeChannels: next }
}

function changedGeneralEmail(settings, values) {
  const next = Boolean(values.generalEmailNotifications)
  return next === (settings.generalEmailNotifications !== false)
    ? {}
    : { generalEmailNotifications: next }
}

export function changedAuthSettings(settings, values) {
  return {
    ...changedChannels(settings, values),
    ...changedGeneralEmail(settings, values),
    ...changedTwoFactor(settings, values),
  }
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
