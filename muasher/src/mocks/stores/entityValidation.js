import { ENTITY_TYPES, DEVELOPMENT_SECTORS, ORG_SIZES } from '@/constants/entityEnums'
import { activeCityOf, findRegion } from './regionsStore'
import { entityReject, fieldError } from './entityRejection'
import { checkRegistry } from './entityRegistry'
import { checkUnifiedNumber } from './entityUnifiedNumber'

const PHONE_PATTERN = /^(05\d{8}|\+9665\d{8})$/
const GRADE_PATTERN = /^\d{1,3}(\.\d{1,2})?$/
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const MIN_FOUNDING_YEAR = 1900
const PASSWORD_MIN = 12
export const REASON_MIN = 10
export const PERMANENT_REASON_MIN = 20

function blank(value) {
  return value === null || value === undefined || String(value).trim() === ''
}

function isWholeNumber(value) {
  return Number.isInteger(Number(value)) && Number(value) >= 0 && !blank(value)
}

function collector() {
  const fields = {}
  return {
    fields,
    add(field, code, params) {
      fields[field] = [...(fields[field] ?? []), fieldError(code, params)]
    },
    assertValid() {
      if (Object.keys(fields).length) throw entityReject.validation(fields)
    },
  }
}

function checkRequired(errors, body, keys) {
  keys.filter((key) => blank(body[key])).forEach((key) => errors.add(key, 'field.required'))
}

function checkChoices(errors, body) {
  const choices = {
    entityType: ENTITY_TYPES,
    developmentSector: DEVELOPMENT_SECTORS,
    orgSize: ORG_SIZES,
  }
  Object.entries(choices)
    .filter(([key, allowed]) => !blank(body[key]) && !allowed.includes(body[key]))
    .forEach(([key]) => errors.add(key, 'field.invalid_choice'))
}

function checkPlace(errors, body) {
  if (!blank(body.regionId) && !findRegion(body.regionId))
    errors.add('regionId', 'field.invalid_choice')
  if (!blank(body.cityId) && !activeCityOf(body.regionId, body.cityId)) {
    errors.add('cityId', 'field.city_not_in_region')
  }
}

function checkGrade(errors, value) {
  if (blank(value)) return
  const text = String(value)
  if (!GRADE_PATTERN.test(text) || Number(text) > 100)
    errors.add('governanceGrade', 'field.governance_grade')
}

function checkNumbers(errors, body) {
  const year = Number(body.foundingYear)
  if (
    !blank(body.foundingYear) &&
    (!Number.isInteger(year) || year < MIN_FOUNDING_YEAR || year > new Date().getFullYear())
  ) {
    errors.add('foundingYear', 'field.founding_year')
  }
  ;['employeesCount', 'annualBeneficiariesCount']
    .filter((key) => !blank(body[key]) && !isWholeNumber(body[key]))
    .forEach((key) => errors.add(key, 'field.count'))
}

function checkContact(errors, body) {
  if (blank(body.name?.ar)) errors.add('name.ar', 'field.required')
  if (!blank(body.phone) && !PHONE_PATTERN.test(String(body.phone)))
    errors.add('phone', 'field.phone')
}

function profileErrors(body) {
  const errors = collector()
  checkRequired(errors, body, [
    'authorizedPersonName',
    'entityType',
    'developmentSector',
    'orgSize',
    'foundingYear',
    'regionId',
    'cityId',
  ])
  if (body.hasStrategicPlan === undefined || body.hasStrategicPlan === null)
    errors.add('hasStrategicPlan', 'field.required')
  checkContact(errors, body)
  checkChoices(errors, body)
  checkPlace(errors, body)
  checkGrade(errors, body.governanceGrade)
  checkNumbers(errors, body)
  return errors
}

function optionalCount(value) {
  return blank(value) ? null : Number(value)
}

export function profileFrom(body) {
  return {
    name: {
      ar: String(body.name.ar).trim(),
      en: blank(body.name.en) ? null : String(body.name.en).trim(),
    },
    entityType: body.entityType,
    developmentSector: body.developmentSector,
    orgSize: body.orgSize,
    foundingYear: Number(body.foundingYear),
    regionId: Number(body.regionId),
    cityId: Number(body.cityId),
    governanceGrade: blank(body.governanceGrade) ? null : Number(body.governanceGrade).toFixed(2),
    employeesCount: optionalCount(body.employeesCount),
    annualBeneficiariesCount: optionalCount(body.annualBeneficiariesCount),
    hasStrategicPlan: Boolean(body.hasStrategicPlan),
  }
}

export function contactFrom(body) {
  const phone = blank(body.phone) ? null : String(body.phone)
  return {
    authorizedPersonName: String(body.authorizedPersonName).trim(),
    phone: phone && phone.startsWith('05') ? `+966${phone.slice(1)}` : phone,
  }
}

export function assertProfile(body, entityId = null) {
  const errors = profileErrors(body)
  checkUnifiedNumber(errors, body, { entityId })
  errors.assertValid()
}

export function assertOwnProfile(body, entityId = null) {
  const errors = profileErrors(body)
  if (typeof body.marketingConsent !== 'boolean') errors.add('marketingConsent', 'field.required')
  checkRegistry(errors, body, entityId)
  checkUnifiedNumber(errors, body, { entityId })
  errors.assertValid()
}

export function assertRegistration(body, terms) {
  const errors = profileErrors(body)
  checkUnifiedNumber(errors, body, { required: true })
  if (blank(body.officialEmail) || !EMAIL_PATTERN.test(String(body.officialEmail)))
    errors.add('officialEmail', 'field.email')
  if (String(body.password ?? '').length < PASSWORD_MIN)
    errors.add('password', 'field.password_min')
  else if (body.password !== body.passwordConfirmation)
    errors.add('password', 'field.password_confirmed')
  if (Number(body.termsVersionId) !== terms.terms.id)
    errors.add('termsVersionId', 'field.terms_not_current')
  if (terms.privacy && Number(body.privacyVersionId) !== terms.privacy.id)
    errors.add('privacyVersionId', 'field.terms_not_current')
  if (body.termsAccepted !== true) errors.add('termsAccepted', 'field.terms_accepted')
  errors.assertValid()
}

export function assertReason(body, min = REASON_MIN, field = 'reason') {
  const text = String(body?.[field] ?? '').trim()
  if (text.length >= min) return text
  const errors = collector()
  errors.add(field, blank(text) ? 'field.required' : 'field.reason_min', { min })
  errors.assertValid()
  return text
}

export function optionalReason(body) {
  const text = String(body?.reason ?? '').trim()
  return text ? assertReason(body) : null
}

export function assertEmail(field, value) {
  if (!blank(value) && EMAIL_PATTERN.test(String(value))) return String(value).trim()
  const errors = collector()
  errors.add(field, 'field.email')
  errors.assertValid()
  return value
}

export function rejectField(field, code, params) {
  const errors = collector()
  errors.add(field, code, params)
  errors.assertValid()
}
