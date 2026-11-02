import { number as yupNumber, object as yupObject, string as yupString } from 'yup'
import { toCamelKey } from '@/api/caseMapping'
import { FINANCE_SETTINGS_RULES } from '@/constants/finance'
import { toWesternDigits } from '@/utils/numerals'

export const FINANCE_FORM_FIELDS = ['accreditedPrice', 'bagPrice', 'vatRate', 'orderValidityMinutes']

export function editableFieldsOf(settings) {
  return new Set((settings?.editableFields ?? []).map(toCamelKey))
}

export function normalizedDecimal(value) {
  const text = toWesternDigits(value)
  if (!text) return ''
  const [whole, fraction = ''] = text.split('.')
  return `${Number(whole)}.${fraction.padEnd(2, '0')}`
}

export function formValuesOf(settings) {
  return {
    accreditedPrice: settings.accreditedPrice ?? '',
    bagPrice: settings.bagPrice ?? '',
    vatRate: settings.vatRate ?? '',
    orderValidityMinutes: String(settings.orderValidityMinutes ?? ''),
  }
}

function priceRule(t) {
  return yupString()
    .transform((value) => toWesternDigits(value))
    .test('format', t('finance.settings.validation.price'), (value) => !value || FINANCE_SETTINGS_RULES.PRICE_INPUT_PATTERN.test(value))
    .test('positive', t('finance.settings.validation.pricePositive'), (value) => !value || Number(value) > 0)
}

export function financeSettingsSchema(t, limits, editable) {
  const vat = yupString()
    .transform((value) => toWesternDigits(value))
    .required(t('validation.required'))
    .test('format', t('finance.settings.validation.vat'), (value) => {
      return FINANCE_SETTINGS_RULES.VAT_INPUT_PATTERN.test(value ?? '') && Number(value) <= FINANCE_SETTINGS_RULES.VAT_MAX
    })
  const validity = yupNumber()
    .transform((value, original) => (original === '' ? undefined : Number(toWesternDigits(original))))
    .typeError(t('finance.settings.validation.validity', limits))
    .required(t('validation.required'))
    .integer(t('finance.settings.validation.validity', limits))
    .min(limits.min, t('finance.settings.validation.validity', limits))
    .max(limits.max, t('finance.settings.validation.validity', limits))
  const rules = { accreditedPrice: priceRule(t), bagPrice: priceRule(t), vatRate: vat, orderValidityMinutes: validity }
  return yupObject(Object.fromEntries(FINANCE_FORM_FIELDS.filter((field) => editable.has(field)).map((field) => [field, rules[field]])))
}

function submittedValue(field, value) {
  if (field === 'orderValidityMinutes') return Number(toWesternDigits(value))
  return normalizedDecimal(value)
}

export function changedSettings(original, values, editable) {
  const initial = formValuesOf(original)
  return Object.fromEntries(
    FINANCE_FORM_FIELDS.filter((field) => editable.has(field))
      .filter((field) => toWesternDigits(values[field]) !== '' && submittedValue(field, values[field]) !== submittedValue(field, initial[field]))
      .map((field) => [field, submittedValue(field, values[field])]),
  )
}
