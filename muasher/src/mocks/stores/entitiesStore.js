import { ENTITY_REFERENCE_PREFIX, entitiesFixture } from '../fixtures/entities'

const ANONYMIZED_DOMAIN = 'erased.invalid'

const LIFECYCLE_DEFAULTS = {
  deactivatedAt: null,
  deactivationReason: null,
  verificationRemindedAt: null,
  deletedAt: null,
  deletionReason: null,
  restoredAt: null,
  anonymizedAt: null,
  incomingOpenRequest: null,
}

let entities = []
let nextEntityId = 1

function cloneRecord(record) {
  return {
    ...record,
    name: { ...record.name },
    owner: { ...record.owner },
    openRequests: record.openRequests.map((request) => ({ ...request })),
    incomingOpenRequest: record.incomingOpenRequest ? { ...record.incomingOpenRequest } : null,
    anonymizedAt: null,
  }
}

export function resetEntitiesStore() {
  entities = entitiesFixture.map(cloneRecord)
  nextEntityId = Math.max(...entities.map((entity) => entity.id)) + 1
}

resetEntitiesStore()

export function referenceOf(entity) {
  return `${ENTITY_REFERENCE_PREFIX}${String(entity.id).padStart(6, '0')}`
}

export function isDeleted(entity) {
  return entity.deletedAt !== null
}

export function isErased(entity) {
  return entity.anonymizedAt !== null
}

export function hasCompleteProfile(entity) {
  return [
    entity.unifiedNumber,
    entity.governanceGrade,
    entity.employeesCount,
    entity.annualBeneficiariesCount,
    entity.licenseNumber,
    entity.foundedOn,
  ].every((value) => value !== null && value !== undefined)
}

export function allEntities() {
  return entities.filter((entity) => !isErased(entity))
}

export function findEntity(id) {
  return allEntities().find((entity) => entity.id === Number(id)) ?? null
}

function sameEmail(left, right) {
  return (
    String(left ?? '')
      .trim()
      .toLowerCase() ===
    String(right ?? '')
      .trim()
      .toLowerCase()
  )
}

export function findEntityByOwnerEmail(email) {
  return allEntities().find((entity) => sameEmail(entity.owner.email, email)) ?? null
}

export function findEntityByUnifiedNumber(unifiedNumber) {
  return allEntities().find((entity) => entity.unifiedNumber === unifiedNumber) ?? null
}

export function findEntityByVerifiedPhone(phone) {
  return (
    allEntities().find(
      (entity) => entity.owner.phone === phone && entity.owner.phoneVerifiedAt !== null,
    ) ?? null
  )
}

export function unifiedNumberTaken(unifiedNumber, exceptId = null) {
  return entities.some((entity) => entity.id !== exceptId && entity.unifiedNumber === unifiedNumber)
}

export function ownerEmailTaken(email) {
  return entities.some((entity) => sameEmail(entity.owner.email, email))
}

export function createEntity({ profile, contact, email, unifiedNumber, marketingConsent }) {
  const now = new Date().toISOString()
  const id = nextEntityId++
  const entity = {
    ...LIFECYCLE_DEFAULTS,
    ...profile,
    id,
    unifiedNumber,
    status: 'pending-verification',
    verifiedAt: null,
    createdAt: now,
    marketingConsentAt: marketingConsent ? now : null,
    owner: {
      id,
      name: contact.authorizedPersonName,
      email,
      phone: contact.phone,
      phoneVerifiedAt: null,
    },
    openRequests: [],
  }
  entities.push(entity)
  return entity
}

export function applyProfile(entity, profile, contact) {
  Object.assign(entity, profile)
  entity.owner.name = contact.authorizedPersonName
  if (entity.owner.phone !== contact.phone) entity.owner.phoneVerifiedAt = null
  entity.owner.phone = contact.phone
  return entity
}

export function markPhoneVerified(entity) {
  entity.owner.phoneVerifiedAt = new Date().toISOString()
  return entity
}

export function setMarketingConsent(entity, consented) {
  if (consented && !entity.marketingConsentAt) entity.marketingConsentAt = new Date().toISOString()
  if (!consented) entity.marketingConsentAt = null
}

export function setOwnerEmail(entity, email) {
  entity.owner.email = email
}

export function markVerified(entity) {
  entity.status = 'active'
  entity.verifiedAt = new Date().toISOString()
}

export function anonymize(entity) {
  const pseudonym = `ERASED-${String(entity.id).padStart(6, '0')}`
  entity.name = { ar: pseudonym, en: pseudonym }
  entity.owner = {
    id: entity.owner.id,
    name: pseudonym,
    email: `${pseudonym.toLowerCase()}@${ANONYMIZED_DOMAIN}`,
    phone: null,
    phoneVerifiedAt: null,
  }
  entity.unifiedNumber = null
  entity.marketingConsentAt = null
  entity.deletionReason = null
  entity.deactivationReason = null
  entity.openRequests = []
  entity.anonymizedAt = new Date().toISOString()
  return pseudonym
}
