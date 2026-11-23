import { apiClient } from '@/api/client'
import { CODE_REQUEST_REJECTIONS, SIGN_IN_REJECTIONS } from '@/constants/signIn'
import { payloadShape } from '@/utils/payloadShape'
import { serviceRequest } from './serviceRequest'
import { toLoginOutcome, toMfaEnrollment, toSessionUser } from './mappers/session'

export const UNAUTHENTICATED_STATUS = 401

const AUTH_FLOW_CONFIG = { skipSessionExpiry: true }
const AUTH_REJECTIONS = [422, 429]
const INVITATION_REJECTIONS = [404, 410, 422, 429]
const INVITATION_CHECK_REJECTIONS = [404, 410, 429]

function mapData(envelope, mapper) {
  return { ...envelope, data: mapper(envelope?.data ?? {}) }
}

function postOutcome(action, path, body, { expectedStatuses = [], ...context } = {}) {
  return serviceRequest(
    action,
    () => apiClient.post(path, body, AUTH_FLOW_CONFIG),
    { entity: 'session', payloadShape: payloadShape(body), ...context },
    { expectedStatuses },
  ).then((envelope) => mapData(envelope, toLoginOutcome))
}

function postCode(action, path, body) {
  return serviceRequest(
    action,
    () => apiClient.post(path, body, AUTH_FLOW_CONFIG),
    { entity: 'registration-email', payloadShape: payloadShape(body) },
    { expectedStatuses: SIGN_IN_REJECTIONS },
  )
}

export const authService = {
  // The official e-mail is proven inside the registration form, before the registration is sent.
  requestRegistrationEmailCode({ officialEmail }) {
    return postCode('auth.registrationEmail.request', '/auth/register/email-code', {
      officialEmail,
    })
  },

  resendRegistrationEmailCode({ challengeId }) {
    return postCode('auth.registrationEmail.resend', '/auth/register/email-code/resend', {
      challengeId,
    })
  },

  verifyRegistrationEmailCode({ challengeId, code }) {
    return postCode('auth.registrationEmail.verify', '/auth/register/email-code/verify', {
      challengeId,
      code,
    })
  },

  register(payload) {
    return postOutcome('auth.register', '/auth/register', payload, {
      entity: 'entity',
      expectedStatuses: AUTH_REJECTIONS,
    })
  },

  signIn({ identifier, password }) {
    return postOutcome(
      'auth.login',
      '/auth/login',
      { identifier, password },
      { expectedStatuses: SIGN_IN_REJECTIONS },
    )
  },

  requestSignInCode({ identifier }) {
    return postOutcome(
      'auth.requestSignInCode',
      '/auth/otp/request',
      { identifier },
      { expectedStatuses: CODE_REQUEST_REJECTIONS },
    )
  },

  verifyOtp({ challengeId, code }) {
    return postOutcome(
      'auth.verifyOtp',
      '/auth/otp/verify',
      { challengeId, code },
      { expectedStatuses: SIGN_IN_REJECTIONS },
    )
  },

  resendOtp({ challengeId }) {
    return postOutcome(
      'auth.resendOtp',
      '/auth/otp/resend',
      { challengeId },
      { expectedStatuses: SIGN_IN_REJECTIONS },
    )
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

  requestPasswordReset({ email }) {
    const body = { email }
    return serviceRequest(
      'auth.requestPasswordReset',
      () => apiClient.post('/auth/password/forgot', body, AUTH_FLOW_CONFIG),
      { entity: 'password-reset', payloadShape: payloadShape(body) },
      { expectedStatuses: AUTH_REJECTIONS },
    )
  },

  // Answers whether a reset link still works; checking it never uses it up.
  checkPasswordResetLink(token) {
    const body = { token }
    return serviceRequest(
      'auth.checkPasswordResetLink',
      () => apiClient.post('/auth/password/reset/check', body, AUTH_FLOW_CONFIG),
      { entity: 'password-reset' },
      { expectedStatuses: AUTH_REJECTIONS },
    )
  },

  // The API finds the account from the link token alone, so no e-mail is sent.
  resetPassword({ token, password, passwordConfirmation }) {
    const body = { token, password, passwordConfirmation }
    return serviceRequest(
      'auth.resetPassword',
      () => apiClient.post('/auth/password/reset', body, AUTH_FLOW_CONFIG),
      { entity: 'password-reset', payloadShape: payloadShape(body) },
      { expectedStatuses: AUTH_REJECTIONS },
    )
  },

  // Changes the signed-in user's own password; the API keeps this session and ends the others.
  changePassword({ currentPassword, password, passwordConfirmation }) {
    const body = { currentPassword, password, passwordConfirmation }
    return serviceRequest(
      'auth.changePassword',
      () => apiClient.post('/auth/password/change', body),
      { entity: 'password-change', payloadShape: payloadShape(body) },
      { expectedStatuses: AUTH_REJECTIONS },
    )
  },

  checkInvitation(token) {
    return serviceRequest(
      'auth.checkInvitation',
      () => apiClient.get(`/auth/invitations/${encodeURIComponent(token)}`, AUTH_FLOW_CONFIG),
      { entity: 'invitation' },
      { expectedStatuses: INVITATION_CHECK_REJECTIONS },
    )
  },

  acceptInvitation(
    token,
    { password, passwordConfirmation, termsVersionId, nationalId, ndaVersionId },
  ) {
    const body = {
      password,
      passwordConfirmation,
      termsVersionId,
      ...(nationalId ? { nationalId } : {}),
      ...(ndaVersionId ? { ndaVersionId } : {}),
    }
    return postOutcome(
      'auth.acceptInvitation',
      `/auth/invitations/${encodeURIComponent(token)}/accept`,
      body,
      { entity: 'invitation', expectedStatuses: INVITATION_REJECTIONS },
    )
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
