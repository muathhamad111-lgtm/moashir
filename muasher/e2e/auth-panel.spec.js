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
const DATE_FIELD_VIEWPORTS = { ...VIEWPORTS, split: { width: 1024, height: 768 } }
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const PANEL_LABEL = { ar: 'عن منصة مؤشر', en: 'About Moashir' }
const AUTH_PAGES = [
  { path: '/auth/login', ready: '[name="identifier"]' },
  { path: '/auth/register', ready: '.auth-layout__card form' },
  { path: '/auth/forgot-password', ready: '[name="email"]' },
  { path: '/auth/reset-password/a1b2c3d4e5f6a7b8', ready: '[name="password"]' },
  { path: '/auth/invitations/invite-sarah-whitfield-2026', ready: '[name="password"]' },
]
const SCREENS = [
  { name: 'login', path: '/auth/login', fullPage: false },
  { name: 'register', path: '/auth/register', fullPage: true },
]
const FORM_TEXT = ['.auth-form__title', '.auth-form__switch', '.auth-layout__link']

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
  await expect(page.locator('aside.auth-layout__brand .auth-layout__logo')).toBeVisible()
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

  test('the form comes first and the named aside holds only the glowing logo', async ({
    page,
  }, testInfo) => {
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

    const aside = page.getByRole('complementary', { name: PANEL_LABEL[localeOf(testInfo)] })
    await expect(aside).toBeVisible()
    await expect(aside.getByRole('heading')).toHaveCount(0)
    await expect(aside.getByRole('listitem')).toHaveCount(0)
    await expect(page.locator('.auth-value-card, .auth-value-panel__heading')).toHaveCount(0)
    await expect(aside.locator('.auth-layout__trophy')).toHaveCount(0)
    await expect(aside.locator('img')).toHaveCount(1)
    await expect(aside.locator('.auth-layout__logo')).toBeVisible()
    for (const part of ['.auth-layout__halo', '.auth-layout__orbit', '.auth-layout__sheen']) {
      await expect(aside.locator(part)).toHaveAttribute('aria-hidden', 'true')
    }
    const animations = await aside
      .locator('.auth-layout__halo, .auth-layout__orbit, .auth-layout__sheen')
      .evaluateAll((parts) => parts.map((part) => getComputedStyle(part).animationName))
    expect(animations).toEqual(['lp-auth-breathe', 'lp-auth-orbit', 'lp-band-sheen'])
  })

  test('the logo glow stands still for reduced motion', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await open(page, '/auth/login')
    const animations = await page
      .locator('.auth-layout__halo, .auth-layout__orbit, .auth-layout__sheen')
      .evaluateAll((parts) => parts.map((part) => getComputedStyle(part).animationName))
    expect(animations).toEqual(['none', 'none', 'none'])
  })

  test('the landing cursor follows the pointer on the auth pages', async ({ page }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/auth/login')
    await expect(page.locator('.auth-layout')).toHaveClass(/lp-has-cursor/)
    const cursorOf = (selector) =>
      page
        .locator(selector)
        .first()
        .evaluate((node) => getComputedStyle(node).cursor)
    const card = await cursorOf('.auth-layout__card')
    const brand = await cursorOf('.auth-layout__brand')
    const button = await cursorOf('.auth-layout__card .btn--primary')
    for (const cursor of [card, brand, button]) expect(cursor).toMatch(/url\(|image-set/)
    expect(new Set([card, brand, button]).size).toBe(3)
    expect(button).toMatch(/pointer$/)
    expect(await cursorOf('.auth-layout__card input')).toBe('text')
  })

  test('the header row offers home, language and theme like the landing page', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/auth/login')
    const toggle = page.locator('.auth-layout__meta .lp-theme-switch')
    await expect(toggle).toHaveAttribute('aria-checked', 'false')
    await toggle.click()
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
    await page.locator('.auth-layout__meta .lp-top__locale').click()
    await expect(page.locator('html')).toHaveAttribute('lang', isEn(testInfo) ? 'ar' : 'en')
    await page.locator('.auth-layout__link').click()
    await expect(page).toHaveURL(/\/$/)
  })

  test('the login header carries the title only', async ({ page }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await open(page, '/auth/login')
    await expect(page.locator('.auth-form__title')).toBeVisible()
    await expect(page.locator('.auth-form__subtitle')).toHaveCount(0)
  })

  test('every auth page shares the same panel', async ({ page }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    for (const { path, ready } of AUTH_PAGES) {
      await open(page, path, ready)
      await expect(
        page.getByRole('complementary', { name: PANEL_LABEL[localeOf(testInfo)] }),
      ).toBeVisible()
      await expect(page.locator('.auth-value-card')).toHaveCount(0)
      await expect(page.locator('h1')).toHaveCount(1)
    }
  })

  for (const theme of ['light', 'dark']) {
    test(`form text and the primary button keep AA contrast in the ${theme} theme`, async ({
      page,
    }, testInfo) => {
      await seedPreferences(page, { locale: localeOf(testInfo), theme })
      await open(page, '/auth/login')
      for (const selector of [...FORM_TEXT, '.auth-layout__card .btn--primary']) {
        expect(await contrastOf(page, selector), selector).toBeGreaterThanOrEqual(AA_TEXT)
      }
    })
  }
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
    expect(aside.y).toBe(main.y)
    expect(aside.x < main.x).toBe(isEn(testInfo))
    await expect(page.locator('.auth-layout__emblem')).toBeInViewport()
    await expect(page.locator('.auth-layout__orbit')).toBeVisible()
    await expect(page.locator('.auth-layout__card')).toBeInViewport()
    await noHorizontalOverflow(page)
  })

  test('the panel stays in view while the long register form scrolls', async ({
    page,
  }, testInfo) => {
    await page.setViewportSize(VIEWPORTS.desktop)
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await open(page, '/auth/register')

    await page.evaluate(() => window.scrollTo(0, 900))
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(0)
    await expect(page.locator('.auth-layout__logo')).toBeInViewport()
    const card = await boxOf(page, '.auth-layout__card')
    const fields = await page
      .locator('.auth-layout__card .entity-form__grid')
      .evaluate((grid) => grid.getBoundingClientRect())
    expect(fields.left).toBeGreaterThanOrEqual(card.x)
    expect(fields.right).toBeLessThanOrEqual(card.x + card.width)
  })

  for (const [viewport, size] of Object.entries(DATE_FIELD_VIEWPORTS)) {
    test(`the founding date fits its field on the ${viewport} register form`, async ({
      page,
    }, testInfo) => {
      await page.setViewportSize(size)
      await seedPreferences(page, { locale: localeOf(testInfo) })
      await open(page, '/auth/register')
      await settleFonts(page)
      const layout = await page.locator('.datepicker').evaluate((group) => {
        const field = group.closest('.field').getBoundingClientRect()
        const box = group.getBoundingClientRect()
        const parts = [...group.querySelectorAll('.datepicker__segment, .datepicker__calendar')]
        const rects = parts.map((part) => part.getBoundingClientRect())
        return {
          spill: Math.max(
            ...rects.map((rect) => Math.max(box.left - rect.left, rect.right - box.right)),
          ),
          fillsField: Math.abs(box.width - field.width) <= 1,
          narrowest: Math.min(...rects.map((rect) => rect.width)),
        }
      })
      expect(layout.spill).toBeLessThanOrEqual(0.5)
      expect(layout.fillsField).toBe(true)
      expect(layout.narrowest).toBeGreaterThan(20)
      await noHorizontalOverflow(page)
    })
  }

  test('mobile shows a compact logo band above the form', async ({ page }, testInfo) => {
    await page.setViewportSize(VIEWPORTS.mobile)
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await open(page, '/auth/login')

    const aside = await boxOf(page, 'aside.auth-layout__brand')
    const main = await boxOf(page, 'main.auth-layout__content')
    expect(aside.y + aside.height).toBeLessThanOrEqual(main.y + 1)
    expect(aside.height).toBeLessThan(180)
    await expect(page.locator('.auth-layout__logo')).toBeVisible()
    await expect(page.locator('.auth-layout__orbit')).toBeHidden()
    await expect(page.locator('.auth-layout__halo')).toBeVisible()
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
})
