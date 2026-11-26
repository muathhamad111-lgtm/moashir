/** The rows each card of the staff entity record shows, in order. */
export const DETAIL_ROWS = [
  'nameAr',
  'nameEn',
  'unifiedNumber',
  'entityType',
  'developmentSector',
  'orgSize',
  'licenseNumber',
  'foundedOn',
  'foundingYear',
  'region',
  'city',
  'governanceGrade',
  'employeesCount',
  'annualBeneficiariesCount',
  'hasStrategicPlan',
]
export const CONTACT_ROWS = [
  'authorizedPersonName',
  'officialEmail',
  'phone',
  'websiteUrl',
  'marketingConsent',
]
export const CONTACT_ONLY_ROWS = ['authorizedPersonName', 'officialEmail', 'phone']
/** The account events of the record's timeline: the date field and, when it has one, its reason. */
export const DELETION_ROWS = ['deletedAt', 'deletionReason']
