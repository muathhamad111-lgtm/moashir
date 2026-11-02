import { delay } from 'msw'
import { MockRejection, messageEntry, rejectionResponse } from '../contract/rejection'
import { entityMessages } from '../fixtures/entityMessages'
import { currentSessionUser } from './sessionStore'
import { sessionCan } from './sessionPermissions'

export const ENTITY_LATENCY_MS = 160

export function fieldError(code, params = {}) {
  return messageEntry(entityMessages, code, params)
}

export const entityReject = {
  unauthenticated: () => new MockRejection(401, 'unauthenticated'),
  forbidden: () => new MockRejection(403, 'forbidden'),
  notFound: () => new MockRejection(404, 'not_found'),
  validation: (fields) => new MockRejection(422, 'validation_failed', { fields }),
  illegalTransition: () => new MockRejection(409, 'state.transition_illegal'),
  deleted: () => new MockRejection(409, 'entity.deleted'),
  notSoftDeleted: () => new MockRejection(409, 'entity.not_soft_deleted'),
  openRequestsConfirmationRequired: (openRequests) =>
    new MockRejection(409, 'entity.open_requests_confirmation_required', {
      meta: { openRequests, openRequestsCount: openRequests.length },
    }),
  verificationCooldown: (seconds) =>
    new MockRejection(429, 'entity.verification_cooldown', {
      headers: { 'Retry-After': String(Math.max(1, seconds)) },
    }),
  confirmationMismatch: () =>
    new MockRejection(422, 'confirmation.mismatch', {
      fields: { confirmation: [fieldError('confirmation.mismatch')] },
    }),
  disclaimerRequired: () =>
    new MockRejection(422, 'disclaimer.required', {
      fields: { disclaimerAcknowledged: [fieldError('disclaimer.required')] },
    }),
  emailPendingApproval: () => new MockRejection(409, 'email_change.pending_approval'),
  emailAddressTaken: () => new MockRejection(409, 'email_change.address_taken'),
  termsUnavailable: () => new MockRejection(503, 'registration.terms_unavailable'),
  accountDeactivated: () => new MockRejection(403, 'account_deactivated'),
  otpInvalid: () =>
    new MockRejection(422, 'otp.invalid', { fields: { code: [fieldError('otp.invalid')] } }),
}

export function signedInUser() {
  const user = currentSessionUser()
  if (!user) throw entityReject.unauthenticated()
  return user
}

export function actorWith(permission) {
  const user = signedInUser()
  if (!sessionCan(permission)) throw entityReject.forbidden()
  return user
}

export async function readJson(request) {
  const text = await request.text()
  return text ? JSON.parse(text) : {}
}

export function entityRoute(resolve) {
  return async (info) => {
    await delay(ENTITY_LATENCY_MS)
    try {
      return await resolve(info)
    } catch (error) {
      if (error instanceof MockRejection) return rejectionResponse(error, info.request, entityMessages)
      throw error
    }
  }
}
