export const PROFILE_SECTION_ICONS = Object.freeze({
  identity: 'building',
  classification: 'location',
  governance: 'chart-bar',
  contact: 'envelope',
  billing: 'sar',
})

export const PROFILE_REQUIREMENTS = Object.freeze({
  REQUIRED: 'required',
  COMPLETION: 'completion',
  OPTIONAL: 'optional',
})

export const PROFILE_SECTION_STATES = Object.freeze({
  COMPLETE: 'complete',
  INCOMPLETE: 'incomplete',
})

export const PROFILE_SAVE_STATES = Object.freeze({
  IDLE: 'idle',
  SAVING: 'saving',
  SAVED: 'saved',
  ERROR: 'error',
  STALE: 'stale',
})

export const PROFILE_STALE_CODE = 'profile.section_stale'

export const PROFILE_READ_ONLY_FIELDS = Object.freeze(['officialEmail', 'vatCertificate'])

export const PROFILE_FILE_FIELDS = Object.freeze(['vatCertificate'])

/** The fields that only show and count after a «yes» to the answer they map to. */
export const PROFILE_FIELD_GATES = Object.freeze({
  governanceGrade: 'hasGovernanceGrade',
  vatNumber: 'vatRegistered',
  vatCertificate: 'vatRegistered',
})

export const PROFILE_LTR_FIELDS = Object.freeze([
  'name.en',
  'unifiedNumber',
  'licenseNumber',
  'governanceGrade',
  'officialEmail',
  'phone',
  'websiteUrl',
  'addressBuildingNumber',
  'addressPostalCode',
  'addressAdditionalNumber',
  'addressShortCode',
  'vatNumber',
])

export const PROFILE_FIELD_INPUTS = Object.freeze({
  'name.ar': { kind: 'text', inputDir: 'rtl' },
  'name.en': { kind: 'text', inputDir: 'ltr' },
  unifiedNumber: { kind: 'digits' },
  licenseNumber: { kind: 'text', inputDir: 'ltr', inputmode: 'numeric' },
  foundedOn: { kind: 'date' },
  entityType: { kind: 'select', options: 'entityType' },
  developmentSector: { kind: 'select', options: 'developmentSector' },
  orgSize: { kind: 'select', options: 'orgSize' },
  regionId: { kind: 'select', options: 'region' },
  cityId: { kind: 'select', options: 'city' },
  governanceGrade: {
    kind: 'text',
    inputDir: 'ltr',
    inputmode: 'decimal',
    hint: 'governanceGrade',
  },
  employeesCount: { kind: 'text', inputmode: 'numeric' },
  annualBeneficiariesCount: { kind: 'text', inputmode: 'numeric' },
  hasStrategicPlan: { kind: 'select', options: 'strategicPlan' },
  hasGovernanceGrade: { kind: 'governance', hint: 'governanceGrade' },
  authorizedPersonName: { kind: 'text', autocomplete: 'name' },
  phone: {
    kind: 'text',
    type: 'tel',
    inputDir: 'ltr',
    inputmode: 'tel',
    autocomplete: 'tel',
    hint: 'phone',
  },
  websiteUrl: {
    kind: 'text',
    type: 'url',
    inputDir: 'ltr',
    inputmode: 'url',
    autocomplete: 'url',
    hint: 'websiteUrl',
  },
  marketingConsent: { kind: 'checkbox' },
  addressBuildingNumber: {
    kind: 'text',
    inputDir: 'ltr',
    inputmode: 'numeric',
    hint: 'addressBuildingNumber',
  },
  addressStreet: { kind: 'text', autocomplete: 'address-line1' },
  addressDistrict: { kind: 'text', autocomplete: 'address-line2' },
  addressCity: { kind: 'text', autocomplete: 'address-level2' },
  addressPostalCode: {
    kind: 'text',
    inputDir: 'ltr',
    inputmode: 'numeric',
    autocomplete: 'postal-code',
    hint: 'addressPostalCode',
  },
  addressAdditionalNumber: {
    kind: 'text',
    inputDir: 'ltr',
    inputmode: 'numeric',
    hint: 'addressAdditionalNumber',
  },
  addressShortCode: { kind: 'text', inputDir: 'ltr', hint: 'addressShortCode' },
  vatRegistered: { kind: 'radio', options: 'yesNo' },
  vatNumber: { kind: 'text', inputDir: 'ltr', inputmode: 'numeric', hint: 'vatNumber' },
})

export const BILLING_PATTERNS = Object.freeze({
  BUILDING_NUMBER: /^\d{4}$/,
  POSTAL_CODE: /^\d{5}$/,
  ADDITIONAL_NUMBER: /^\d{4}$/,
  SHORT_CODE: /^[A-Za-z]{4}\d{4}$/,
  VAT_NUMBER: /^3\d{13}3$/,
})

export const BILLING_TEXT_LIMITS = Object.freeze({ STREET: 150, DISTRICT: 150, CITY: 100 })

export const ENTITY_LOGO_RULES = Object.freeze({
  types: ['image/png', 'image/jpeg'],
  accept: '.png,.jpg,.jpeg,image/png,image/jpeg',
  maxBytes: 2 * 1024 * 1024,
  minSide: 200,
  maxSide: 2000,
})

export const ENTITY_LOGO_LINK_LIFETIME_MS = 5 * 60 * 1000

export const ACCOUNT_STATUS_KEYS = Object.freeze({
  active: 'active',
  deactivated: 'deactivated',
  'pending-verification': 'pendingVerification',
})
