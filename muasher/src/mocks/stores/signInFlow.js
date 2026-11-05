import { envelope } from '../contract/responses'
import { descriptorFor, sessionPayload } from './accounts'
import { startMockSession } from './sessionStore'
import { clearFailures } from './signInLockout'

export const SIGN_IN_STEPS = Object.freeze({
  AUTHENTICATED: 'authenticated',
  MFA_CHALLENGE: 'mfa_challenge',
  MFA_ENROLLMENT: 'mfa_enrollment',
  OTP_REQUIRED: 'otp_required',
})

let pending = null

export function pendingSignIn() {
  return pending
}

export function isStaff(account) {
  return account?.kind === 'staff'
}

export function signIn(account, extra = {}) {
  const descriptor = descriptorFor(account)
  startMockSession(descriptor)
  clearFailures(account)
  pending = null
  return envelope({ step: SIGN_IN_STEPS.AUTHENTICATED, user: sessionPayload(descriptor), ...extra })
}

export function continueSignIn(account) {
  if (!isStaff(account)) return signIn(account)
  const step =
    account.record.mfaEnrolled === false
      ? SIGN_IN_STEPS.MFA_ENROLLMENT
      : SIGN_IN_STEPS.MFA_CHALLENGE
  pending = { account, step }
  return envelope(
    step === SIGN_IN_STEPS.MFA_CHALLENGE
      ? { step, mfaRequired: true }
      : { step, mfaEnrollmentRequired: true },
  )
}
