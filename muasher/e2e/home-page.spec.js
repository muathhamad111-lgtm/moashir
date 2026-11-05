/* eslint-disable playwright/no-wait-for-timeout, playwright/no-conditional-in-test */
import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'

const SHOTS = 'e2e/__screenshots__/home'
const FRAMES = `${SHOTS}/motion`
const SECTION_ORDER = [
  'hero',
  'tour',
  'paths',
  'model',
  'levels',
  'journey',
  'kit',
  'start',
  'leaders',
  'contact',
]
const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  mobile: { width: 390, height: 844 },
}
const ENTITY = { id: 5001, roles: ['entity'], entityId: 5001 }
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const MESSAGE = {
  ar: 'نرغب في معرفة متطلبات التقييم المعتمد والمدة المتوقعة للمراجعة.',
  en: 'We would like to know the accredited assessment requirements and the expected review time.',
}

mkdirSync(FRAMES, { recursive: true })

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function suffixOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en-ltr' : 'ar-rtl'
}

async function hideDevOverlays(page) {
  await page.addStyleTag({ content: DEV_OVERLAYS })
}

async function openHome(page) {
  await page.goto('/')
  await hideDevOverlays(page)
  for (let attempt = 0; attempt < 6; attempt += 1) {
    const retry = page.locator('.error-state__retry').first()
    if (await retry.isVisible().catch(() => false)) await retry.click()
    if (
      await page
        .locator('.home-hero__title')
        .isVisible()
        .catch(() => false)
    )
      break
    await page.waitForTimeout(500)
  }
  await expect(page.locator('.home-hero__title')).toBeVisible({ timeout: 10000 })
  await expect(page.locator('.criteria-model__cell').first()).toBeAttached({ timeout: 10000 })
  await expect(page.locator('#levels .home-level').first()).toBeAttached({ timeout: 10000 })
}

async function scrollThrough(page) {
  const height = await page.evaluate(() => document.documentElement.scrollHeight)
  for (let top = 0; top < height; top += 500) {
    await page.evaluate((y) => window.scrollTo(0, y), top)
    await page.waitForTimeout(60)
  }
  await page.evaluate(() => window.scrollTo(0, 0))
}

test.describe('home page — approved sections', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await seedPreferences(page, { locale: localeOf(testInfo), theme: 'light' })
  })

  test('renders the brief sections in order without demo markers or the assistant', async ({
    page,
  }) => {
    await openHome(page)
    const anchors = await page
      .locator('.landing > [id]')
      .evaluateAll((nodes) => nodes.map((node) => node.id))
    expect(anchors).toEqual(SECTION_ORDER)
    await expect(page.locator('.home-stats__item').first()).toBeVisible()
    expect(await page.locator('.home-stats__item').count()).toBeLessThanOrEqual(4)
    await expect(page.locator('.demo-notice')).toHaveCount(0)
    await expect(page.locator('.chat-widget__launcher')).toHaveCount(0)
    await expect(page.locator('.home-pill--example')).toHaveCount(1)
    await expect(page.locator('.home-path--award .home-pill--gold')).toBeVisible()
    await expect(page.locator('.home-path--award .btn')).toBeDisabled()
    await expect(page.locator('#kit .home-pill--soon')).toBeVisible()
    await expect(page.locator('#leaders .home-pill--soon')).toBeVisible()
    await expect(page.locator('body')).not.toContainText(
      /عرض تجريبي|\bDemo\b|فاتور|فواتير|invoice/i,
    )
  })

  test('reads the level thresholds, the accredited price and the published model', async ({
    page,
  }) => {
    await openHome(page)
    const ranges = page.locator('#levels .level-range__value')
    await expect(ranges).toHaveText(['60–69.99%', '70–79.99%', '80–89.99%', '90%'])
    await expect(ranges.first()).toHaveAttribute('dir', 'ltr')
    await expect(page.locator('.home-path--accredited .home-path__amount')).toHaveText(
      /^\d{1,3}(,\d{3})*\.\d{2}$/,
    )
    await expect(page.locator('.criteria-model__band')).toHaveCount(3)
    await expect(page.locator('.criteria-model__connector')).toHaveCount(2)
    await expect(page.locator('#journey .home-journey__marker')).toHaveText([
      '01',
      '02',
      '03',
      '04',
      '05',
      '06',
    ])
    await expect(page.locator('.hero-preview .score-ring__number')).toHaveText(/^\d+%$/)
  })

  test('the header follows the visible section and shrinks on scroll', async ({
    page,
  }, testInfo) => {
    await openHome(page)
    const header = page.locator('.public-navbar')
    await expect(header).not.toHaveClass(/is-scrolled/)
    const english = localeOf(testInfo) === 'en'
    const pathsLink = page
      .locator('.public-navbar__menu .public-nav-links__link')
      .filter({ hasText: english ? 'Assessment paths' : 'مسارات التقييم' })
    await pathsLink.click()
    await expect(page.locator('#paths')).toBeInViewport()
    await expect(header).toHaveClass(/is-scrolled/)
    await expect(pathsLink).toHaveAttribute('aria-current', 'location')
  })
})

