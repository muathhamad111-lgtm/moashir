import { boolean as yupBoolean, object as yupObject, string as yupString } from 'yup'
import { PASSWORD_LENGTH } from '@/constants/entityEnums'

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
  return yupObject({ email: emailField(t), ...newPasswordShape(t) })
}

export function invitationSchema(t, { termsRequired }) {
  return yupObject({
    ...newPasswordShape(t),
    termsAccepted: termsRequired
      ? yupBoolean().oneOf([true], t('entities.validation.termsRequired'))
      : yupBoolean(),
  })
}

export function resetPayload(token, values) {
  return {
    token,
    email: values.email.trim(),
    password: values.password,
    passwordConfirmation: values.passwordConfirmation,
  }
}

export function invitationPayload(values, terms) {
  return {
    password: values.password,
    passwordConfirmation: values.passwordConfirmation,
    termsVersionId: terms?.terms?.id ?? null,
  }
}
