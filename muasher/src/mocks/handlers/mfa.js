import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { DEV_TOTP_CODE, MOCK_MANUAL_ENTRY_KEY, MOCK_RECOVERY_CODES } from '../fixtures/auth'
import { SIGN_IN_STEPS, pendingSignIn, signIn } from '../stores/signInFlow'

const LATENCY_MS = 320

const MOCK_QR_SVG =
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 21 21"><rect width="21" height="21" fill="#ffffff"/><path d="M1 1h7v7H1zM13 1h7v7h-7zM1 13h7v7H1zM10 10h2v2h-2zM14 14h2v2h-2zM17 11h2v2h-2zM11 16h2v3h-2z" fill="#0d3b45"/></svg>'

function invalidCode() {
  const message = 'The verification code is incorrect.'
  return errorResponse(422, 'mfa.invalid', message, { code: [message] })
}

function notPending() {
  return errorResponse(401, 'unauthenticated', 'Unauthenticated.')
}

function pendingAt(step) {
  const pending = pendingSignIn()
  return pending?.step === step ? pending : null
}

export const mfaHandlers = [
  http.post('/api/v1/auth/mfa/challenge', async ({ request }) => {
    await delay(LATENCY_MS)
    const pending = pendingAt(SIGN_IN_STEPS.MFA_CHALLENGE)
    if (!pending) return notPending()
    const body = await request.json()
    const accepted = body.recoveryCode
      ? MOCK_RECOVERY_CODES.includes(String(body.recoveryCode).toUpperCase())
      : String(body.code) === DEV_TOTP_CODE
    if (!accepted) return invalidCode()
    return signIn(pending.account)
  }),

  http.post('/api/v1/auth/mfa/enroll', async () => {
    await delay(LATENCY_MS)
    if (!pendingAt(SIGN_IN_STEPS.MFA_ENROLLMENT)) return notPending()
    return envelope({ qrSvg: MOCK_QR_SVG, manualEntryKey: MOCK_MANUAL_ENTRY_KEY })
  }),

  http.post('/api/v1/auth/mfa/confirm', async ({ request }) => {
    await delay(LATENCY_MS)
    const pending = pendingAt(SIGN_IN_STEPS.MFA_ENROLLMENT)
    if (!pending) return notPending()
    const body = await request.json()
    if (String(body.code) !== DEV_TOTP_CODE) return invalidCode()
    return signIn(pending.account, { recoveryCodes: [...MOCK_RECOVERY_CODES] })
  }),
]
