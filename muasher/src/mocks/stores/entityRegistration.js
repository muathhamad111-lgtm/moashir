import { entityReject } from './entityRejection'
import {
  assertRegistration,
  contactFrom,
  profileFrom,
  registrationRegistry,
} from './entityValidation'
import { applyRegistry } from './entityRegistry'
import { createEntity, isDeleted, markVerified, ownerEmailTaken } from './entitiesStore'
import { currentTerms } from './termsStore'
import { unifiedNumberFrom } from './entityUnifiedNumber'

export const ENTITY_STANDING = {
  SIGN_IN: 'sign_in',
  VERIFY: 'verify',
  DEACTIVATED: 'deactivated',
  PENDING_REVIEW: 'pending_review',
  REJECTED: 'rejected',
  CLOSED: 'closed',
}

export function validateRegistration(body) {
  const terms = currentTerms()
  if (!terms.terms) throw entityReject.termsUnavailable()
  assertRegistration(body, terms)
}

export function registerEntity(body) {
  validateRegistration(body)
  const email = String(body.officialEmail).trim().toLowerCase()
  if (ownerEmailTaken(email)) return null
  return createEntity({
    profile: applyRegistry(profileFrom(body), registrationRegistry(body)),
    contact: contactFrom(body),
    email,
    unifiedNumber: unifiedNumberFrom(body),
    marketingConsent: body.marketingConsent === true,
  })
}

export function standingOf(entity) {
  if (isDeleted(entity)) return ENTITY_STANDING.CLOSED
  if (entity.status === 'deactivated') return ENTITY_STANDING.DEACTIVATED
  if (entity.status === 'pending-review') return ENTITY_STANDING.PENDING_REVIEW
  if (entity.status === 'rejected') return ENTITY_STANDING.REJECTED
  if (entity.status === 'pending-verification') return ENTITY_STANDING.VERIFY
  return ENTITY_STANDING.SIGN_IN
}

export function completeVerification(entity) {
  if (entity.status === 'pending-verification') markVerified(entity)
  return entity
}
