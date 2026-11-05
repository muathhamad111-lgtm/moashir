export const PROFILE_TABS = Object.freeze({
  BASIC: 'basic',
  CONTACT: 'contact',
  OWNER: 'owner',
})

export const PROFILE_TAB_FIELDS = Object.freeze({
  [PROFILE_TABS.BASIC]: [
    'name',
    'unifiedNumber',
    'licenseNumber',
    'foundedOn',
    'foundingYear',
    'regionId',
    'cityId',
    'developmentSector',
    'entityType',
    'orgSize',
    'employeesCount',
    'governanceGrade',
    'annualBeneficiariesCount',
    'hasStrategicPlan',
  ],
  [PROFILE_TABS.CONTACT]: ['phone', 'websiteUrl', 'marketingConsent'],
  [PROFILE_TABS.OWNER]: ['authorizedPersonName'],
})

export const ENTITY_LOGO_RULES = Object.freeze({
  types: ['image/png', 'image/jpeg'],
  accept: '.png,.jpg,.jpeg,image/png,image/jpeg',
  maxBytes: 2 * 1024 * 1024,
  minSide: 200,
  maxSide: 2000,
})

export const ENTITY_LOGO_LINK_LIFETIME_MS = 5 * 60 * 1000
