/* eslint-disable playwright/no-conditional-in-test, playwright/no-wait-for-timeout */
import { test, expect } from '@playwright/test'
import { seedLocale } from './support/session'

function localeSuffix(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en-ltr' : 'ar-rtl'
}

test.beforeEach(async ({ page }, testInfo) => {
  if (testInfo.project.name === 'chromium-en-ltr') {
    await seedLocale(page, 'en')
  }
})

async function retryFor(page, selector) {
  for (let attempt = 0; attempt < 6; attempt += 1) {
    const retry = page.locator('.error-state__retry').first()
    if (await retry.isVisible().catch(() => false)) await retry.click()
    if (
      await page
        .locator(selector)
        .first()
        .isVisible()
        .catch(() => false)
    )
      return
    await page.waitForTimeout(500)
  }
  await expect(page.locator(selector).first()).toBeVisible({ timeout: 10000 })
}

async function settleTurn(page, selector) {
  for (let attempt = 0; attempt < 6; attempt += 1) {
    if (
      await page
        .locator(selector)
        .first()
        .isVisible()
        .catch(() => false)
    )
      return
    const turnRetry = page.locator('.chat-widget__turn-error .chat-widget__inline-retry')
    if (await turnRetry.isVisible().catch(() => false)) await turnRetry.click()
    await page.waitForTimeout(600)
  }
  await expect(page.locator(selector).first()).toBeVisible({ timeout: 10000 })
}

test('landing renders hero, stats, tour, path cards, published model, levels and journey', async ({
  page,
}, testInfo) => {
  await page.goto('/')
  await retryFor(page, '.home-hero__title')
  await expect(page.locator('.home-hero__title')).not.toBeEmpty()

  await retryFor(page, '.home-path')
  await expect(page.locator('.home-path')).toHaveCount(3)

  await expect(page.locator('#tour .home-tour__step')).toHaveCount(5)
  await expect(page.locator('#journey .home-journey__stage')).toHaveCount(6)

  await retryFor(page, '.criteria-model__band')
  await expect(page.locator('.criteria-model__band')).toHaveCount(3)
  await expect(page.locator('.criteria-model__cell')).toHaveCount(9)

  await retryFor(page, '#levels .home-level')
  await expect(page.locator('#levels .home-level')).toHaveCount(4)
  await expect(page.locator('.home-kpi-promo')).toHaveCount(0)

  await page.screenshot({
    path: `test-results/task3-landing-${localeSuffix(testInfo)}.png`,
    fullPage: true,
  })
})

test('public KPIs page renders the premium grid with tabs, search and show more', async ({
  page,
}, testInfo) => {
  await page.goto('/kpis')
  await expect(page.locator('.kpis-page__title')).toBeVisible()
  await retryFor(page, '.kpi-card')

  await expect(page.locator('.kpis-page__tabs .tabs__tab').first()).toBeVisible()
  await expect(page.locator('.kpi-card').first()).toBeVisible()
  await expect.poll(async () => page.locator('.kpi-card').count()).toBeLessThanOrEqual(12)

  const categoryTab = page.locator('.kpis-page__tabs .tabs__tab').nth(1)
  await categoryTab.click()
  await expect(categoryTab).toHaveAttribute('aria-selected', 'true')
  await expect(page.locator('.kpi-card').first()).toBeVisible()
  await expect.poll(async () => page.locator('.kpi-card').count()).toBeLessThanOrEqual(12)

  const allTab = page.locator('.kpis-page__tabs .tabs__tab').first()
  await allTab.click()
  await expect(allTab).toHaveAttribute('aria-selected', 'true')

  const showMore = page.locator('.kpis-page__more button')
  await expect(showMore).toBeVisible()
  const preMore = await page.locator('.kpi-card').count()
  await showMore.click()
  await expect.poll(async () => page.locator('.kpi-card').count()).toBeGreaterThan(preMore)

  await page.screenshot({
    path: `test-results/task3b-kpis-grid-${localeSuffix(testInfo)}.png`,
    fullPage: true,
  })

  await page.locator('.kpis-page__toolbar .toolbar__search input').fill('zzzznomatch')
  await expect(page.locator('.empty-state')).toBeVisible()
  await expect(page.locator('.kpi-card')).toHaveCount(0)

  await page.screenshot({
    path: `test-results/task3b-kpis-search-${localeSuffix(testInfo)}.png`,
    fullPage: true,
  })
})

test('lenses page renders the analytics-lenses title and the five lens cards', async ({ page }) => {
  await page.goto('/lenses')
  await retryFor(page, '.lenses-page__title')
  await expect(page.locator('.lenses-page__title')).toBeVisible()
  await retryFor(page, '.lens-card')
  await expect(page.locator('.lens-card')).toHaveCount(5)
})

test('AI chat widget opens, answers a KB question and returns the KB-miss fallback', async ({
  page,
}, testInfo) => {
  await page.goto('/kpis')
  await retryFor(page, '.kpis-page__title')

  await page.locator('.chat-widget__launcher').click()
  const panel = page.locator('.chat-widget__panel')
  await expect(panel).toBeVisible()
  await expect(panel).toHaveAttribute('role', 'dialog')
  await expect(page.locator('.chat-widget__suggestions')).toBeVisible()

  await page.screenshot({
    path: `test-results/task3-chat-open-${localeSuffix(testInfo)}.png`,
    fullPage: false,
  })

  await page.locator('.chat-widget__suggestion').first().click()
  await settleTurn(page, '.chat-message--assistant')

  const outOfKb =
    testInfo.project.name === 'chromium-en-ltr'
      ? 'How much are the leadership-path fees?'
      : 'كم تبلغ رسوم مسار القيادات؟'
  await page.locator('.chat-widget__input').fill(outOfKb)
  await page.locator('.chat-widget__send').click()
  await settleTurn(page, '.chat-message--miss')
})

test('home section links scroll to the tour and paths, and the tour respects reduced motion', async ({
  page,
}, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.goto('/')
  await retryFor(page, '.home-hero__title')
  const english = testInfo.project.name === 'chromium-en-ltr'
  const links = page.locator('.public-navbar__menu .public-nav-links__link')

  await links.filter({ hasText: english ? 'Assessment paths' : 'مسارات التقييم' }).click()
  await expect(page.locator('#paths')).toBeInViewport()

  await links.filter({ hasText: english ? 'Platform tour' : 'جولة في المنصة' }).click()
  await expect(page.locator('#tour')).toBeInViewport()
  const toggle = page.locator('.home-tour__toggle')
  await expect(toggle).toHaveAttribute('aria-pressed', 'false')
  await page.locator('.home-tour__step').nth(3).click()
  await expect(page.locator('.home-tour__step').nth(3)).toHaveAttribute('aria-current', 'step')
  await expect(page.locator('.home-tour__pager-dot').nth(3)).toHaveClass(/is-active/)
})
