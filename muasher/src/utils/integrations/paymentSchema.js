import {
  array as yupArray,
  boolean as yupBoolean,
  object as yupObject,
  string as yupString,
} from 'yup'
import {
  CARD_NETWORKS,
  INTEGRATION_LIMITS,
  INTEGRATION_MODES,
  PAYMENT_METHODS,
} from '@/constants/integrations'
import { choiceOf, requiredText, secretRule, tokenRule } from './secretSchema'

const KEY_LENGTH = { min: 16, max: 256 }
const WEBHOOK_SECRET = { min: 32, max: 128, printable: true }

function keyPair(t, mode) {
  return yupObject({
    publishableKey: tokenRule(t, { ...KEY_LENGTH, prefix: `pk_${mode}_` }),
    secretKey: secretRule(t, { ...KEY_LENGTH, prefix: `sk_${mode}_` }),
    webhookSecret: secretRule(t, WEBHOOK_SECRET),
  })
}

function methodsRule(t) {
  return yupArray()
    .of(yupString())
    .test(
      'applePay',
      t('integrations.payment.validation.applePayNeedsConfirmation'),
      function check(methods = []) {
        return (
          !methods.includes(PAYMENT_METHODS.APPLE_PAY) ||
          this.parent.confirmations?.applePayDomain === true
        )
      },
    )
    .test(
      'stcPay',
      t('integrations.payment.validation.stcPayNeedsConfirmation'),
      function check(methods = []) {
        return (
          !methods.includes(PAYMENT_METHODS.STC_PAY) ||
          this.parent.confirmations?.stcpayActivation === true
        )
      },
    )
}

export function paymentSchema(t) {
  return yupObject({
    mode: choiceOf(t, Object.values(INTEGRATION_MODES)),
    active: yupBoolean(),
    test: keyPair(t, INTEGRATION_MODES.TEST),
    live: keyPair(t, INTEGRATION_MODES.LIVE),
    methods: methodsRule(t),
    supportedNetworks: yupArray()
      .of(yupString().oneOf(CARD_NETWORKS))
      .min(1, t('integrations.payment.validation.networkRequired')),
    applePayLabel: yupObject({
      ar: requiredText(t, INTEGRATION_LIMITS.APPLE_PAY_LABEL_MAX),
      en: requiredText(t, INTEGRATION_LIMITS.APPLE_PAY_LABEL_MAX),
    }),
    confirmations: yupObject({ applePayDomain: yupBoolean(), stcpayActivation: yupBoolean() }),
    linkedPaths: yupArray().of(yupString()),
  })
}
