import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'
import { AA_TEXT, contrastOf } from './support/contrast'

const SHOTS = 'e2e/__screenshots__/brand'
const IGNORED_CONSOLE = /Failed to load resource|50\d|Service Unavailable/i

const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  mobile: { width: 390, height: 844 },
}

const ENTITY = {
  id: 5001,
  roles: ['entity'],
  entityId: 5001,
  name: { ar: 'أحمد بن سالم القحطاني', en: 'Ahmed Salem Al-Qahtani' },
}

const PUBLIC_SCREENS = [
  { key: 'home', path: '/', ready: '.home-hero__title', settled: '.home-stats__item' },
  { key: 'login', path: '/auth/login', ready: '.auth-layout__brand', settled: '.auth-layout__card form' },
  { key: 'register', path: '/auth/register', ready: '.auth-layout__brand', settled: '.auth-layout__card form' },
]

const ENTITY_SCREENS = [
  { key: 'dashboard', path: '/dashboard', ready: 'main h1', settled: '.journey-station' },
  { key: 'self', path: '/assessment/self', ready: 'main h1', settled: '.self-summary' },
  { key: 'accredited', path: '/assessment/accredited', ready: 'main h1', settled: '.pricing-breakdown__row' },
]

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function dirOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'ltr' : 'rtl'
}

function suffixOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en-ltr' : 'ar-rtl'
}

function trackConsole(page) {
  const errors = []
  page.on('console', (message) => {
    const ignored = IGNORED_CONSOLE.test(message.text())
    if (message.type() === 'error' && !ignored) errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))
  return errors
}

async function openScreen(page, screen) {
  await page.goto(screen.path)
  await expect(page.locator(screen.ready).first()).toBeVisible({ timeout: 15000 })
  await expect(page.locator(screen.settled).first()).toBeVisible({ timeout: 15000 })
  await page.evaluate(() => document.fonts.ready.then(() => true))
}

for (const theme of ['dark', 'light']) {
  for (const [viewportName, viewport] of Object.entries(VIEWPORTS)) {
    test.describe(`brand identity — ${theme} ${viewportName}`, () => {
      test.use({ viewport })

      for (const screen of PUBLIC_SCREENS) {
        test(`public ${screen.key}`, async ({ page }, testInfo) => {
          const errors = trackConsole(page)
          await seedPreferences(page, { locale: localeOf(testInfo), theme })
          await openScreen(page, screen)

          await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
          await expect(page.locator('html')).toHaveAttribute('dir', dirOf(testInfo))
          await expect(page.locator('.brand-pattern').first()).toHaveAttribute('aria-hidden', 'true')
          expect(await contrastOf(page, '.btn--primary')).toBeGreaterThanOrEqual(AA_TEXT)

          await page.screenshot({ path: `${SHOTS}/${screen.key}-${theme}-${viewportName}-${suffixOf(testInfo)}.png` })
          expect(errors).toEqual([])
        })
      }

      for (const screen of ENTITY_SCREENS) {
        test(`entity ${screen.key}`, async ({ page }, testInfo) => {
          const errors = trackConsole(page)
          await seedSignedIn(page, { ...ENTITY, locale: localeOf(testInfo) }, { locale: localeOf(testInfo), theme })
          await openScreen(page, screen)

          await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
          expect(await contrastOf(page, screen.ready)).toBeGreaterThanOrEqual(AA_TEXT)

          await page.screenshot({ path: `${SHOTS}/${screen.key}-${theme}-${viewportName}-${suffixOf(testInfo)}.png` })
          expect(errors).toEqual([])
        })
      }
    })
  }
}

test('home hero and footer use the official on-dark logo, patterns and featured indicators', async ({ page }, testInfo) => {
  await seedPreferences(page, { locale: localeOf(testInfo), theme: 'light' })
  await openScreen(page, PUBLIC_SCREENS[0])

  await expect(page.locator('.home-hero.brand-surface .brand-pattern--lattice')).toHaveAttribute('aria-hidden', 'true')
  await expect(page.locator('.home-hero__mark')).toHaveAttribute('alt', '')
  await expect(page.locator('.home-stats__item').first()).toBeVisible()
  expect(await contrastOf(page, '.home-stats__value')).toBeGreaterThanOrEqual(AA_TEXT)
  expect(await contrastOf(page, '.home-hero__title')).toBeGreaterThanOrEqual(AA_TEXT)
  await expect(page.locator('.home-path--award .home-path__availability')).toBeVisible()
  await expect(page.locator('.navbar__logo')).toHaveAttribute('src', /moashir-logo-on-light/)

  const footer = page.locator('.footer')
  await footer.scrollIntoViewIfNeeded()
  await expect(page.locator('.footer__logo')).toHaveAttribute('src', /moashir-logo-on-dark/)
  expect(await contrastOf(page, '.footer__link')).toBeGreaterThanOrEqual(AA_TEXT)
  await footer.screenshot({ path: `${SHOTS}/footer-light-${suffixOf(testInfo)}.png` })
})

test('sign-in brand panel shows the on-dark logo over the lattice pattern', async ({ page }, testInfo) => {
  await seedPreferences(page, { locale: localeOf(testInfo), theme: 'light' })
  await openScreen(page, PUBLIC_SCREENS[1])

  await expect(page.locator('.auth-layout__logo')).toHaveAttribute('src', /moashir-logo-on-dark/)
  await expect(page.locator('.auth-layout__brand .brand-pattern--lattice')).toHaveAttribute('aria-hidden', 'true')
  expect(await contrastOf(page, '.auth-value-panel__heading')).toBeGreaterThanOrEqual(AA_TEXT)
})

test('the favicon and app icons follow the identity', async ({ page }) => {
  await page.goto('/')
  await expect(page.locator('link[rel="icon"][type="image/svg+xml"]')).toHaveAttribute('href', '/favicon.svg')
  await expect(page.locator('meta[name="theme-color"]')).toHaveAttribute('content', '#0a2325')
  const favicon = await page.request.get('/favicon.svg')
  expect(favicon.ok()).toBe(true)
  expect(await favicon.text()).toContain('#98d8d3')
  const manifest = await (await page.request.get('/site.webmanifest')).json()
  expect(manifest.theme_color).toBe('#0a2325')
})
