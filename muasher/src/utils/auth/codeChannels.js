import { OTP_CHANNELS, OTP_CHANNEL_VALUES } from '@/constants/signIn'

export function normalizedCodeChannels(channels) {
  const listed = Array.isArray(channels) ? channels : []
  return OTP_CHANNEL_VALUES.filter(
    (channel) => channel === OTP_CHANNELS.EMAIL || listed.includes(channel),
  )
}
