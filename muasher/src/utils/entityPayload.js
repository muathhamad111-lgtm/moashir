import { governanceGradePayload } from '@/utils/governanceGrade'
import { toWesternDigits } from '@/utils/numerals'

const YES = 'yes'
const NO = 'no'

function text(value) {
  return value === null || value === undefined ? '' : String(value)
}

function optionalNumber(value) {
  const western = toWesternDigits(value)
  return western === '' ? null : Number(western)
}

function localPhone(phone) {
  return phone ? phone.replace(/^\+966/, '0') : ''
}

function answerValue(value) {
  if (value === null || value === undefined) return ''
  return value ? YES : NO
}

// Only an entity that said it has no grade starts ticked; any other starts with the grade required.
function gradeAnswerValue(value) {
  return value === false ? NO : YES
}

function answerPayload(value) {
  if (value === YES) return true
  return value === NO ? false : null
}

export function unifiedNumberPayload(value) {
  const unifiedNumber = toWesternDigits(value)
  return unifiedNumber ? { unifiedNumber } : {}
}

export function emptyProfileValues() {
  return {
    name: { ar: '', en: '' },
    unifiedNumber: '',
    authorizedPersonName: '',
    phone: '',
    entityType: '',
    developmentSector: '',
    orgSize: '',
    foundingYear: '',
    regionId: '',
    cityId: '',
    hasGovernanceGrade: YES,
    governanceGrade: '',
    employeesCount: '',
    annualBeneficiariesCount: '',
    hasStrategicPlan: NO,
  }
}

export function profileFormValues(entity) {
  return {
    name: { ar: text(entity.name?.ar), en: text(entity.name?.en) },
    unifiedNumber: text(entity.unifiedNumber),
    authorizedPersonName: text(entity.authorizedPersonName),
    phone: localPhone(entity.phone),
    entityType: text(entity.entityType),
    developmentSector: text(entity.developmentSector),
    orgSize: text(entity.orgSize),
    foundingYear: text(entity.foundingYear),
    regionId: text(entity.region?.id),
    cityId: text(entity.city?.id),
    hasGovernanceGrade: gradeAnswerValue(entity.hasGovernanceGrade),
    governanceGrade: text(entity.governanceGrade),
    employeesCount: text(entity.employeesCount),
    annualBeneficiariesCount: text(entity.annualBeneficiariesCount),
    hasStrategicPlan: answerValue(entity.hasStrategicPlan),
  }
}

export function profilePayload(values) {
  const english = values.name.en.trim()
  return {
    name: { ar: values.name.ar.trim(), en: english === '' ? null : english },
    authorizedPersonName: values.authorizedPersonName.trim(),
    phone: values.phone ? toWesternDigits(values.phone) : null,
    entityType: values.entityType,
    developmentSector: values.developmentSector,
    orgSize: values.orgSize,
    foundingYear: Number(toWesternDigits(values.foundingYear)),
    regionId: Number(values.regionId),
    cityId: Number(values.cityId),
    hasGovernanceGrade: answerPayload(values.hasGovernanceGrade),
    governanceGrade:
      values.hasGovernanceGrade === YES ? governanceGradePayload(values.governanceGrade) : null,
    employeesCount: optionalNumber(values.employeesCount),
    annualBeneficiariesCount: optionalNumber(values.annualBeneficiariesCount),
    hasStrategicPlan: answerPayload(values.hasStrategicPlan),
  }
}

function foundingYearOf(foundedOn) {
  return Number(foundedOn.slice(0, 4))
}

function optionalText(value) {
  const text = toWesternDigits(value)
  return text === '' ? null : text
}

export function registrationPayload(values, terms) {
  const payload = {
    ...profilePayload(values),
    ...unifiedNumberPayload(values.unifiedNumber),
    foundingYear: foundingYearOf(values.foundedOn),
    foundedOn: values.foundedOn,
    licenseNumber: optionalText(values.licenseNumber),
    licenseCertificate: values.licenseCertificate,
    authorizationLetter: values.authorizationLetter,
    officialEmail: values.officialEmail.trim(),
    emailVerificationToken: values.emailVerificationToken,
    password: values.password,
    passwordConfirmation: values.passwordConfirmation,
    termsVersionId: terms.terms.id,
    termsAccepted: values.termsAccepted === true,
  }
  return terms.privacy ? { ...payload, privacyVersionId: terms.privacy.id } : payload
}
