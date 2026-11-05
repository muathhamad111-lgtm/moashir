import { findCity, findRegion } from './regionsStore'
import { referenceOf } from './entitiesStore'
import { listEmailChanges } from './entityEmailChanges'
import { listPrivacyRequests } from './privacyRequestsStore'

function entitySection(entity) {
  return {
    reference: referenceOf(entity),
    name: { ...entity.name },
    entityType: entity.entityType,
    developmentSector: entity.developmentSector,
    orgSize: entity.orgSize,
    foundingYear: entity.foundingYear,
    region: { ...findRegion(entity.regionId)?.name },
    city: { ...findCity(entity.cityId)?.name },
    governanceGrade: entity.governanceGrade,
    employeesCount: entity.employeesCount,
    annualBeneficiariesCount: entity.annualBeneficiariesCount,
    hasStrategicPlan: entity.hasStrategicPlan,
    status: entity.status,
    registeredAt: entity.createdAt,
    verifiedAt: entity.verifiedAt,
  }
}

function accountSection(entity) {
  return {
    authorizedPersonName: entity.owner.name,
    officialEmail: entity.owner.email,
    phone: entity.owner.phone,
  }
}

export function personalDataPackage(entity) {
  return {
    generatedAt: new Date().toISOString(),
    entity: entitySection(entity),
    account: accountSection(entity),
    consents: { marketingConsentAt: entity.marketingConsentAt },
    emailChanges: listEmailChanges('all')
      .filter((change) => change.entityId === entity.id)
      .map(({ oldEmail, newEmail, status, requestedAt }) => ({
        oldEmail,
        newEmail,
        status,
        requestedAt,
      })),
    privacyRequests: listPrivacyRequests({ entityId: entity.id }).map(
      ({ kind, status, createdAt }) => ({
        kind,
        status,
        createdAt,
      }),
    ),
  }
}
