import {
  boolean as yupBoolean,
  mixed as yupMixed,
  object as yupObject,
  string as yupString,
} from 'yup'
import {
  DEVELOPMENT_SECTORS,
  EARLIEST_FOUNDING_DATE,
  ENTITY_TYPES,
  FOUNDING_YEAR_MIN,
  ORG_SIZES,
  PASSWORD_LENGTH,
  PHONE_PATTERN,
  REASON_LENGTH,
  UNIFIED_NUMBER_PATTERN,
} from '@/constants/entityEnums'
import { isValidGovernanceGrade } from '@/utils/governanceGrade'
import { entityDocumentIssue } from '@/utils/entityDocumentRules'
import { ENTITY_DOCUMENT_RULES } from '@/constants/entityDocuments'
import { toWesternDigits } from '@/utils/numerals'
import { riyadhDateKey } from '@/utils/datetime'

const NAME_MAX = 200
const PERSON_NAME = { MIN: 3, MAX: 150 }
const EMAIL_MAX = 254
const COUNT_PATTERN = /^\d+$/
const YEAR_PATTERN = /^\d{4}$/
const CITY_NAME_MAX = 120
const LICENSE_PATTERN = /^\d{3,20}$/
const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/
const WEBSITE_MAX = 255
const HTTPS_PREFIX = 'https://'
const YES = 'yes'
const YES_NO = ['yes', 'no']

function requiredText(t, max) {
  return yupString()
    .trim()
    .required(t('validation.required'))
    .max(max, t('entities.validation.maxChars', { max }))
}

function choice(t, values) {
  return yupString().required(t('validation.required')).oneOf(values, t('validation.required'))
}

function optionalCount(t) {
  return yupString().test('count', t('entities.validation.count'), (value) => {
    const text = toWesternDigits(value)
    return text === '' || COUNT_PATTERN.test(text)
  })
}

function foundingYear(t) {
  const currentYear = new Date().getFullYear()
  return yupString()
    .required(t('validation.required'))
    .test(
      'year',
      t('entities.validation.foundingYear', { min: FOUNDING_YEAR_MIN, max: currentYear }),
      (value) => {
        const text = toWesternDigits(value)
        return (
          YEAR_PATTERN.test(text) &&
          Number(text) >= FOUNDING_YEAR_MIN &&
          Number(text) <= currentYear
        )
      },
    )
}

function governanceGradeField(t) {
  return yupString().when('hasGovernanceGrade', ([answer]) =>
    answer === YES
      ? yupString()
          .required(t('validation.required'))
          .test('grade', t('entities.validation.governanceGrade'), isValidGovernanceGrade)
      : yupString().nullable(),
  )
}

export function unifiedNumberField(t, { required = true } = {}) {
  return yupString()
    .trim()
    .test('required', t('validation.required'), (value) => !required || Boolean(value))
    .test(
      'unifiedNumber',
      t('entities.validation.unifiedNumber'),
      (value) => !value || UNIFIED_NUMBER_PATTERN.test(toWesternDigits(value)),
    )
}

export function entityProfileShape(t) {
  return {
    name: yupObject({
      ar: requiredText(t, NAME_MAX),
      en: yupString()
        .trim()
        .max(NAME_MAX, t('entities.validation.maxChars', { max: NAME_MAX })),
    }),
    authorizedPersonName: requiredText(t, PERSON_NAME.MAX).min(
      PERSON_NAME.MIN,
      t('validation.minChars', { min: PERSON_NAME.MIN }),
    ),
    phone: yupString()
      .trim()
      .test(
        'phone',
        t('entities.validation.phone'),
        (value) => !value || PHONE_PATTERN.test(toWesternDigits(value)),
      ),
    entityType: choice(t, ENTITY_TYPES),
    developmentSector: choice(t, DEVELOPMENT_SECTORS),
    orgSize: choice(t, ORG_SIZES),
    foundingYear: foundingYear(t),
    regionId: yupString().required(t('validation.required')),
    cityId: yupString().required(t('validation.required')),
    hasGovernanceGrade: choice(t, YES_NO),
    governanceGrade: governanceGradeField(t),
    employeesCount: optionalCount(t),
    annualBeneficiariesCount: optionalCount(t),
    hasStrategicPlan: choice(t, YES_NO),
  }
}

function isCalendarDate(value) {
  if (!DATE_PATTERN.test(value)) return false
  const date = new Date(`${value}T00:00:00Z`)
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value
}

function isHttpsAddress(value) {
  if (!value.startsWith(HTTPS_PREFIX) || /\s/.test(value)) return false
  try {
    return Boolean(new URL(value).hostname.includes('.'))
  } catch {
    return false
  }
}

export function registryShape(t, today) {
  return {
    licenseNumber: yupString()
      .trim()
      .test('license', t('entities.validation.licenseNumber'), (value) => {
        const text = toWesternDigits(value)
        return !text || LICENSE_PATTERN.test(text)
      }),
    foundedOn: yupString()
      .trim()
      .test(
        'date',
        t('entities.validation.foundedOn', { min: EARLIEST_FOUNDING_DATE }),
        (value) =>
          !value || (isCalendarDate(value) && value >= EARLIEST_FOUNDING_DATE && value <= today),
      ),
    websiteUrl: yupString()
      .trim()
      .max(WEBSITE_MAX, t('entities.validation.maxChars', { max: WEBSITE_MAX }))
      .test(
        'https',
        t('entities.validation.websiteUrl'),
        (value) => !value || isHttpsAddress(value),
      ),
  }
}

