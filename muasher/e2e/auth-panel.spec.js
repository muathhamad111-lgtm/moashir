import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedPreferences } from './support/session'
import { settleFonts } from './support/screenshots'
import { AA_TEXT, contrastOf } from './support/contrast'

const SHOTS = 'e2e/__screenshots__/auth-panel'
const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  mobile: { width: 390, height: 844 },
}
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const COPY = {
  ar: {
    heading: 'قياسٌ عادل، وتحسينٌ مستمر',
    titles: ['معايير معلنة وموحّدة', 'تقييم مستقل ومحايد', 'نتائج تقود إلى التحسين'],
    footnote: 'بياناتك محمية وفق ضوابط الهيئة الوطنية للأمن السيبراني.',
    subtitle: 'ادخل إلى حسابك في منصة مؤشر.',
  },
  en: {
    heading: 'Fair measurement, continuous improvement',
    titles: [
      'Published, unified standards',
      'Independent, impartial review',
      'Results that drive improvement',
    ],
    footnote: 'Your data is protected in line with National Cybersecurity Authority controls.',
    subtitle: 'Sign in to your Moashir account.',
  },
}
const AUTH_PAGES = [
  { path: '/auth/login', ready: '[name="identifier"]' },
  { path: '/auth/register', ready: '.auth-layout__card form' },
  { path: '/auth/forgot-password', ready: '[name="email"]' },
  { path: '/auth/reset-password/a1b2c3d4e5f6a7b8', ready: '[name="email"]' },
  { path: '/auth/invitations/invite-sarah-whitfield-2026', ready: '[name="password"]' },
]
const SCREENS = [
  { name: 'login', path: '/auth/login', fullPage: false },
  { name: 'register', path: '/auth/register', fullPage: true },
]
const PANEL_TEXT = [
  '.auth-value-panel__heading',
  '.auth-value-card__title',
  '.auth-value-card__body',
  '.auth-value-panel__footnote',
]

mkdirSync(SHOTS, { recursive: true })

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function localeOf(testInfo) {
  return isEn(testInfo) ? 'en' : 'ar'
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

async function open(page, path, ready = '.auth-layout__card form') {
  await page.goto(path)
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator(ready).first()).toBeVisible({ timeout: 10000 })
  await expect(page.locator('aside.auth-layout__brand h2')).toBeVisible()
}

async function noHorizontalOverflow(page) {
  await expect
    .poll(() =>
      page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
      ),
    )
    .toBeLessThanOrEqual(1)
}

function boxOf(page, selector) {
  return page.locator(selector).first().boundingBox()
}

test.describe('auth brand panel — semantics', () => {
  test.use({ viewport: VIEWPORTS.desktop })

  test('the form comes first and the aside is named by its h2', async ({ page }, testInfo) => {
    const copy = COPY[localeOf(testInfo)]
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await open(page, '/auth/login')

    const order = await page.evaluate(() => {
      const main = document.querySelector('main')
      const aside = document.querySelector('aside.auth-layout__brand')
      return main.compareDocumentPosition(aside) & Node.DOCUMENT_POSITION_FOLLOWING
    })
    expect(order).toBeTruthy()
    await expect(page.locator('h1')).toHaveCount(1)
    await expect(page.locator('main h1')).toHaveCount(1)

    const aside = page.getByRole('complementary', { name: copy.heading })
    await expect(aside).toBeVisible()
    await expect(aside.getByRole('heading', { level: 2 })).toHaveText(copy.heading)
    await expect(aside.getByRole('heading', { level: 3 })).toHaveText(copy.titles)
    await expect(aside.getByRole('list').getByRole('listitem')).toHaveCount(3)
    await expect(page.locator('.auth-value-panel__footnote')).toHaveText(copy.footnote)
    await expect(page.locator('.auth-layout__tagline, .illustration-slot')).toHaveCount(0)
  })

  test('the panel icons are the approved decorative glyphs', async ({ page }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await open(page, '/auth/login')

    const icons = page.locator('aside.auth-layout__brand svg.app-icon')
    await expect(icons).toHaveCount(4)
    const names = await icons.evaluateAll((nodes) => nodes.map((node) => node.dataset.icon))
    expect(names).toEqual(['list-check', 'user-check', 'chart-line', 'lock'])
    for (const icon of await icons.all()) {
      await expect(icon).toHaveAttribute('aria-hidden', 'true')
      await expect(icon).toHaveAttribute('focusable', 'false')
    }
  })

  test('the login subtitle speaks to every audience', async ({ page }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await open(page, '/auth/login')
    await expect(page.locator('.auth-form__subtitle')).toHaveText(COPY[localeOf(testInfo)].subtitle)
  })

  test('every auth page shares the same panel', async ({ page }, testInfo) => {
    const copy = COPY[localeOf(testInfo)]
    await seedPreferences(page, { locale: localeOf(testInfo) })
    for (const { path, ready } of AUTH_PAGES) {
      await open(page, path, ready)
      await expect(page.locator('aside.auth-layout__brand h2')).toHaveText(copy.heading)
      await expect(page.locator('.auth-value-card')).toHaveCount(3)
      await expect(page.locator('h1')).toHaveCount(1)
    }
  })

  test('panel text keeps AA contrast over the brand surface', async ({ page }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/auth/login')
    for (const selector of PANEL_TEXT) {
      expect(await contrastOf(page, selector)).toBeGreaterThanOrEqual(AA_TEXT)
    }
  })
})

