import { smsGatewayStatus } from './smsAvailability'

export const CODE_CHANNELS = Object.freeze({ EMAIL: 'email', SMS: 'sms' })

const DEFAULT_CHANNELS = Object.freeze([CODE_CHANNELS.EMAIL])

let channels = [...DEFAULT_CHANNELS]
let generalEmailNotifications = true
let entityRegistrationReview = true
const STAFF_ROLES = Object.freeze([
  'system_admin',
  'criteria_officer',
  'request_follower',
  'assessor',
  'filtering_officer',
  'award_secretary',
  'content_officer',
  'finance_officer',
])
let twoFactorEnabled = true
let twoFactorRoles = [...STAFF_ROLES]

export function resetAuthSettings() {
  channels = [...DEFAULT_CHANNELS]
  generalEmailNotifications = true
  entityRegistrationReview = true
  twoFactorEnabled = true
  twoFactorRoles = [...STAFF_ROLES]
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
    generalEmailNotifications,
    entityRegistrationReview,
    twoFactorEnabled,
    twoFactorRoles: [...twoFactorRoles],
    smsStatus: smsGatewayStatus(),
  }
}

export function setTwoFactorPolicy({ enabled, roles }) {
  if (enabled !== undefined) twoFactorEnabled = enabled
  if (roles !== undefined) twoFactorRoles = [...roles]
}

export function registrationReviewRequired() {
  return entityRegistrationReview
}

export function setEntityRegistrationReview(enabled) {
  entityRegistrationReview = enabled
}

export function setGeneralEmailNotifications(enabled) {
  generalEmailNotifications = enabled
}

export function setSignInCodeChannels(next) {
  const ordered = Object.values(CODE_CHANNELS).filter((channel) => next.includes(channel))
  const changed = ordered.join() !== channels.join()
  channels = ordered
  return changed
}
