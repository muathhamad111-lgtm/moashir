import { governanceGradePayload } from '@/utils/governanceGrade'
import { toWesternDigits } from '@/utils/numerals'
import { PROFILE_READ_ONLY_FIELDS } from '@/constants/entityProfile'
import { readPath, writePath } from './paths'

const YES = 'yes'
const NO = 'no'

function text(value) {
  return value === null || value === undefined ? '' : String(value)
}

function nullableText(value) {
  const trimmed = toWesternDigits(value)
  return trimmed === '' ? null : trimmed
}

function nullableNumber(value) {
  const western = toWesternDigits(value)
  return western === '' ? null : Number(western)
}

function localPhone(phone) {
  return phone ? String(phone).replace(/^\+966/, '0') : ''
}

function planValue(value) {
  if (value === null || value === undefined) return ''
  return value ? YES : NO
}

function planPayload(value) {
  if (value === YES) return true
  if (value === NO) return false
  return null
}

const READERS = Object.freeze({
  'name.ar': (profile) => text(profile.name?.ar),
  'name.en': (profile) => text(profile.name?.en),
  unifiedNumber: (profile) => text(profile.unifiedNumber),
  licenseNumber: (profile) => text(profile.licenseNumber),
  foundedOn: (profile) => text(profile.foundedOn),
  entityType: (profile) => text(profile.entityType),
  developmentSector: (profile) => text(profile.developmentSector),
  orgSize: (profile) => text(profile.orgSize),
  regionId: (profile) => text(profile.region?.id),
  cityId: (profile) => text(profile.city?.id),
  governanceGrade: (profile) => text(profile.governanceGrade),
  employeesCount: (profile) => text(profile.employeesCount),
  annualBeneficiariesCount: (profile) => text(profile.annualBeneficiariesCount),
  hasStrategicPlan: (profile) => planValue(profile.hasStrategicPlan),
  authorizedPersonName: (profile) => text(profile.authorizedPersonName),
  phone: (profile) => localPhone(profile.phone),
  websiteUrl: (profile) => text(profile.websiteUrl),
  marketingConsent: (profile) => profile.marketingConsent === true,
})

const WRITERS = Object.freeze({
  'name.ar': (value) => String(value ?? '').trim(),
  'name.en': (value) => nullableText(String(value ?? '').trim()),
  unifiedNumber: (value) => toWesternDigits(value),
  licenseNumber: nullableText,
  foundedOn: nullableText,
  entityType: (value) => value,
  developmentSector: (value) => value,
  orgSize: (value) => value,
  regionId: (value) => Number(value),
  cityId: (value) => Number(value),
  governanceGrade: governanceGradePayload,
  employeesCount: nullableNumber,
  annualBeneficiariesCount: nullableNumber,
  hasStrategicPlan: planPayload,
  authorizedPersonName: (value) => String(value ?? '').trim(),
  phone: nullableText,
  websiteUrl: (value) => nullableText(String(value ?? '').trim()),
  marketingConsent: (value) => value === true,
})

export function editableFieldsOf(fields) {
  return fields.filter((name) => !PROFILE_READ_ONLY_FIELDS.includes(name) && READERS[name])
}

export function sectionFormValues(profile, fields) {
  return editableFieldsOf(fields).reduce(
    (values, name) => writePath(values, name, READERS[name](profile ?? {})),
    {},
  )
}

export function sectionPayload(values, fields, baseRevision) {
  return editableFieldsOf(fields).reduce(
    (payload, name) => writePath(payload, name, WRITERS[name](readPath(values, name))),
    { baseRevision },
  )
}