export function staffProfileSchema(
  t,
  { unifiedNumberRequired = false, currentPersonName = '' } = {},
) {
  return yupObject({
    ...entityProfileShape(t),
    hasStrategicPlan: yupString().oneOf(['', 'yes', 'no']),
    unifiedNumber: unifiedNumberField(t, { required: unifiedNumberRequired }),
    authorizationLetter: staffAuthorizationLetterRule(t, currentPersonName),
    reason: reasonText(t),
  })
}

export function isAuthorizedPersonChanged(name, currentPersonName) {
  return (name ?? '').trim() !== (currentPersonName ?? '').trim()
}

// A staff edit that names a new authorized person carries a new letter (operator 2026-11-25); otherwise a letter is optional.
function staffAuthorizationLetterRule(t, currentPersonName) {
  return yupMixed()
    .nullable()
    .test('staff-letter', (file, context) => {
      const changed = isAuthorizedPersonChanged(
        context.parent.authorizedPersonName,
        currentPersonName,
      )
      return !file && !changed ? true : documentCheck(t, file, context)
    })
}

function documentCheck(t, file, context) {
  const issue = entityDocumentIssue(file)
  if (!issue) return true
  return context.createError({
    message: t(`entities.documents.errors.${issue}`, { max: ENTITY_DOCUMENT_RULES.maxMb }),
  })
}

export function entityDocumentRule(t) {
  return yupMixed()
    .nullable()
    .test('entity-document', (file, context) => documentCheck(t, file, context))
}

export function registrationSchema(t) {
  const registry = registryShape(t, riyadhDateKey(new Date()))
  return yupObject({
    ...entityProfileShape(t),
    name: yupObject({ ar: requiredText(t, NAME_MAX), en: requiredText(t, NAME_MAX) }),
    foundingYear: yupString(),
    foundedOn: registry.foundedOn.required(t('validation.required')),
    licenseNumber: registry.licenseNumber.required(t('validation.required')),
    licenseCertificate: entityDocumentRule(t),
    authorizationLetter: entityDocumentRule(t),
    hasStrategicPlan: yupString().oneOf(['', 'yes', 'no']),
    unifiedNumber: unifiedNumberField(t),
    officialEmail: yupString()
      .trim()
      .required(t('validation.required'))
      .email(t('validation.email'))
      .max(EMAIL_MAX),
    emailVerificationToken: yupString().required(t('entities.registration.emailVerify.required')),
    password: yupString()
      .required(t('validation.required'))
      .min(PASSWORD_LENGTH.MIN, t('entities.validation.passwordMin', { min: PASSWORD_LENGTH.MIN }))
      .max(PASSWORD_LENGTH.MAX, t('entities.validation.maxChars', { max: PASSWORD_LENGTH.MAX })),
    passwordConfirmation: yupString()
      .required(t('validation.required'))
      .test('match', t('entities.validation.passwordMatch'), function matches(value) {
        return value === this.parent.password
      }),
    termsAccepted: yupBoolean().oneOf([true], t('entities.validation.termsRequired')),
  })
}

export function reasonText(t, min = REASON_LENGTH.MIN) {
  return yupString()
    .trim()
    .required(t('validation.required'))
    .min(min, t('validation.minChars', { min }))
    .max(REASON_LENGTH.MAX, t('entities.validation.maxChars', { max: REASON_LENGTH.MAX }))
}

export function reasonSchema(t, min = REASON_LENGTH.MIN) {
  return yupObject({ reason: reasonText(t, min) })
}

export function optionalReasonSchema(t) {
  return yupObject({
    reason: yupString()
      .trim()
      .test(
        'min',
        t('validation.minChars', { min: REASON_LENGTH.MIN }),
        (value) => !value || value.length >= REASON_LENGTH.MIN,
      ),
  })
}

export function emailChangeSchema(t) {
  return yupObject({
    newEmail: yupString()
      .trim()
      .required(t('validation.required'))
      .email(t('validation.email'))
      .max(EMAIL_MAX),
  })
}

export function permanentDeletionSchema(t, reference) {
  return yupObject({
    disclaimerAcknowledged: yupBoolean().oneOf(
      [true],
      t('entities.permanent.validation.disclaimer'),
    ),
    confirmation: yupString()
      .trim()
      .required(t('validation.required'))
      .test(
        'reference',
        t('entities.permanent.validation.reference', { reference }),
        (value) =>
          String(value ?? '')
            .trim()
            .toUpperCase() === reference,
      ),
    reason: reasonText(t, REASON_LENGTH.PERMANENT_MIN),
  })
}

export function citySchema(t) {
  return yupObject({
    regionId: yupString().required(t('validation.required')),
    name: yupObject({ ar: requiredText(t, CITY_NAME_MAX), en: requiredText(t, CITY_NAME_MAX) }),
    sortOrder: optionalCount(t),
  })
}
