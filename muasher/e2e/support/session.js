import config from '../../playwright.config.js'

export const MOCK_SESSION_COOKIE = 'moashir_mock_session'
const APP_URL = config.use.baseURL

function descriptorOf({ id = 7000, roles = [], entityId = null, name, committeeCycles, locale, superAdmin }) {
  return {
    id,
    roles,
    entityId,
    ...(name ? { name } : {}),
    ...(committeeCycles ? { committeeCycles } : {}),
    ...(locale ? { locale } : {}),
    ...(superAdmin ? { superAdmin } : {}),
  }
}

export function mockSessionCookie(user) {
  return {
    name: MOCK_SESSION_COOKIE,
    value: encodeURIComponent(JSON.stringify(descriptorOf(user))),
    url: APP_URL,
  }
}

export function seedMockSession(page, user) {
  return page.context().addCookies([mockSessionCookie(user)])
}

export const LOCALE_COOKIE = 'locale'

export function seedLocale(page, locale) {
  if (!locale) return Promise.resolve()
  return page.context().addCookies([{ name: LOCALE_COOKIE, value: locale, url: APP_URL }])
}

export async function seedPreferences(page, { locale, theme } = {}) {
  await seedLocale(page, locale)
  if (theme) await page.addInitScript((th) => localStorage.setItem('muasher.theme', th), theme)
}

export async function seedSignedIn(page, user, preferences = {}) {
  await seedMockSession(page, { ...user, locale: user.locale ?? preferences.locale })
  await seedPreferences(page, preferences)
}
