import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { readJson } from '../stores/entityRejection'
import { currentSessionUser } from '../stores/sessionStore'
import { sessionCan, sessionIsSuperAdmin } from '../stores/sessionPermissions'
import {
  CODE_CHANNELS,
  authSettingsResource,
  setEntityRegistrationReview,
  setGeneralEmailNotifications,
  setNationalIdPolicy,
  setTwoFactorPolicy,
  setSignInCodeChannels,
} from '../stores/authSettingsStore'
import { SMS_GATEWAY_STATUS, smsGatewayStatus } from '../stores/smsAvailability'
import { authField, authReject, authRoute } from './authRoute'

const PERMISSION = 'settings.auth.manage'
const CHANNELS_FIELD = 'entitySignInCodeChannels'
const GENERAL_EMAIL_FIELD = 'generalEmailNotifications'
const REGISTRATION_REVIEW_FIELD = 'entityRegistrationReview'
const TWO_FACTOR_FIELD = 'twoFactorEnabled'
const TWO_FACTOR_ROLES_FIELD = 'twoFactorRoles'
const NATIONAL_ID_FIELD = 'nationalIdEnabled'
const NATIONAL_ID_ROLES_FIELD = 'nationalIdRoles'
const ROLE_VALUES = [
  'system_admin',
  'criteria_officer',
  'request_follower',
  'assessor',
  'filtering_officer',
  'award_secretary',
  'content_officer',
  'finance_officer',
  'entity',
]
const KNOWN_CHANNELS = Object.values(CODE_CHANNELS)
const REASON_LENGTH = { MIN: 10, MAX: 1000 }
const LATENCY = { READ: 220, WRITE: 360 }

function assertManager() {
  if (!currentSessionUser()) throw authReject.unauthenticated()
  if (!sessionCan(PERMISSION)) throw authReject.forbidden()
}

function reasonOf(body) {
  const reason = String(body.reason ?? '').trim()
  if (reason && (reason.length < REASON_LENGTH.MIN || reason.length > REASON_LENGTH.MAX))
    throw authReject.validation({
      reason: [authField('field.reason_min', { min: REASON_LENGTH.MIN })],
    })
  return reason
}

const MAX_CHANNELS = KNOWN_CHANNELS.length

function fieldError(path, code) {
  return authReject.validation({ [path]: [authField(code)] })
}

function assertChannelList(channels) {
  if (Array.isArray(channels) && !channels.length)
    throw fieldError(CHANNELS_FIELD, 'field.required')
  if (!Array.isArray(channels) || channels.length > MAX_CHANNELS)
    throw fieldError(CHANNELS_FIELD, 'field.invalid')
  const invalid = channels.findIndex(
    (channel, index) => !KNOWN_CHANNELS.includes(channel) || channels.indexOf(channel) !== index,
  )
  if (invalid !== -1) throw fieldError(`${CHANNELS_FIELD}.${invalid}`, 'field.invalid')
}

function assertChannelRules(channels) {
  if (!channels.includes(CODE_CHANNELS.EMAIL))
    throw fieldError(CHANNELS_FIELD, 'channels.email_required')
  const smsUnavailable = smsGatewayStatus() === SMS_GATEWAY_STATUS.UNAVAILABLE
  if (channels.includes(CODE_CHANNELS.SMS) && smsUnavailable)
    throw fieldError(CHANNELS_FIELD, 'channels.sms_unavailable')
}

function channelsOf(body) {
  if (!(CHANNELS_FIELD in body)) return undefined
  assertChannelList(body[CHANNELS_FIELD])
  assertChannelRules(body[CHANNELS_FIELD])
  return body[CHANNELS_FIELD]
}

function booleanOf(body, field) {
  if (!(field in body)) return undefined
  if (typeof body[field] !== 'boolean') throw fieldError(field, 'field.invalid')
  return body[field]
}

function rolePolicyOf(body, enabledField, rolesField) {
  const policy = {}
  if (enabledField in body) {
    if (typeof body[enabledField] !== 'boolean') throw fieldError(enabledField, 'field.invalid')
    policy.enabled = body[enabledField]
  }
  if (rolesField in body) {
    const roles = body[rolesField]
    if (!Array.isArray(roles)) throw fieldError(rolesField, 'field.invalid')
    const invalid = roles.findIndex(
      (role, index) => !ROLE_VALUES.includes(role) || roles.indexOf(role) !== index,
    )
    if (invalid !== -1) throw fieldError(`${rolesField}.${invalid}`, 'field.invalid')
    policy.roles = roles
  }
  return policy
}

export const authSettingsHandlers = [
  http.get(
    '/api/v1/settings/auth',
    authRoute(() => {
      assertManager()
      return envelope(authSettingsResource())
    }, LATENCY.READ),
  ),

  http.put(
    '/api/v1/settings/auth',
    authRoute(async ({ request }) => {
      assertManager()
      const body = await readJson(request)
      const channels = channelsOf(body)
      const generalEmail = booleanOf(body, GENERAL_EMAIL_FIELD)
      const registrationReview = booleanOf(body, REGISTRATION_REVIEW_FIELD)
      const twoFactor = rolePolicyOf(body, TWO_FACTOR_FIELD, TWO_FACTOR_ROLES_FIELD)
      const nationalId = rolePolicyOf(body, NATIONAL_ID_FIELD, NATIONAL_ID_ROLES_FIELD)
      const reason = reasonOf(body)
      if (sessionIsSuperAdmin() && !reason) throw authReject.reasonRequired()
      if (channels !== undefined) setSignInCodeChannels(channels)
      if (generalEmail !== undefined) setGeneralEmailNotifications(generalEmail)
      if (registrationReview !== undefined) setEntityRegistrationReview(registrationReview)
      setTwoFactorPolicy(twoFactor)
      setNationalIdPolicy(nationalId)
      return envelope(authSettingsResource())
    }, LATENCY.WRITE),
  ),
]