test.describe('auth brand panel — layout', () => {
  test('desktop keeps the panel at the inline start beside the form', async ({
    page,
  }, testInfo) => {
    await page.setViewportSize(VIEWPORTS.desktop)
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await open(page, '/auth/login')

    const aside = await boxOf(page, 'aside.auth-layout__brand')
    const main = await boxOf(page, 'main.auth-layout__content')
    expect(aside.width).toBe(main.width)
    expect(aside.y).toBe(main.y)
    expect(aside.x < main.x).toBe(isEn(testInfo))
    await expect(page.locator('.auth-value-card').first()).toBeVisible()
    await expect(page.locator('.auth-value-panel__footnote')).toBeInViewport()
  })

  test('the panel stays in view while the long register form scrolls', async ({
    page,
  }, testInfo) => {
    await page.setViewportSize(VIEWPORTS.desktop)
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await open(page, '/auth/register')

    await page.evaluate(() => window.scrollTo(0, 900))
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0)
    await expect(page.locator('.auth-value-panel__heading')).toBeInViewport()
    await expect(page.locator('.auth-value-card').last()).toBeInViewport()
    await expect(page.locator('.auth-value-panel__footnote')).toBeInViewport()
  })

  test('mobile shows a compact header above the form without cards', async ({ page }, testInfo) => {
    await page.setViewportSize(VIEWPORTS.mobile)
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await open(page, '/auth/login')

    const aside = await boxOf(page, 'aside.auth-layout__brand')
    const main = await boxOf(page, 'main.auth-layout__content')
    expect(aside.y + aside.height).toBeLessThanOrEqual(main.y + 1)
    await expect(page.locator('.auth-layout__logo')).toBeVisible()
    await expect(page.locator('.auth-value-panel__heading')).toHaveText(
      COPY[localeOf(testInfo)].heading,
    )
    await expect(page.locator('.auth-value-panel__list')).toBeHidden()
    await expect(page.locator('.auth-value-panel__footnote')).toBeHidden()
    await expect(page.getByRole('complementary').getByRole('listitem')).toHaveCount(0)
    await expect(page.locator('main h1')).toBeInViewport()
    await noHorizontalOverflow(page)
  })
})

test.describe('auth brand panel — screenshots', () => {
  for (const screen of SCREENS) {
    for (const theme of ['light', 'dark']) {
      for (const [viewport, size] of Object.entries(VIEWPORTS)) {
        test(`${screen.name} ${theme} ${viewport}`, async ({ page }, testInfo) => {
          await page.setViewportSize(size)
          await seedPreferences(page, { locale: localeOf(testInfo), theme })
          await open(page, screen.path)
          await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
          await noHorizontalOverflow(page)
          await settleFonts(page)
          await page.screenshot({
            path: `${SHOTS}/${screen.name}-${theme}-${viewport}-${suffixOf(testInfo)}.png`,
            fullPage: screen.fullPage,
          })
        })
      }
    }
  }

  test('register scrolled light desktop', async ({ page }, testInfo) => {
    await page.setViewportSize(VIEWPORTS.desktop)
    await seedPreferences(page, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/auth/register')
    await page.evaluate(() => window.scrollTo(0, 900))
    await expect(page.locator('.auth-value-panel__footnote')).toBeInViewport()
    await settleFonts(page)
    await page.screenshot({
      path: `${SHOTS}/register-scrolled-light-desktop-${suffixOf(testInfo)}.png`,
    })
  })
})
