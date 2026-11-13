import { findCity, findRegion } from './regionsStore'
import { findEntity, hasCompleteProfile, isDeleted, referenceOf } from './entitiesStore'
import { profileCompletionOf, profileSectionsOf } from './entityProfileSections'
import { findFile } from './finance/financeState'
import { signedDownloadOf } from './finance/signedFiles'
import { entityLevelFields, hasOpenAccreditedRequest } from './entityLevels'

export const ENTITY_ACCESS = { FULL: 'full', CONTACT: 'contact', NONE: 'none' }

export function maskEmail(email) {
  const [local, domain] = String(email).split('@')
  return `${local.slice(0, 1)}***@${domain}`
}

function place(record) {
  return record ? { id: record.id, code: record.code, name: { ...record.name } } : null
}

export function entitySummary(entity) {
  return {
    id: entity.id,
    reference: referenceOf(entity),
    name: { ar: entity.name.ar, en: entity.name.en ?? null },
    status: entity.status,
  }
}

export function entityProfileOf(entity) {
  return {
    entityType: entity.entityType,
    developmentSector: entity.developmentSector,
    orgSize: entity.orgSize,
    foundingYear: entity.foundingYear,
    region: place(findRegion(entity.regionId)),
    city: place(findCity(entity.cityId)),
    governanceGrade: entity.governanceGrade,
    employeesCount: entity.employeesCount,
    annualBeneficiariesCount: entity.annualBeneficiariesCount,
    hasStrategicPlan: entity.hasStrategicPlan,
    unifiedNumber: entity.unifiedNumber ?? null,
    licenseNumber: entity.licenseNumber ?? null,
    foundedOn: entity.foundedOn ?? null,
    websiteUrl: entity.websiteUrl ?? null,
    profileCompleted: hasCompleteProfile(entity),
    verifiedAt: entity.verifiedAt,
    createdAt: entity.createdAt,
  }
}

function entityContact(entity) {
  return {
    authorizedPersonName: entity.owner.name,
    officialEmail: entity.owner.email,
    phone: entity.owner.phone,
    phoneVerified: Boolean(entity.owner.phoneVerifiedAt),
  }
}

function pendingChange(change) {
  return change
    ? {
        status: change.status,
        newEmail: maskEmail(change.newEmail),
        policy: change.policy,
        requestedAt: change.requestedAt,
      }
    : null
}

export function ownEntityResource(entity, { awaitingChange = null, canEdit = false } = {}) {
  return {
    ...entitySummary(entity),
    ...entityProfileOf(entity),
    ...entityContact(entity),
    marketingConsent: entity.marketingConsentAt !== null,
    marketingConsentAt: entity.marketingConsentAt,
    pendingEmailChange: pendingChange(awaitingChange),
    logo: entity.logo ? { ...entity.logo } : null,
    canEdit,
    profileSections: profileSectionsOf(entity),
    profileCompletion: profileCompletionOf(entity),
  }
}

function staffLogoOf(entity, viewer) {
  const file = entity.logo ? findFile(entity.logo.id) : null
  if (!file || !viewer) return null
  return {
    id: file.id,
    url: signedDownloadOf(viewer, file).url,
    uploadedAt: entity.logo.uploadedAt,
  }
}

function staffEntityResource(entity, viewer) {
  return {
    ...entitySummary(entity),
    ...entityLevelFields(entity),
    hasOpenAccreditedRequest: hasOpenAccreditedRequest(entity.id),
    ...entityProfileOf(entity),
    ...entityContact(entity),
    logo: staffLogoOf(entity, viewer),
    marketingConsent: entity.marketingConsentAt !== null,
    deactivatedAt: entity.deactivatedAt,
    deactivationReason: entity.deactivationReason,
    verificationRemindedAt: entity.verificationRemindedAt,
    isDeleted: isDeleted(entity),
    isFrozen: isDeleted(entity),
    deletedAt: entity.deletedAt,
    deletionReason: entity.deletionReason,
    restoredAt: entity.restoredAt,
  }
}

const VARIANTS = {
  [ENTITY_ACCESS.FULL]: staffEntityResource,
  [ENTITY_ACCESS.CONTACT]: (entity) => ({
    ...entitySummary(entity),
    ...entityLevelFields(entity),
    ...entityContact(entity),
  }),
  [ENTITY_ACCESS.NONE]: (entity) => ({
    ...entitySummary(entity),
    ...entityLevelFields(entity),
    isDeleted: isDeleted(entity),
  }),
}

export function entityResourceFor(access) {
  return VARIANTS[access] ?? VARIANTS[ENTITY_ACCESS.NONE]
}

export function deletionImpactResource(openRequests, kept, { frozen = false } = {}) {
  return {
    openRequests: openRequests.map((request) => ({
      ...request,
      cycleId: request.cycleId ?? null,
      isFrozen: frozen,
    })),
    openRequestsCount: openRequests.length,
    requiresConfirmation: openRequests.length > 0,
    keptOnPermanentDeletion: [...kept],
  }
}

export function emailChangeResource(change) {
  const entity = findEntity(change.entityId)
  return {
    id: change.id,
    entity: entity ? entitySummary(entity) : null,
    oldEmail: change.oldEmail,
    newEmail: change.newEmail,
    policy: change.policy,
    status: change.status,
    requestedAt: change.requestedAt,
    otpVerifiedAt: change.otpVerifiedAt,
    appliedAt: change.appliedAt,
    decisionReason: change.decisionReason,
  }
}

export function ownEmailChangeResource(change) {
  return { status: change.status, policy: change.policy, newEmail: maskEmail(change.newEmail) }
}

export function privacyRequestResource(request) {
  const entity = findEntity(request.entityId)
  return {
    id: request.id,
    entity: entity ? entitySummary(entity) : null,
    kind: request.kind,
    status: request.status,
    details: request.details,
    createdBy: request.createdBy,
    decidedBy: request.decidedBy,
    decisionNote: request.decisionNote,
    decidedAt: request.decidedAt,
    createdAt: request.createdAt,
  }
}

export function cityResource(city) {
  return {
    id: city.id,
    regionId: city.regionId,
    code: city.code,
    name: { ...city.name },
    sortOrder: city.sortOrder,
    isActive: city.isActive,
  }
}
