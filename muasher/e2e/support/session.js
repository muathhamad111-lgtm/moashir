import config from '../../playwright.config.js'

export const MOCK_SESSION_COOKIE = 'moashir_mock_session'
const APP_URL = config.use.baseURL

function descriptorOf({ id = 7000, roles = [], entityId = null, name, locale, superAdmin }) {
  return {
    id,
    roles,
    entityId,
    ...(name ? { name } : {}),
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
export const MOCK_SCENARIO_COOKIE = 'moashir_mock_scenario'

export function seedScenario(page, scenario) {
  return page.context().addCookies([{ name: MOCK_SCENARIO_COOKIE, value: scenario, url: APP_URL }])
}

export function seedLocale(page, locale) {
  if (!locale) return Promise.resolve()
  return page.context().addCookies([{ name: LOCALE_COOKIE, value: locale, url: APP_URL }])
}

export async function seedPreferences(page, { locale, theme } = {}) {
  await seedLocale(page, locale)
  if (theme) await page.addInitScript((th) => localStorage.setItem('muasher.theme', th), theme)
}

const LEVEL_ACKNOWLEDGED_KEY = 'muasher.level.acknowledged'
const MOCK_LEVEL_CERTIFICATES = {
  5001: '2c9e7a41-0b5d-4f63-8e21-7d4a00009102',
  6001: '2c9e7a41-0b5d-4f63-8e21-7d4a00009104',
  6002: '2c9e7a41-0b5d-4f63-8e21-7d4a00009110',
  6004: '2c9e7a41-0b5d-4f63-8e21-7d4a00009113',
  6005: '2c9e7a41-0b5d-4f63-8e21-7d4a00009111',
  7005: '2c9e7a41-0b5d-4f63-8e21-7d4a00009112',
}

function acknowledgeLevel(page, user) {
  const certificateId = MOCK_LEVEL_CERTIFICATES[user.entityId]
  if (!certificateId) return Promise.resolve()
  return page.addInitScript(
    ([key, value]) => window.localStorage.setItem(key, value),
    [`${LEVEL_ACKNOWLEDGED_KEY}.${user.id ?? 7000}`, certificateId],
  )
}

export async function seedSignedIn(page, user, preferences = {}) {
  await seedMockSession(page, { ...user, locale: user.locale ?? preferences.locale })
  await seedPreferences(page, preferences)
  if (!preferences.showLevelAchieved) await acknowledgeLevel(page, user)
}
