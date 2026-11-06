import { smsGatewayStatus } from './smsAvailability'

export const CODE_CHANNELS = Object.freeze({ EMAIL: 'email', SMS: 'sms' })

const DEFAULT_CHANNELS = Object.freeze([CODE_CHANNELS.EMAIL])

let channels = [...DEFAULT_CHANNELS]

export function resetAuthSettings() {
  channels = [...DEFAULT_CHANNELS]
}

export function signInCodeChannelsAllowed() {
  return [...channels]
}

export function smsAllowedForSignInCodes() {
  return channels.includes(CODE_CHANNELS.SMS)
}

export function authSettingsResource() {
  return {
    entitySignInCodeChannels: signInCodeChannelsAllowed(),
    smsStatus: smsGatewayStatus(),
    staffTotpRequired: true,
  }
}

export function setSignInCodeChannels(next) {
  const ordered = Object.values(CODE_CHANNELS).filter((channel) => next.includes(channel))
  const changed = ordered.join() !== channels.join()
  channels = ordered
  return changed
}
