import { boolean as yupBoolean, object as yupObject, string as yupString } from 'yup'
import {
  INTEGRATION_LIMITS,
  INTEGRATION_MODES,
  SECRET_PATTERNS,
  SMTP_ENCRYPTIONS,
  SMTP_PORTS,
} from '@/constants/integrations'
import { choiceOf, integerBetween, requiredText, secretRule } from './secretSchema'

const TEXT_MAX = 255
const NAME_MAX = 100

function hostname(t, { required }) {
  const base = yupString().trim()
  const rule = required ? base.required(t('validation.required')) : base
  return rule.test(
    'hostname',
    t('integrations.validation.hostname'),
    (value) => !value || SECRET_PATTERNS.HOSTNAME.test(value),
  )
}

function emailAddress(t, { required }) {
  const base = yupString().trim().email(t('validation.email'))
  return required ? base.required(t('validation.required')) : base
}

function testAddressRule(t) {
  return emailAddress(t, { required: false }).test(
    'requiredInTest',
    t('integrations.email.validation.testAddressRequired'),
    function check(value) {
      return this.parent.mode !== INTEGRATION_MODES.TEST || Boolean(value)
    },
  )
}

export function emailSchema(t, { passwordRequired = () => false } = {}) {
  const timeout = INTEGRATION_LIMITS.EMAIL_TIMEOUT
  return yupObject({
    active: yupBoolean(),
    mode: choiceOf(t, Object.values(INTEGRATION_MODES)),
    host: hostname(t, { required: true }),
    port: choiceOf(t, SMTP_PORTS),
    encryption: choiceOf(t, SMTP_ENCRYPTIONS),
    username: requiredText(t, TEXT_MAX),
    password: secretRule(
      t,
      { min: 1, max: TEXT_MAX, allowWhitespace: true },
      { required: passwordRequired },
    ),
    fromAddress: emailAddress(t, { required: true }),
    fromName: yupObject({ ar: requiredText(t, NAME_MAX), en: requiredText(t, NAME_MAX) }),
    replyTo: emailAddress(t, { required: false }),
    ehloDomain: hostname(t, { required: false }),
    timeoutSeconds: integerBetween(t, { min: timeout.MIN, max: timeout.MAX }),
    testAddress: testAddressRule(t),
  })
}

export function testEmailSchema(t) {
  return yupObject({ recipient: emailAddress(t, { required: true }) })
}
