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
  return (
    entity.governanceGrade !== null &&
    entity.employeesCount !== null &&
    entity.annualBeneficiariesCount !== null
  )
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

export function ownerEmailTaken(email) {
  return entities.some((entity) => sameEmail(entity.owner.email, email))
}

export function createEntity({ profile, contact, email, marketingConsent }) {
  const now = new Date().toISOString()
  const id = nextEntityId++
  const entity = {
    ...LIFECYCLE_DEFAULTS,
    ...profile,
    id,
    status: 'pending-verification',
    verifiedAt: null,
    createdAt: now,
    marketingConsentAt: marketingConsent ? now : null,
    owner: { id, name: contact.authorizedPersonName, email, phone: contact.phone },
    openRequests: [],
  }
  entities.push(entity)
  return entity
}

export function applyProfile(entity, profile, contact) {
  Object.assign(entity, profile)
  entity.owner.name = contact.authorizedPersonName
  entity.owner.phone = contact.phone
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
  }
  entity.marketingConsentAt = null
  entity.deletionReason = null
  entity.deactivationReason = null
  entity.openRequests = []
  entity.anonymizedAt = new Date().toISOString()
  return pseudonym
}
