import { createI18n } from 'vue-i18n'
import { DEFAULT_LOCALE, FALLBACK_LOCALE, SUPPORTED_LOCALES } from '@/constants/locales'
import { readLocaleCookie } from '@/utils/localeCookie'
import ar from './locales/ar.json'
import en from './locales/en.json'

function initialLocale() {
  const stored = readLocaleCookie()
  return SUPPORTED_LOCALES.includes(stored) ? stored : DEFAULT_LOCALE
}

const latinNumberFormats = {
  default: { style: 'decimal', useGrouping: true, numberingSystem: 'latn' },
  decimal: { style: 'decimal', useGrouping: true, numberingSystem: 'latn' },
  integer: {
    style: 'decimal',
    useGrouping: true,
    maximumFractionDigits: 0,
    numberingSystem: 'latn',
  },
  percent: { style: 'percent', maximumFractionDigits: 1, numberingSystem: 'latn' },
  money: {
    style: 'decimal',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
    useGrouping: true,
    numberingSystem: 'latn',
  },
  currency: {
    style: 'currency',
    currency: 'SAR',
    maximumFractionDigits: 0,
    numberingSystem: 'latn',
  },
  id: {
    style: 'decimal',
    useGrouping: false,
    maximumFractionDigits: 0,
    numberingSystem: 'latn',
  },
}

export const i18n = createI18n({
  legacy: false,
  locale: initialLocale(),
  fallbackLocale: FALLBACK_LOCALE,
  messages: { ar, en },
  numberFormats: {
    ar: latinNumberFormats,
    en: latinNumberFormats,
  },
})
