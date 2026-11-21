import { object as yupObject, string as yupString } from 'yup'
import { IDENTIFIER_MAX_LENGTH, SIGN_IN_IDENTIFIERS } from '@/constants/signIn'
import { PASSWORD_LENGTH } from '@/constants/entityEnums'
import { identifierForRequest, identifierTypeOf } from './signInIdentifier'

export const SIGN_IN_DEFAULTS = Object.freeze({ identifier: '', password: '' })

export const SIGN_IN_CODE_DEFAULTS = Object.freeze({ identifier: '' })

function identifierSchema(t) {
  return yupString()
    .trim()
    .required(t('validation.required'))
    .max(IDENTIFIER_MAX_LENGTH, t('entities.validation.maxChars', { max: IDENTIFIER_MAX_LENGTH }))
    .test('identifier', t('auth.login.validation.identifier'), (value) =>
      Boolean(identifierTypeOf(value)),
    )
}

export function signInCodeSchema(t) {
  return yupObject({ identifier: identifierSchema(t) })
}

export function signInSchema(t) {
  return yupObject({
    identifier: identifierSchema(t),
    password: yupString()
      .required(t('validation.required'))
      .max(PASSWORD_LENGTH.MAX, t('entities.validation.maxChars', { max: PASSWORD_LENGTH.MAX })),
  })
}

export function signInCredentials(values) {
  return { identifier: identifierForRequest(values.identifier), password: values.password }
}

export function signInCodeRequestOf(values) {
  return { identifier: identifierForRequest(values.identifier) }
}

export function signInEmailOf(identifier) {
  return identifierTypeOf(identifier) === SIGN_IN_IDENTIFIERS.EMAIL ? identifier : ''
}
