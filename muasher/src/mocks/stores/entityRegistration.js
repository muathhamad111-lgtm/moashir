import { entityReject } from './entityRejection'
import { assertRegistration, contactFrom, profileFrom } from './entityValidation'
import { createEntity, isDeleted, markVerified, ownerEmailTaken } from './entitiesStore'
import { currentTerms } from './termsStore'
import { unifiedNumberFrom } from './entityUnifiedNumber'

export const ENTITY_STANDING = {
  SIGN_IN: 'sign_in',
  VERIFY: 'verify',
  DEACTIVATED: 'deactivated',
  CLOSED: 'closed',
}

export function registerEntity(body) {
  const terms = currentTerms()
  if (!terms.terms) throw entityReject.termsUnavailable()
  assertRegistration(body, terms)
  const email = String(body.officialEmail).trim().toLowerCase()
  if (ownerEmailTaken(email)) return null
  return createEntity({
    profile: profileFrom(body),
    contact: contactFrom(body),
    email,
    unifiedNumber: unifiedNumberFrom(body),
    marketingConsent: body.marketingConsent === true,
  })
}

export function standingOf(entity) {
  if (isDeleted(entity)) return ENTITY_STANDING.CLOSED
  if (entity.status === 'deactivated') return ENTITY_STANDING.DEACTIVATED
  if (entity.status === 'pending-verification') return ENTITY_STANDING.VERIFY
  return ENTITY_STANDING.SIGN_IN
}

export function completeVerification(entity) {
  if (entity.status === 'pending-verification') markVerified(entity)
  return entity
}