test.describe('home page — contact form (Task-42 contract)', () => {
  test.beforeEach(async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
  })

  test('a guest is told what is missing, then sends a message and receives a reference', async ({
    page,
  }, testInfo) => {
    const locale = localeOf(testInfo)
    await seedPreferences(page, { locale, theme: 'light' })
    await openHome(page)
    const form = page.locator('.contact-form__form')
    await form.scrollIntoViewIfNeeded()
    await form.locator('button[type="submit"]').click()
    await expect(form.locator('.field__error').first()).toBeVisible()

    await form
      .locator('input[name="name"]')
      .fill(locale === 'en' ? 'Sara Al-Abdullah' : 'سارة العبدالله')
    await form.locator('input[name="email"]').fill('sara.alabdullah@example.sa')
    await form.locator('input[name="mobile"]').fill('0500000000')
    await form
      .locator('input[name="organization"]')
      .fill(locale === 'en' ? 'Al-Amal Charity' : 'جمعية الأمل الخيرية')
    await form.locator('select[name="enquiryType"]').selectOption('payments')
    await form.locator('textarea[name="message"]').fill(MESSAGE[locale])
    await form.locator('input[type="checkbox"]').check()
    await expect(form.locator('select[name="enquiryType"] option[value="payments"]')).toHaveText(
      locale === 'en' ? 'Payments & receipts' : 'الدفع والإيصالات',
    )
    await form.locator('button[type="submit"]').click()

    const reference = page.locator('.contact-form__reference-value')
    await expect(reference).toHaveText(/^CNT-\d{4}-\d{6}$/, { timeout: 10000 })
    await expect(page.locator('.contact-form__success')).toHaveAttribute('role', 'status')
    await page
      .locator('.home-contact__card')
      .screenshot({ path: `${SHOTS}/contact-success-${suffixOf(testInfo)}.png` })
  })

  test('a signed-in entity sees its identity prefilled and read-only', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, ENTITY, { locale: localeOf(testInfo), theme: 'light' })
    await openHome(page)
    const name = page.locator('.contact-form__form input[name="name"]')
    await name.scrollIntoViewIfNeeded()
    await expect(name).toHaveAttribute('readonly', '')
    await expect(name).not.toHaveValue('')
    await expect(page.locator('.contact-form__form input[name="email"]')).toHaveAttribute(
      'readonly',
      '',
    )
    await expect(page.locator('.contact-form__signed-in')).toBeVisible()
    await expect(page.locator('.public-navbar')).toContainText(
      localeOf(testInfo) === 'en' ? 'Dashboard' : 'لوحة التحكم',
    )
  })
})

test.describe('home page — mobile menu', () => {
  test.use({ viewport: VIEWPORTS.mobile })

  test('the drawer opens from the start edge, traps focus and closes with Escape', async ({
    page,
  }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await seedPreferences(page, { locale: localeOf(testInfo), theme: 'light' })
    await openHome(page)
    const toggle = page.locator('.public-navbar__toggle')
    await toggle.click()
    const panel = page.locator('.nav-drawer__panel')
    await expect(panel).toBeVisible()
    await expect(panel).toHaveAttribute('aria-modal', 'true')
    await expect(page.locator('.nav-drawer__close')).toBeFocused()
    const box = await panel.boundingBox()
    const startsOnTheRight = localeOf(testInfo) === 'ar'
    const startEdge = startsOnTheRight ? box.x + box.width : box.x
    expect(Math.round(startEdge)).toBe(startsOnTheRight ? VIEWPORTS.mobile.width : 0)
    await page.screenshot({ path: `${SHOTS}/menu-open-mobile-${suffixOf(testInfo)}.png` })

    await page.keyboard.press('Shift+Tab')
    await expect(panel.locator('.nav-drawer__auth a').last()).toBeFocused()
    await page.keyboard.press('Tab')
    await expect(page.locator('.nav-drawer__close')).toBeFocused()
    await page.keyboard.press('Escape')
    await expect(panel).toHaveCount(0)
    await expect(toggle).toBeFocused()
  })
})

