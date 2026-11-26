import { boolean as yupBoolean, object as yupObject, string as yupString } from 'yup'
import { CONTACT_ENQUIRY_TYPES, CONTACT_LIMITS, CONTACT_MOBILE_PATTERN } from '@/constants/contact'

function bounded(t, { min, max }) {
  let rule = yupString().trim()
  if (min) rule = rule.min(min, t('validation.minChars', { min }))
  return rule.max(max, t('validation.maxChars', { max }))
}

function identity(t, isGuest, rule) {
  return isGuest ? rule.required(t('validation.required')) : yupString().nullable()
}

export function contactSchema(t, { isGuest }) {
  return yupObject({
    name: identity(
      t,
      isGuest,
      bounded(t, { min: CONTACT_LIMITS.nameMin, max: CONTACT_LIMITS.nameMax }),
    ),
    email: identity(
      t,
      isGuest,
      bounded(t, { max: CONTACT_LIMITS.emailMax }).email(t('validation.email')),
    ),
    mobile: yupString()
      .trim()
      .test('mobile', t('public.home.contact.form.mobileFormat'), (value) => {
        return !value || CONTACT_MOBILE_PATTERN.test(value)
      }),
    organization: isGuest
      ? bounded(t, { max: CONTACT_LIMITS.organizationMax })
      : yupString().nullable(),
    enquiryType: yupString()
      .required(t('validation.required'))
      .oneOf(CONTACT_ENQUIRY_TYPES, t('validation.required')),
    message: bounded(t, {
      min: CONTACT_LIMITS.messageMin,
      max: CONTACT_LIMITS.messageMax,
    }).required(t('validation.required')),
    privacyConsent: yupBoolean().oneOf([true], t('validation.mustAgree')),
  })
}
