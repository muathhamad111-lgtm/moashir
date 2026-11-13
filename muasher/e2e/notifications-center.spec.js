import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

const STAFF = { id: 5101, roles: ['system_admin'], entityId: null, name: 'مدير النظام' }

test.beforeEach(async ({ page }, testInfo) => {
  await seedSignedIn(page, STAFF, {
    locale: testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar',
  })
})

async function openCentre(page) {
  await page.goto('/notifications')
  await expect(page.locator('.notifications-center__title')).toBeVisible()
  await expect(page.locator('.notification-center-item').first()).toBeVisible({ timeout: 10000 })
}

test('staff read the same notification centre inside the back-office shell', async ({ page }) => {
  await openCentre(page)
  await expect(page.locator('.sidebar')).toBeVisible()
  await expect(page.locator('[role="tablist"] [role="tab"]')).toHaveCount(2)
  await expect(page.locator('.notification-day-group').first()).toBeVisible()
  await expect(page.locator('.notification-preferences-card')).toBeVisible()
})

test('staff filter unread and mark everything read from the centre', async ({ page }) => {
  await openCentre(page)
  await page.locator('[role="tab"]').nth(1).click()
  await expect(page.locator('.notification-center-item.is-unread').first()).toBeVisible()
  await page.locator('.notifications-center__hero .btn').click()
  await expect(page.locator('.empty-state')).toBeVisible()
  await expect(page.locator('.notification-bell__count')).toHaveCount(0)
})

test('the staff bell lists the latest notifications and opens one', async ({ page }) => {
  await page.goto('/dashboard')
  const bell = page.locator('.notification-bell > button')
  await expect(bell).toBeVisible()
  await bell.click()
  await expect(page.locator('.notification-panel .notification-item').first()).toBeVisible()
  await page.locator('.notification-panel .notification-item').first().click()
  await expect(page.locator('.notification-panel')).toHaveCount(0)
})