test.describe('home page — motion', () => {
  test.use({ viewport: VIEWPORTS.desktop })

  test('reveals on scroll, floats the preview card and offers pause controls', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo), theme: 'light' })
    await openHome(page)
    const card = page.locator('.hero-preview__card')
    await expect(card).toHaveCSS('animation-name', 'hero-float')
    const pause = page.locator('.home-hero__motion')
    await pause.click()
    await expect(page.locator('.landing')).toHaveClass(/is-motion-paused/)
    await expect(card).toHaveCSS('animation-play-state', 'paused')
    await pause.click()
    await expect(card).toHaveCSS('animation-play-state', 'running')

    const kit = page.locator('#kit .home-kit__grid')
    await expect(kit).toHaveClass(/is-reveal-pending/)
    await kit.scrollIntoViewIfNeeded()
    await expect(kit).toHaveClass(/is-revealed/)

    const tour = page.locator('#tour')
    await tour.scrollIntoViewIfNeeded()
    await expect(page.locator('.home-tour__toggle')).toHaveAttribute('aria-pressed', 'true')
    await expect(page.locator('.home-tour__step').nth(1)).toHaveAttribute('aria-current', 'step', {
      timeout: 8000,
    })
    await page.locator('.home-tour__toggle').click()
    await expect(page.locator('.home-tour__toggle')).toHaveAttribute('aria-pressed', 'false')
  })

  test('records the entrance, the tour and the journey line as frames', async ({
    page,
  }, testInfo) => {
    const suffix = suffixOf(testInfo)
    await seedPreferences(page, { locale: localeOf(testInfo), theme: 'light' })
    await page.goto('/')
    await hideDevOverlays(page)
    await expect(page.locator('.home-hero__title')).toBeVisible({ timeout: 10000 })
    for (const moment of [0, 250, 600, 1200]) {
      await page.waitForTimeout(moment === 0 ? 0 : 350)
      await page.screenshot({
        path: `${FRAMES}/01-hero-${String(moment).padStart(4, '0')}ms-${suffix}.png`,
      })
    }
    await page.locator('#journey').scrollIntoViewIfNeeded()
    for (const [index, offset] of [-500, -300, -100, 100].entries()) {
      await page.evaluate((delta) => {
        const top = document.getElementById('journey').getBoundingClientRect().top + window.scrollY
        window.scrollTo(0, top + delta)
      }, offset)
      await page.waitForTimeout(250)
      await page.screenshot({ path: `${FRAMES}/02-journey-${index + 1}-${suffix}.png` })
    }
    await page.locator('#tour').scrollIntoViewIfNeeded()
    await page.waitForTimeout(2500)
    await page.screenshot({ path: `${FRAMES}/03-tour-progress-${suffix}.png` })
    await expect(page.locator('.home-tour__progress')).toHaveCount(1)
  })
})

for (const theme of ['light', 'dark']) {
  for (const [viewportName, viewport] of Object.entries(VIEWPORTS)) {
    test.describe(`home page screenshots — ${theme} ${viewportName}`, () => {
      test.use({ viewport })

      test(`captures the full page (${theme}, ${viewportName})`, async ({ page }, testInfo) => {
        await page.emulateMedia({ reducedMotion: 'reduce' })
        await seedPreferences(page, { locale: localeOf(testInfo), theme })
        await openHome(page)
        await expect(page.locator('html')).toHaveAttribute('data-theme', theme)
        await scrollThrough(page)
        await expect(page.locator('.home-stats__item').first()).toBeVisible()
        const overflow = await page.evaluate(
          () => document.documentElement.scrollWidth - window.innerWidth,
        )
        expect(overflow).toBeLessThanOrEqual(0)
        await page.screenshot({
          path: `${SHOTS}/home-${theme}-${viewportName}-${suffixOf(testInfo)}.png`,
          fullPage: true,
        })
      })
    })
  }
}
