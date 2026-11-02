export const LOCALE_COOKIE = 'locale'

const ONE_YEAR_SECONDS = 60 * 60 * 24 * 365

export function readLocaleCookie() {
  const prefix = `${LOCALE_COOKIE}=`
  const entry = document.cookie
    .split(';')
    .map((part) => part.trim())
    .find((part) => part.startsWith(prefix))
  return entry ? decodeURIComponent(entry.slice(prefix.length)) : null
}

export function writeLocaleCookie(locale) {
  const secure = window.location.protocol === 'https:' ? '; Secure' : ''
  document.cookie = `${LOCALE_COOKIE}=${encodeURIComponent(locale)}; path=/; max-age=${ONE_YEAR_SECONDS}; SameSite=Lax${secure}`
}
