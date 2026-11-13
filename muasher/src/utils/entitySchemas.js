import { boolean as yupBoolean, object as yupObject, string as yupString } from 'yup'
import {
  DEVELOPMENT_SECTORS,
  ENTITY_TYPES,
  FOUNDING_YEAR_MIN,
  ORG_SIZES,
  PASSWORD_LENGTH,
  PHONE_PATTERN,
  PRIVACY_DETAILS_LENGTH,
  PRIVACY_REQUEST_KINDS,
  REASON_LENGTH,
  UNIFIED_NUMBER_PATTERN,
} from '@/constants/entityEnums'
import { isValidGovernanceGrade } from '@/utils/governanceGrade'
import { toWesternDigits } from '@/utils/numerals'

const NAME_MAX = 200
const PERSON_NAME = { MIN: 3, MAX: 150 }
const EMAIL_MAX = 254
const COUNT_PATTERN = /^\d+$/
const YEAR_PATTERN = /^\d{4}$/
const CITY_NAME_MAX = 120
const LICENSE_PATTERN = /^\d{3,20}$/
const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/
const EARLIEST_FOUNDING_DATE = '1900-01-01'
const WEBSITE_MAX = 255
const HTTPS_PREFIX = 'https://'

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
    governanceGrade: yupString().test(
      'grade',
      t('entities.validation.governanceGrade'),
      isValidGovernanceGrade,
    ),
    employeesCount: optionalCount(t),
    annualBeneficiariesCount: optionalCount(t),
    hasStrategicPlan: choice(t, ['yes', 'no']),
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

export function staffProfileSchema(t, { unifiedNumberRequired = false } = {}) {
  return yupObject({
    ...entityProfileShape(t),
    hasStrategicPlan: yupString().oneOf(['', 'yes', 'no']),
    unifiedNumber: unifiedNumberField(t, { required: unifiedNumberRequired }),
    reason: reasonText(t),
  })
}

export function registrationSchema(t) {
  return yupObject({
    ...entityProfileShape(t),
    unifiedNumber: unifiedNumberField(t),
    officialEmail: yupString()
      .trim()
      .required(t('validation.required'))
      .email(t('validation.email'))
      .max(EMAIL_MAX),
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
    marketingConsent: yupBoolean(),
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

export function privacyRequestSchema(t) {
  return yupObject({
    entityId: yupString().required(t('validation.required')),
    kind: choice(t, PRIVACY_REQUEST_KINDS),
    details: yupString()
      .trim()
      .required(t('validation.required'))
      .min(
        PRIVACY_DETAILS_LENGTH.MIN,
        t('validation.minChars', { min: PRIVACY_DETAILS_LENGTH.MIN }),
      )
      .max(
        PRIVACY_DETAILS_LENGTH.MAX,
        t('entities.validation.maxChars', { max: PRIVACY_DETAILS_LENGTH.MAX }),
      ),
  })
}
