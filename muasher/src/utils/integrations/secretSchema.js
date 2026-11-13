import { mixed as yupMixed, string as yupString } from 'yup'
import { SECRET_PATTERNS } from '@/constants/integrations'
import { SECRET_ACTIONS } from './secretValue'

function enteredValue(secret) {
  return secret?.action === SECRET_ACTIONS.REPLACE ? String(secret.value ?? '') : ''
}

function lengthMessage(t, { min, max }) {
  return t('integrations.validation.lengthBetween', { min, max })
}

export function tokenRule(
  t,
  { min, max, prefix = '', printable = false, allowWhitespace = false },
) {
  return yupString()
    .trim()
    .test(
      'length',
      lengthMessage(t, { min, max }),
      (value) => !value || (value.length >= min && value.length <= max),
    )
    .test(
      'whitespace',
      t('integrations.validation.noWhitespace'),
      (value) => allowWhitespace || !value || SECRET_PATTERNS.NO_WHITESPACE.test(value),
    )
    .test(
      'printable',
      t('integrations.validation.printable'),
      (value) => !printable || !value || SECRET_PATTERNS.PRINTABLE.test(value),
    )
    .test(
      'prefix',
      t('integrations.validation.prefix', { prefix }),
      (value) => !prefix || !value || value.startsWith(prefix),
    )
}

export function secretRule(t, options, { required = () => false } = {}) {
  const rule = tokenRule(t, options)
  return yupMixed().test('secret', '', function validateSecret(secret) {
    const value = enteredValue(secret)
    if (!value)
      return required()
        ? this.createError({ message: t('integrations.validation.secretRequired') })
        : true
    try {
      rule.validateSync(value)
      return true
    } catch (failure) {
      return this.createError({ message: failure.message })
    }
  })
}

export function requiredText(t, max) {
  return yupString()
    .trim()
    .required(t('validation.required'))
    .max(max, t('integrations.validation.maxChars', { max }))
}

export function optionalText(t, max) {
  return yupString().trim().max(max, t('integrations.validation.maxChars', { max }))
}

export function choiceOf(t, values) {
  return yupString()
    .required(t('validation.required'))
    .oneOf(values.map(String), t('validation.required'))
}

export function integerBetween(t, { min, max }) {
  return yupString()
    .required(t('validation.required'))
    .test('integer', t('integrations.validation.integerBetween', { min, max }), (value) => {
      const number = Number(value)
      return Number.isInteger(number) && number >= min && number <= max
    })
}
