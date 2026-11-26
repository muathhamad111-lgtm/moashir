import { string as yupString } from 'yup'
import { toWesternDigits } from '@/utils/numerals'
import { BILLING_PATTERNS, BILLING_TEXT_LIMITS } from '@/constants/entityProfile'

const YES = 'yes'

function patternField(t, pattern, messageKey) {
  return yupString().test(
    messageKey,
    t(`portal.profile.billing.validation.${messageKey}`),
    (value) => {
      const text = toWesternDigits(value)
      return !text || pattern.test(text)
    },
  )
}

function textField(t, max) {
  return yupString().max(max, t('validation.maxChars', { max }))
}

function vatNumberField(t) {
  return yupString().when('vatRegistered', ([answer], rule) =>
    answer === YES
      ? rule
          .required(t('validation.required'))
          .test('vatNumber', t('portal.profile.billing.validation.vatNumber'), (value) => {
            const text = toWesternDigits(value)
            return !text || BILLING_PATTERNS.VAT_NUMBER.test(text)
          })
      : rule,
  )
}

export function billingRules(t) {
  return {
    addressBuildingNumber: patternField(t, BILLING_PATTERNS.BUILDING_NUMBER, 'buildingNumber'),
    addressStreet: textField(t, BILLING_TEXT_LIMITS.STREET),
    addressDistrict: textField(t, BILLING_TEXT_LIMITS.DISTRICT),
    addressCity: textField(t, BILLING_TEXT_LIMITS.CITY),
    addressPostalCode: patternField(t, BILLING_PATTERNS.POSTAL_CODE, 'postalCode'),
    addressAdditionalNumber: patternField(
      t,
      BILLING_PATTERNS.ADDITIONAL_NUMBER,
      'additionalNumber',
    ),
    addressShortCode: patternField(t, BILLING_PATTERNS.SHORT_CODE, 'shortCode'),
    vatRegistered: yupString(),
    vatNumber: vatNumberField(t),
  }
}
