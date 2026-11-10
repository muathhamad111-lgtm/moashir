import { object, string } from 'yup'
import { PRICE_AVAILABILITY, PRICE_AVAILABILITY_VALUES } from '@/constants/prices'
import { FINANCE_SETTINGS_RULES } from '@/constants/finance'
import { toWesternDigits } from '@/utils/numerals'
const DECIMALS = 2

export function priceString(value) {
  const [whole, fraction = ''] = toWesternDigits(String(value)).trim().split('.')
  return `${Number(whole)}.${fraction.padEnd(DECIMALS, '0')}`
}

export function pathPriceValues(row) {
  return {
    priceBeforeVat: row.inForce?.priceBeforeVat ?? '',
    availability: row.inForce?.availability ?? PRICE_AVAILABILITY.AVAILABLE,
    effectiveFrom: '',
  }
}

function withinMax(value, max) {
  return !max || Number(priceString(value)) <= Number(max)
}

function withinRange(value, { effectiveFromMin, effectiveFromMax }) {
  if (!value) return true
  return value >= effectiveFromMin && value <= effectiveFromMax
}

export function pathPriceSchema(t, limits) {
  return object({
    priceBeforeVat: string()
      .transform((value) => toWesternDigits(value ?? '').trim())
      .required(t('validation.required'))
      .matches(FINANCE_SETTINGS_RULES.PRICE_INPUT_PATTERN, t('settings.prices.validation.price'))
      .test('max', t('settings.prices.validation.max', { max: limits.priceMax }), (value) =>
        value ? withinMax(value, limits.priceMax) : true,
      ),
    availability: string()
      .oneOf([...PRICE_AVAILABILITY_VALUES])
      .required(t('validation.required')),
    effectiveFrom: string().test(
      'range',
      t('settings.prices.validation.effectiveFrom', {
        min: limits.effectiveFromMin,
        max: limits.effectiveFromMax,
      }),
      (value) => withinRange(value, limits),
    ),
  })
}

export function pathPricePayload(values, row) {
  return {
    priceBeforeVat: priceString(values.priceBeforeVat),
    availability: values.availability,
    effectiveFrom: values.effectiveFrom || null,
    baseVersionId: row.inForce?.id ?? null,
  }
}
