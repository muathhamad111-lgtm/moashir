/* eslint-disable playwright/no-wait-for-timeout */
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
  for (let attempt = 0; attempt < 8; attempt += 1) {
    const retry = page.locator('.error-state__retry, .error-state button').first()
    if (await retry.isVisible().catch(() => false)) await retry.click()
    if (await page.locator(selector).first().isVisible().catch(() => false)) return
    await page.waitForTimeout(500)
  }
  await expect(page.locator(selector).first()).toBeVisible({ timeout: 10000 })
}

async function reloadUntil(page, url, selector) {
  for (let attempt = 0; attempt < 8; attempt += 1) {
    await page.goto(url)
    await retryFor(page, '.home-kpi-promo')
    if (await page.locator(selector).first().isVisible().catch(() => false)) return
    await page.waitForTimeout(400)
  }
  await expect(page.locator(selector).first()).toBeVisible({ timeout: 10000 })
}

test('a KPI card click opens the per-slug detail page with the premium layout', async ({
  page,
}, testInfo) => {
  await page.goto('/kpis')
  await expect(page.locator('.kpis-page__title')).toBeVisible()
  await retryFor(page, '.kpi-card')

  await page.locator('.kpi-card').first().click()
  await expect(page).toHaveURL(/\/kpis\/[a-z0-9-]+$/)

  await retryFor(page, '.kpi-detail__title')
  await expect(page.locator('.kpi-detail__number')).toBeVisible()
  await expect(page.locator('.kpi-detail__breadcrumb')).toBeVisible()

  await page.screenshot({
    path: `test-results/task3c-kpi-detail-${localeSuffix(testInfo)}.png`,
    fullPage: true,
  })

  await page.locator('.kpi-detail__crumb').first().click()
  await expect(page).toHaveURL(/\/kpis$/)
})

test('an unknown slug renders the branded not-found state with a back link', async ({
  page,
}, testInfo) => {
  await page.goto('/kpis/this-indicator-does-not-exist')
  await retryFor(page, '.empty-state')
  await expect(page.locator('.kpi-detail__body')).toHaveCount(0)
  await expect(page.locator('.empty-state__action .btn')).toBeVisible()

  await page.screenshot({
    path: `test-results/task3c-kpi-notfound-${localeSuffix(testInfo)}.png`,
    fullPage: true,
  })

  await page.locator('.empty-state__action .btn').click()
  await expect(page).toHaveURL(/\/kpis$/)
})

test('the homepage KPI promo renders featured cards and the view-all CTA', async ({
  page,
}, testInfo) => {
  await reloadUntil(page, '/', '.home-kpi-promo__grid .kpi-card')

  const promo = page.locator('.home-kpi-promo')
  await promo.scrollIntoViewIfNeeded()

  await expect(page.locator('.home-kpi-promo__grid .kpi-card').first()).toBeVisible()
  await expect(page.locator('.home-kpi-promo__cta .btn')).toBeVisible()

  await page.screenshot({
    path: `test-results/task3c-home-preview-${localeSuffix(testInfo)}.png`,
    fullPage: false,
  })

  await page.locator('.home-kpi-promo__grid .kpi-card').first().click()
  await expect(page).toHaveURL(/\/kpis\/[a-z0-9-]+$/)
})
