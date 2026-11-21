import { mixed as yupMixed, object as yupObject, string as yupString } from 'yup'
import { REFUND_RULES } from '@/constants/finance'

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/
const BYTES_PER_MB = 1024 * 1024

export function riyadhDate(value = new Date()) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(new Date(value))
}

function extensionOf(file) {
  return String(file?.name ?? '')
    .split('.')
    .pop()
    .toLowerCase()
}

export function evidenceProblem(file) {
  if (!(file instanceof Blob)) return 'required'
  if (!REFUND_RULES.EVIDENCE_EXTENSIONS.includes(extensionOf(file))) return 'type'
  if (file.size > REFUND_RULES.EVIDENCE_MAX_MB * BYTES_PER_MB) return 'size'
  return null
}

function boundedText(t, min, max) {
  return yupString()
    .trim()
    .required(t('validation.required'))
    .min(min, t('validation.minChars', { min }))
    .max(max, t('finance.validation.maxChars', { max }))
}

function refundDate(t, paidOn) {
  return yupString()
    .required(t('validation.required'))
    .matches(DATE_PATTERN, t('finance.validation.date'))
    .test(
      'not-future',
      t('finance.validation.dateFuture'),
      (value) => !value || value <= riyadhDate(),
    )
    .test(
      'after-payment',
      t('finance.validation.dateBeforePayment'),
      (value) => !value || !paidOn || value >= paidOn,
    )
}

function evidence(t) {
  return yupMixed().test('evidence', (file, context) => {
    const problem = evidenceProblem(file)
    if (!problem) return true
    const messages = {
      required: t('finance.refund.evidence.required'),
      type: t('finance.refund.evidence.type'),
      size: t('finance.refund.evidence.size', { max: REFUND_RULES.EVIDENCE_MAX_MB }),
    }
    return context.createError({ message: messages[problem] })
  })
}

export function refundSchema(t, { total, paidOn }) {
  return yupObject({
    amount: yupString()
      .required(t('validation.required'))
      .oneOf([total], t('finance.refund.amountFixed', { total })),
    refundedOn: refundDate(t, paidOn),
    reason: boundedText(t, REFUND_RULES.REASON_MIN, REFUND_RULES.REASON_MAX),
    transferReference: boundedText(
      t,
      REFUND_RULES.TRANSFER_REFERENCE_MIN,
      REFUND_RULES.TRANSFER_REFERENCE_MAX,
    ).matches(REFUND_RULES.TRANSFER_REFERENCE_PATTERN, t('finance.validation.transferReference')),
    evidence: evidence(t),
  })
}

export function refundFormData(values) {
  const form = new FormData()
  form.append('amount', values.amount)
  form.append('refundedOn', values.refundedOn)
  form.append('reason', values.reason.trim())
  form.append('transferReference', values.transferReference.trim())
  form.append('evidence', values.evidence)
  return form
}
