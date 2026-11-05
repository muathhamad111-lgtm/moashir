import { governanceGradePayload } from '@/utils/governanceGrade'
import { toWesternDigits } from '@/utils/numerals'

const YES = 'yes'
const NO = 'no'

export const PROFILE_FIELDS = [
  'name.ar',
  'name.en',
  'authorizedPersonName',
  'phone',
  'entityType',
  'developmentSector',
  'orgSize',
  'foundingYear',
  'regionId',
  'cityId',
  'governanceGrade',
  'employeesCount',
  'annualBeneficiariesCount',
  'hasStrategicPlan',
]

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

export function emptyProfileValues() {
  return {
    name: { ar: '', en: '' },
    authorizedPersonName: '',
    phone: '',
    entityType: '',
    developmentSector: '',
    orgSize: '',
    foundingYear: '',
    regionId: '',
    cityId: '',
    governanceGrade: '',
    employeesCount: '',
    annualBeneficiariesCount: '',
    hasStrategicPlan: NO,
  }
}

export function profileFormValues(entity) {
  return {
    name: { ar: text(entity.name?.ar), en: text(entity.name?.en) },
    authorizedPersonName: text(entity.authorizedPersonName),
    phone: localPhone(entity.phone),
    entityType: text(entity.entityType),
    developmentSector: text(entity.developmentSector),
    orgSize: text(entity.orgSize),
    foundingYear: text(entity.foundingYear),
    regionId: text(entity.region?.id),
    cityId: text(entity.city?.id),
    governanceGrade: text(entity.governanceGrade),
    employeesCount: text(entity.employeesCount),
    annualBeneficiariesCount: text(entity.annualBeneficiariesCount),
    hasStrategicPlan: entity.hasStrategicPlan ? YES : NO,
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
    governanceGrade: governanceGradePayload(values.governanceGrade),
    employeesCount: optionalNumber(values.employeesCount),
    annualBeneficiariesCount: optionalNumber(values.annualBeneficiariesCount),
    hasStrategicPlan: values.hasStrategicPlan === YES,
  }
}

export function registrationPayload(values, terms) {
  const payload = {
    ...profilePayload(values),
    officialEmail: values.officialEmail.trim(),
    password: values.password,
    passwordConfirmation: values.passwordConfirmation,
    termsVersionId: terms.terms.id,
    termsAccepted: values.termsAccepted === true,
    marketingConsent: values.marketingConsent === true,
  }
  return terms.privacy ? { ...payload, privacyVersionId: terms.privacy.id } : payload
}

function valueAt(values, path) {
  return path.split('.').reduce((current, key) => current?.[key], values)
}

export function profileChanges(before, after, fields = PROFILE_FIELDS) {
  return fields
    .filter((field) => text(valueAt(before, field)).trim() !== text(valueAt(after, field)).trim())
    .map((field) => ({
      field,
      before: text(valueAt(before, field)),
      after: text(valueAt(after, field)),
    }))
}
