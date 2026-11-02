import { i18n } from '@/i18n'
import { SUPPORTED_LOCALES } from '@/constants/locales'
import { writeLocaleCookie } from '@/utils/localeCookie'

export function isSupportedLocale(locale) {
  return SUPPORTED_LOCALES.includes(locale)
}

export function applyLocale(locale) {
  if (!isSupportedLocale(locale)) return false
  i18n.global.locale.value = locale
  writeLocaleCookie(locale)
  return true
}
