import { COLUMN_KINDS } from '@/utils/tableColumns'

// The optional entity fields a viewer may add to each variant of the entities list (hidden until
// chosen), in display order. The authorized person and the official e-mail stay in the contact
// variant only; free-text reasons and the postal address stay on the entity's page.
const FULL_FIELDS = [
  'city',
  'developmentSector',
  'orgSize',
  'foundingYear',
  'governanceGrade',
  'employeesCount',
  'annualBeneficiariesCount',
  'unifiedNumber',
  'licenseNumber',
  'levelCertificateNumber',
  'levelValidUntil',
  'verifiedAt',
  'approvedAt',
  'deactivatedAt',
  'profileCompleted',
  'billingComplete',
  'vatRegistered',
  'vatNumber',
  'marketingConsent',
  'websiteUrl',
]
const CONTACT_FIELDS = ['levelCertificateNumber', 'levelValidUntil', 'region', 'createdAt']
const DELETED_FIELDS = ['entityType', 'level', 'createdAt']

export const ENTITY_COLUMN_SETS = Object.freeze({
  full: [
    'name',
    'level',
    'lastAssessment',
    'typeRegion',
    'statusRegistered',
    ...FULL_FIELDS,
    'actions',
  ],
  contact: [
    'name',
    'level',
    'authorizedPersonName',
    'officialEmail',
    'status',
    ...CONTACT_FIELDS,
    'actions',
  ],
  deleted: ['name', 'region', 'deletedAt', 'status', ...DELETED_FIELDS, 'actions'],
})

// The fields shown as plain formatted values (one shared cell); the others have their own cells.
export const ENTITY_FACT_FIELDS = Object.freeze(
  [...new Set([...FULL_FIELDS, ...CONTACT_FIELDS, ...DELETED_FIELDS])].filter(
    (key) => !['region', 'level'].includes(key),
  ),
)

const K = COLUMN_KINDS
const FIELD_KINDS = Object.freeze({
  developmentSector: K.STATUS,
  orgSize: K.STATUS,
  entityType: K.STATUS,
  profileCompleted: K.STATUS,
  billingComplete: K.STATUS,
  vatRegistered: K.STATUS,
  marketingConsent: K.STATUS,
  foundingYear: K.NUMBER,
  governanceGrade: K.NUMBER,
  employeesCount: K.NUMBER,
  annualBeneficiariesCount: K.NUMBER,
  unifiedNumber: K.CODE,
  licenseNumber: K.CODE,
  levelCertificateNumber: K.CODE,
  vatNumber: K.CODE,
  levelValidUntil: K.DATE,
})

export function entityColumnKindOf(key) {
  return FIELD_KINDS[key]
}
