import { apiClient } from '@/api/client'
import { LOGIN_REQUEST_MODES } from '@/constants/loginSteps'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'
import { toLoginOutcome, toMfaEnrollment, toSessionUser } from './mappers/session'
import { withFieldAliases } from './mappers/fieldErrors'

export const UNAUTHENTICATED_STATUS = 401

const AUTH_FLOW_CONFIG = { skipSessionExpiry: true }
const LOGIN_FIELD_ALIASES = { email: 'officialEmail' }

function mapData(envelope, mapper) {
  return { ...envelope, data: mapper(envelope?.data ?? {}) }
}

function postOutcome(action, path, body, context) {
  const request = serviceRequest(action, () => apiClient.post(path, body, AUTH_FLOW_CONFIG), {
    entity: 'session',
    payloadShape: payloadShape(body),
    ...context,
  })
  return withFieldAliases(request, LOGIN_FIELD_ALIASES).then((envelope) =>
    mapData(envelope, toLoginOutcome),
  )
}

export const authService = {
  getLoginModes() {
    return serviceRequest(
      'auth.getLoginModes',
      () => apiClient.get('/auth/login-modes', AUTH_FLOW_CONFIG),
      { entity: 'login-modes' },
    )
  },

  register(payload) {
    return postOutcome('auth.register', '/auth/register', payload, { entity: 'entity' })
  },

  loginWithPassword({ officialEmail, password }) {
    return postOutcome('auth.login', '/auth/login', {
      email: officialEmail,
      mode: LOGIN_REQUEST_MODES.PASSWORD,
      password,
    })
  },

  requestLoginOtp({ officialEmail, channel }) {
    return postOutcome('auth.login', '/auth/login', {
      email: officialEmail,
      mode: LOGIN_REQUEST_MODES.OTP,
      channel,
    })
  },

  verifyOtp({ challengeId, code }) {
    return postOutcome('auth.verifyOtp', '/auth/otp/verify', { challengeId, code })
  },

  resendOtp({ challengeId, channel }) {
    return postOutcome('auth.resendOtp', '/auth/otp/resend', { challengeId, channel })
  },

  challengeMfa({ code, recoveryCode }) {
    const body = recoveryCode ? { recoveryCode } : { code }
    return postOutcome('auth.challengeMfa', '/auth/mfa/challenge', body)
  },

  async startMfaEnrollment() {
    const envelope = await serviceRequest(
      'auth.startMfaEnrollment',
      () => apiClient.post('/auth/mfa/enroll', {}, AUTH_FLOW_CONFIG),
      { entity: 'mfa-enrollment' },
    )
    return mapData(envelope, toMfaEnrollment)
  },

  confirmMfaEnrollment({ code }) {
    return postOutcome('auth.confirmMfaEnrollment', '/auth/mfa/confirm', { code })
  },

  logout() {
    return serviceRequest(
      'auth.logout',
      () => apiClient.post('/auth/logout', {}, AUTH_FLOW_CONFIG),
      {
        entity: 'session',
      },
    )
  },

  async session() {
    const envelope = await serviceRequest(
      'auth.session',
      () => apiClient.get('/auth/session', AUTH_FLOW_CONFIG),
      { entity: 'session' },
      { expectedStatuses: [UNAUTHENTICATED_STATUS] },
    )
    return mapData(envelope, toSessionUser)
  },
}
