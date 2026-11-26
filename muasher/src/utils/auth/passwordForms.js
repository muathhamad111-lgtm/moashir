import { boolean as yupBoolean, object as yupObject, string as yupString } from 'yup'
import { PASSWORD_LENGTH } from '@/constants/entityEnums'
import { nationalIdRule } from '@/utils/identity/nationalIdSchema'
import { normalizeNationalId } from '@/utils/identity/nationalId'

const EMAIL_MAX = 254

function emailField(t) {
  return yupString()
    .trim()
    .required(t('validation.required'))
    .email(t('validation.email'))
    .max(EMAIL_MAX, t('entities.validation.maxChars', { max: EMAIL_MAX }))
}

function newPasswordShape(t) {
  return {
    password: yupString()
      .required(t('validation.required'))
      .min(PASSWORD_LENGTH.MIN, t('entities.validation.passwordMin', { min: PASSWORD_LENGTH.MIN }))
      .max(PASSWORD_LENGTH.MAX, t('entities.validation.maxChars', { max: PASSWORD_LENGTH.MAX })),
    passwordConfirmation: yupString()
      .required(t('validation.required'))
      .test('match', t('entities.validation.passwordMatch'), function matches(value) {
        return value === this.parent.password
      }),
  }
}

export function forgotPasswordSchema(t) {
  return yupObject({ email: emailField(t) })
}

export function resetPasswordSchema(t) {
  return yupObject(newPasswordShape(t))
}

export function changePasswordSchema(t) {
  return yupObject({
    currentPassword: yupString().required(t('validation.required')),
    ...newPasswordShape(t),
  })
}

// The national ID is optional at acceptance: blank keeps the administrator's entry (or leaves it for later).
export function invitationSchema(t, { termsRequired }) {
  return yupObject({
    ...newPasswordShape(t),
    nationalId: nationalIdRule(t, { required: false }),
    termsAccepted: termsRequired
      ? yupBoolean().oneOf([true], t('entities.validation.termsRequired'))
      : yupBoolean(),
  })
}

export function resetPayload(token, values) {
  return {
    token,
    password: values.password,
    passwordConfirmation: values.passwordConfirmation,
  }
}

export function changePasswordPayload(values) {
  return {
    currentPassword: values.currentPassword,
    password: values.password,
    passwordConfirmation: values.passwordConfirmation,
  }
}

export function invitationPayload(values, terms, nda = null) {
  return {
    password: values.password,
    passwordConfirmation: values.passwordConfirmation,
    termsVersionId: terms?.terms?.id ?? null,
    ...(nda ? { ndaVersionId: nda.id } : {}),
    ...(values.nationalId ? { nationalId: normalizeNationalId(values.nationalId) } : {}),
  }
}
