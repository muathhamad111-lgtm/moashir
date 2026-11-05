import {
  array as yupArray,
  boolean as yupBoolean,
  object as yupObject,
  string as yupString,
} from 'yup'
import {
  INTEGRATION_LIMITS,
  INTEGRATION_MODES,
  NO_ACTIVE_PROVIDER,
  SECRET_PATTERNS,
  SMS_PROVIDERS,
} from '@/constants/integrations'
import { choiceOf, optionalText, requiredText, secretRule } from './secretSchema'

function whenActive(provider, rule, t) {
  return rule.test(
    'requiredWhenActive',
    t('integrations.sms.validation.requiredForActive'),
    function check(value) {
      const active = this.from?.[2]?.value?.activeProvider
      return active !== provider || Boolean(String(value ?? '').trim())
    },
  )
}

function msegatSenderRule(t) {
  return whenActive(
    SMS_PROVIDERS.MSEGAT,
    optionalText(t, INTEGRATION_LIMITS.MSEGAT_SENDER_MAX).test(
      'pattern',
      t('integrations.sms.validation.senderPattern'),
      (value) => !value || SECRET_PATTERNS.SENDER_NAME.test(value),
    ),
    t,
  )
}

function saudiMobile(t) {
  return yupString().matches(SECRET_PATTERNS.SAUDI_MOBILE, t('integrations.validation.saudiMobile'))
}

function testRecipientsRule(t) {
  const max = INTEGRATION_LIMITS.TEST_RECIPIENTS_MAX
  return yupArray()
    .of(saudiMobile(t))
    .max(max, t('integrations.sms.validation.recipientsMax', { max }))
}

function templateBody(t) {
  const max = INTEGRATION_LIMITS.SMS_TEMPLATE_MAX_CHARS
  return yupObject({ ar: requiredText(t, max), en: requiredText(t, max) })
}

export function smsSchema(t) {
  return yupObject({
    activeProvider: choiceOf(t, [NO_ACTIVE_PROVIDER, ...Object.values(SMS_PROVIDERS)]),
    mode: choiceOf(t, Object.values(INTEGRATION_MODES)),
    testRecipients: testRecipientsRule(t),
    providers: yupObject({
      taqnyat: yupObject({
        bearerToken: secretRule(t, { min: 20, max: 512 }),
        senderName: whenActive(
          SMS_PROVIDERS.TAQNYAT,
          optionalText(t, INTEGRATION_LIMITS.TAQNYAT_SENDER_MAX),
          t,
        ),
      }),
      msegat: yupObject({
        username: whenActive(
          SMS_PROVIDERS.MSEGAT,
          optionalText(t, INTEGRATION_LIMITS.MSEGAT_USERNAME_MAX),
          t,
        ),
        apiKey: secretRule(t, { min: 16, max: 256 }),
        senderName: msegatSenderRule(t),
        templateRestricted: yupBoolean(),
      }),
    }),
    templates: yupArray().of(yupObject({ key: yupString(), body: templateBody(t) })),
  })
}

export function testRecipientSchema(t) {
  return yupObject({
    recipient: saudiMobile(t).trim().required(t('validation.required')),
    provider: yupString().required(t('validation.required')),
  })
}
