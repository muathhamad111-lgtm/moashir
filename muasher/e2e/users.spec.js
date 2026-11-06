/* eslint-disable playwright/no-conditional-in-test, playwright/no-wait-for-timeout, playwright/expect-expect */
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

function seed(page, locale) {
  return seedSignedIn(page, { roles: ['system_admin'], name: 'Admin' }, { locale })
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

test('renders the users list with the platform-users fixtures', async ({ page }) => {
  await page.goto('/admin/settings/users')
  await expect(page.locator('.users-page__title').first()).toBeVisible()
  await retryTable(page)
  await expect(page.locator('.table:not(.table--loading) tbody .table__row')).toHaveCount(10)
  await expect(page.locator('.pagination')).toBeVisible({ timeout: 10000 })
})

test('shows the stats cards for the users summary', async ({ page }) => {
  await page.goto('/admin/settings/users')
  await retryTable(page)
  await expect(page.locator('.stats-card').first()).toBeVisible()
  await expect(page.locator('.stats-card')).toHaveCount(5)
})

test('filters the list by role', async ({ page }) => {
  await page.goto('/admin/settings/users')
  await retryTable(page)
  await page.locator('.toolbar__filters .dropdown-filter__trigger').first().click()
  await page.locator('.toolbar__filters [role="option"]').nth(1).click()
  await retryTable(page)
  await expect(page.locator('.table:not(.table--loading) tbody .table__row').first()).toBeVisible()
})

test('opens the add-user form modal', async ({ page }) => {
  await page.goto('/admin/settings/users')
  await retryTable(page)
  await page
    .getByRole('button', { name: /invite user|دعوة مستخدم/i })
    .first()
    .click()
  await expect(page.locator('.modal__panel')).toBeVisible()
  await expect(page.locator('input[name="name"]')).toBeVisible()
  await expect(page.locator('input[name="email"]')).toBeVisible()
})

test('capture users screens for the design review', async ({ page }, testInfo) => {
  const suffix = testInfo.project.name === 'chromium-en-ltr' ? 'en-ltr' : 'ar-rtl'

  await page.goto('/admin/settings/users')
  await retryTable(page)
  await page.waitForTimeout(600)
  await page.screenshot({ path: `test-results/users-index-${suffix}.png`, fullPage: true })

  await page.locator('.toolbar__filters .dropdown-filter__trigger').first().click()
  await page.locator('.toolbar__filters [role="option"]').nth(1).click()
  await retryTable(page)
  await page.waitForTimeout(600)
  await page.screenshot({ path: `test-results/users-filter-${suffix}.png`, fullPage: true })

  await page.reload()
  await retryTable(page)
  await page
    .getByRole('button', { name: /invite user|دعوة مستخدم/i })
    .first()
    .click()
  await page.waitForTimeout(500)
  await page.screenshot({ path: `test-results/users-form-${suffix}.png`, fullPage: true })
})
