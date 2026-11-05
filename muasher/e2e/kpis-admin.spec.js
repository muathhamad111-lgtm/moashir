/* eslint-disable playwright/no-conditional-in-test, playwright/no-wait-for-timeout, playwright/expect-expect */
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

function seed(page, locale) {
  return seedSignedIn(page, { roles: ['content_officer'], name: 'Content Officer' }, { locale })
}

test.beforeEach(async ({ page }, testInfo) => {
  await seed(page, testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar')
})

async function retryTable(page) {
  for (let attempt = 0; attempt < 8; attempt += 1) {
    const errorRetry = page.locator('.error-state__retry')
    if (await errorRetry.isVisible().catch(() => false)) await errorRetry.click()
    const loaded = page.locator('.table:not(.table--loading) tbody .table__row').first()
    if (await loaded.isVisible().catch(() => false)) return
    await page.waitForTimeout(600)
  }
}

test('renders the KPIs management table with the fixtures', async ({ page }) => {
  await page.goto('/admin/kpis')
  await expect(page.locator('.kpis-admin__title').first()).toBeVisible()
  await retryTable(page)
  await expect(page.locator('.table:not(.table--loading) tbody .table__row')).toHaveCount(15)
  await expect(page.locator('.pagination')).toBeVisible({ timeout: 10000 })
})

test('shows the stats cards for the KPIs summary', async ({ page }) => {
  await page.goto('/admin/kpis')
  await retryTable(page)
  await expect(page.locator('.stats-card').first()).toBeVisible()
  await expect(page.locator('.stats-card')).toHaveCount(4)
})

test('renders the up/down reorder controls per row', async ({ page }) => {
  await page.goto('/admin/kpis')
  await retryTable(page)
  await expect(page.locator('.kpis-admin__reorder').first()).toBeVisible()
  await expect(page.locator('.kpis-admin__reorder-btn')).not.toHaveCount(0)
})

test('opens the add-KPI form modal with bilingual fields and color swatches', async ({ page }) => {
  await page.goto('/admin/kpis')
  await retryTable(page)
  await page.getByRole('button', { name: /add indicator|إضافة مؤشر/i }).first().click()
  await expect(page.locator('.modal__panel')).toBeVisible()
  await expect(page.locator('input[name="name.ar"]')).toBeVisible()
  await expect(page.locator('input[name="name.en"]')).toBeVisible()
  await expect(page.locator('.kpi-form__swatches')).toHaveCount(2)
  await expect(page.locator('.kpi-form__swatch')).toHaveCount(10)
  await expect(page.locator('.kpi-form__preview .kpi-card')).toBeVisible()
})

test('the PUBLIC KPIs page still renders published, ordered indicators', async ({ page }) => {
  await page.goto('/kpis')
  await expect(page.locator('.kpis-page__title')).toBeVisible()
  for (let attempt = 0; attempt < 8; attempt += 1) {
    const errorRetry = page.locator('.error-state__retry')
    if (await errorRetry.isVisible().catch(() => false)) await errorRetry.click()
    const card = page.locator('.kpis-page__grid .kpi-card').first()
    if (await card.isVisible().catch(() => false)) break
    await page.waitForTimeout(600)
  }
  await expect(page.locator('.kpis-page__grid .kpi-card').first()).toBeVisible()
})

test('capture KPIs admin screens for the design review', async ({ page }, testInfo) => {
  const suffix = testInfo.project.name === 'chromium-en-ltr' ? 'en-ltr' : 'ar-rtl'

  await page.goto('/admin/kpis')
  await retryTable(page)
  await page.waitForTimeout(600)
  await page.screenshot({ path: `test-results/kpis-admin-index-${suffix}.png`, fullPage: true })

  await page.getByRole('button', { name: /add indicator|إضافة مؤشر/i }).first().click()
  await page.waitForTimeout(500)
  await page.screenshot({ path: `test-results/kpis-admin-form-${suffix}.png`, fullPage: true })
})
