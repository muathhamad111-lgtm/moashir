import { smsGatewayStatus } from './smsAvailability'
import { activeScenario } from './committee/committeeScenario'

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
const FILE_ACCESS_ROLES = Object.freeze([
  'system_admin',
  'request_follower',
  'assessor',
  'filtering_officer',
  'award_secretary',
])
// The scenario also requires entity accounts, so the entity flow of the completion modal stays covered.
const SCENARIO_NATIONAL_ID_ROLES = Object.freeze([...FILE_ACCESS_ROLES, 'entity'])
export const NATIONAL_ID_REQUIRED_SCENARIO = 'national-id-required'

function scenarioRequiresNationalId() {
  return activeScenario() === NATIONAL_ID_REQUIRED_SCENARIO
}

let nationalIdEnabled = scenarioRequiresNationalId()
let nationalIdRoles = scenarioRequiresNationalId() ? [...SCENARIO_NATIONAL_ID_ROLES] : []

export function resetAuthSettings() {
  channels = [...DEFAULT_CHANNELS]
  generalEmailNotifications = true
  entityRegistrationReview = true
  twoFactorEnabled = true
  twoFactorRoles = [...STAFF_ROLES]
  nationalIdEnabled = scenarioRequiresNationalId()
  nationalIdRoles = scenarioRequiresNationalId() ? [...SCENARIO_NATIONAL_ID_ROLES] : []
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
    nationalIdEnabled,
    nationalIdRoles: [...nationalIdRoles],
    fileAccessRoles: [...FILE_ACCESS_ROLES],
  }
}

export function setNationalIdPolicy({ enabled, roles }) {
  if (enabled !== undefined) nationalIdEnabled = enabled
  if (roles !== undefined) nationalIdRoles = [...roles]
}

export function nationalIdRequiredFor(roles, { superAdmin = false } = {}) {
  if (!nationalIdEnabled) return false
  return superAdmin || roles.some((role) => nationalIdRoles.includes(role))
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
