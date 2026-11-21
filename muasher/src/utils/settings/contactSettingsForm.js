import { number, object } from 'yup'
import { toWesternDigits } from '@/utils/numerals'

function monthsOf(value) {
  const text = toWesternDigits(String(value ?? '')).trim()
  return text === '' ? null : Number(text)
}

export function contactSettingsSchema(t, settings) {
  const { min, max } = settings?.retentionLimits ?? { min: 1, max: 120 }
  return object({
    retentionMonths: number()
      .transform((_, original) => monthsOf(original))
      .typeError(t('settings.contactMessages.validation.whole', { min, max }))
      .required(t('validation.required'))
      .integer(t('settings.contactMessages.validation.whole', { min, max }))
      .min(min, t('settings.contactMessages.validation.whole', { min, max }))
      .max(max, t('settings.contactMessages.validation.whole', { min, max })),
  })
}

export function contactSettingsValues(settings) {
  return {
    retentionMonths:
      settings?.retentionMonths === null ? '' : String(settings?.retentionMonths ?? ''),
  }
}

export function changedContactSettings(settings, values) {
  const months = monthsOf(values.retentionMonths)
  return months === settings?.retentionMonths ? {} : { retentionMonths: months }
}
