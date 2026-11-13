import { object as yupObject, string as yupString } from 'yup'
import { MANUAL_SETTLE_RULES } from '@/constants/finance'

export function manualSettleSchema(t) {
  return yupObject({
    status: yupString()
      .required(t('validation.required'))
      .oneOf(MANUAL_SETTLE_RULES.STATUSES, t('validation.required')),
    providerPaymentId: yupString()
      .trim()
      .max(
        MANUAL_SETTLE_RULES.PROVIDER_ID_MAX,
        t('finance.validation.maxChars', { max: MANUAL_SETTLE_RULES.PROVIDER_ID_MAX }),
      )
      .test(
        'pattern',
        t('finance.settle.providerIdPattern'),
        (value) => !value || MANUAL_SETTLE_RULES.PROVIDER_ID_PATTERN.test(value),
      ),
    reason: yupString()
      .trim()
      .required(t('validation.required'))
      .min(
        MANUAL_SETTLE_RULES.REASON_MIN,
        t('validation.minChars', { min: MANUAL_SETTLE_RULES.REASON_MIN }),
      )
      .max(
        MANUAL_SETTLE_RULES.REASON_MAX,
        t('finance.validation.maxChars', { max: MANUAL_SETTLE_RULES.REASON_MAX }),
      ),
  })
}

export function manualSettlePayload(values) {
  const providerPaymentId = values.providerPaymentId?.trim()
  return {
    status: values.status,
    ...(providerPaymentId ? { providerPaymentId } : {}),
    reason: values.reason.trim(),
  }
}
