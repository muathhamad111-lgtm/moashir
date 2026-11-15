export const REGISTRATION_STEPS = Object.freeze([
  Object.freeze({
    id: 'entity',
    labelKey: 'entities.registration.steps.entity',
    fields: Object.freeze([
      'entityType',
      'unifiedNumber',
      'name.ar',
      'name.en',
      'regionId',
      'cityId',
      'developmentSector',
      'orgSize',
      'foundingYear',
      'employeesCount',
      'governanceGrade',
      'annualBeneficiariesCount',
    ]),
  }),
  Object.freeze({
    id: 'account',
    labelKey: 'entities.registration.steps.account',
    fields: Object.freeze([
      'authorizedPersonName',
      'phone',
      'officialEmail',
      'emailVerificationToken',
      'password',
      'passwordConfirmation',
      'termsAccepted',
      'marketingConsent',
    ]),
  }),
])
