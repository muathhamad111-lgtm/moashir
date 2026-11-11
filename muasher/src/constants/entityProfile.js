export const PROFILE_SECTION_ICONS = Object.freeze({
  identity: 'building',
  classification: 'location',
  governance: 'chart-bar',
  contact: 'envelope',
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

export const PROFILE_READ_ONLY_FIELDS = Object.freeze(['officialEmail'])

export const PROFILE_LTR_FIELDS = Object.freeze([
  'name.en',
  'unifiedNumber',
  'licenseNumber',
  'governanceGrade',
  'officialEmail',
  'phone',
  'websiteUrl',
])

export const PROFILE_FIELD_INPUTS = Object.freeze({
  'name.ar': { kind: 'text', inputDir: 'rtl' },
  'name.en': { kind: 'text', inputDir: 'ltr' },
  unifiedNumber: { kind: 'digits', hint: 'unifiedNumber' },
  licenseNumber: { kind: 'text', inputDir: 'ltr', inputmode: 'numeric', hint: 'licenseNumber' },
  foundedOn: { kind: 'date', hint: 'foundedOn' },
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
})

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
