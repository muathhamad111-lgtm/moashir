import { delay } from 'msw'
import { MockRejection, messageEntry, rejectionResponse } from '../contract/rejection'
import { authMessages } from '../fixtures/authMessages'

function retryAfter(seconds) {
  return { 'Retry-After': String(Math.max(1, seconds)) }
}

function codeField(code) {
  return { code: [messageEntry(authMessages, code)] }
}

export function authField(code, params = {}) {
  return messageEntry(authMessages, code, params)
}

export const authReject = {
  unauthenticated: () => new MockRejection(401, 'unauthenticated'),
  forbidden: () => new MockRejection(403, 'forbidden'),
  validation: (fields) => new MockRejection(422, 'validation_failed', { fields }),
  credentials: () => new MockRejection(422, 'credentials.invalid'),
  deactivated: () => new MockRejection(403, 'account_deactivated'),
  locked: (seconds) => new MockRejection(423, 'account_locked', { headers: retryAfter(seconds) }),
  code: (code) => new MockRejection(422, code, { fields: codeField(code) }),
  cooldown: (seconds) => new MockRejection(429, 'otp.cooldown', { headers: retryAfter(seconds) }),
  conflict: (code) => new MockRejection(409, code),
  unavailable: (code) => new MockRejection(503, code),
  reasonRequired: () => new MockRejection(422, 'reason_required'),
}

export function authRoute(resolve, latencyMs) {
  return async (info) => {
    await delay(latencyMs)
    try {
      return await resolve(info)
    } catch (error) {
      if (error instanceof MockRejection)
        return rejectionResponse(error, info.request, authMessages)
      throw error
    }
  }
}
