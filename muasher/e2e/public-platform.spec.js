/* eslint-disable playwright/no-conditional-in-test, playwright/no-wait-for-timeout */
import { test, expect } from '@playwright/test'
import { seedLocale } from './support/session'
import { searchList } from './support/listFilters'

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

test('landing renders hero, stats, path cards, the model stages, levels and journey', async ({
  page,
}, testInfo) => {
  await page.goto('/')
  await retryFor(page, '#lp-hero-title')
  await expect(page.locator('#lp-hero-title')).not.toBeEmpty()

  await retryFor(page, '.lp-path')
  await expect(page.locator('.lp-path')).toHaveCount(3)
  await expect(page.locator('#model .lp-cycle__step')).toHaveCount(4)
  await retryFor(page, '#model .lp-model-criteria .lp-tag')
  await expect(page.locator('#model .lp-model-criteria .lp-tag')).toHaveCount(9)
  await expect(page.locator('#model .lp-model-criteria__group')).toHaveCount(3)
  await expect(page.locator('#journey .lp-jr')).toHaveCount(6)

  await retryFor(page, '#levels .lp-lv__tiers button')
  await expect(page.locator('#levels .lp-lv__tiers button')).toHaveCount(5)

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

  await searchList(page, 'zzzznomatch')
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

test('home header links scroll to the paths and the levels', async ({ page }, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/')
  await retryFor(page, '#lp-hero-title')
  const english = testInfo.project.name === 'chromium-en-ltr'
  const links = page.locator('.lp-top__nav .lp-top__link')

  await links.filter({ hasText: english ? 'Assessment paths' : 'مسارات التقييم' }).click()
  await expect(page.locator('#paths')).toBeInViewport()

  await links.filter({ hasText: english ? 'Excellence levels' : 'مستويات التميّز' }).click()
  await expect(page.locator('#levels')).toBeInViewport()
})
