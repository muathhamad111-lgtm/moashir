import { applyRegistry } from './entityRegistry'

function entityField(section, key) {
  return {
    section,
    read: (entity) => entity[key] ?? null,
    write: (entity, value) => {
      entity[key] = value
    },
  }
}

function registryField(section, key) {
  return {
    section,
    read: (entity) => entity[key] ?? null,
    write: (entity, value) => applyRegistry(entity, { [key]: value }),
  }
}

/** Every profile field an entity proposes, keyed like the API's change items. */
export const PROFILE_CHANGE_FIELDS = Object.freeze({
  name: {
    section: 'identity',
    read: (entity) => ({ ar: entity.name?.ar ?? '', en: entity.name?.en ?? null }),
    write: (entity, value) => {
      entity.name = { ...value }
    },
  },
  unified_number: entityField('identity', 'unifiedNumber'),
  license_number: registryField('identity', 'licenseNumber'),
  founded_on: registryField('identity', 'foundedOn'),
  entity_type: entityField('classification', 'entityType'),
  development_sector: entityField('classification', 'developmentSector'),
  org_size: entityField('classification', 'orgSize'),
  region_id: entityField('classification', 'regionId'),
  city_id: entityField('classification', 'cityId'),
  governance_grade: entityField('governance', 'governanceGrade'),
  employees_count: entityField('governance', 'employeesCount'),
  annual_beneficiaries_count: entityField('governance', 'annualBeneficiariesCount'),
  has_strategic_plan: entityField('governance', 'hasStrategicPlan'),
  authorized_person_name: {
    section: 'contact',
    read: (entity) => entity.owner?.name ?? null,
    write: (entity, value) => {
      entity.owner.name = value
    },
  },
  phone: {
    section: 'contact',
    read: (entity) => entity.owner?.phone ?? null,
    write: (entity, value) => {
      if (entity.owner.phone !== value) entity.owner.phoneVerifiedAt = null
      entity.owner.phone = value
    },
  },
  website_url: registryField('contact', 'websiteUrl'),
  address_building_number: entityField('billing', 'addressBuildingNumber'),
  address_street: entityField('billing', 'addressStreet'),
  address_district: entityField('billing', 'addressDistrict'),
  address_city: entityField('billing', 'addressCity'),
  address_postal_code: entityField('billing', 'addressPostalCode'),
  address_additional_number: entityField('billing', 'addressAdditionalNumber'),
  address_short_code: entityField('billing', 'addressShortCode'),
  vat_registered: {
    section: 'billing',
    read: (entity) => entity.vatRegistered ?? null,
    write: (entity, value) => {
      entity.vatRegistered = value
      if (value !== true) entity.vatCertificate = null
    },
  },
  vat_number: entityField('billing', 'vatNumber'),
})

export function fieldsOfSection(section) {
  return Object.keys(PROFILE_CHANGE_FIELDS).filter(
    (field) => PROFILE_CHANGE_FIELDS[field].section === section,
  )
}

export function sameValue(left, right) {
  return JSON.stringify(left ?? null) === JSON.stringify(right ?? null)
}
