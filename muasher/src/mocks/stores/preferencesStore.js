import { readCookie } from '../contract/cookies'
import { DEFAULT_LOCALE, SUPPORTED_LOCALES } from '@/constants/locales'
import { LOCALE_COOKIE } from '@/utils/localeCookie'

const localesByAccount = new Map()

export function rememberLocale(accountId, locale) {
  localesByAccount.set(String(accountId), locale)
}

export function profileLocaleOf(accountId) {
  const remembered = localesByAccount.get(String(accountId))
  if (remembered) return remembered
  const guestLocale = readCookie(LOCALE_COOKIE)
  return SUPPORTED_LOCALES.includes(guestLocale) ? guestLocale : DEFAULT_LOCALE
}

export function forgetLocales() {
  localesByAccount.clear()
}
