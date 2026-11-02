/* eslint-disable playwright/no-conditional-in-test, playwright/no-wait-for-timeout */
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

function seed(page, locale, roles = ['entity']) {
  return seedSignedIn(page, { id: 5001, roles, entityId: roles.includes('entity') ? 5001 : null, name: 'Entity' }, { locale })
}

test.beforeEach(async ({ page }, testInfo) => {
  await seed(page, testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar')
})

async function waitForList(page) {
  await expect(page.locator('.notifications-center__title')).toBeVisible()
  await expect(page.locator('.notification-center-item').first()).toBeVisible({ timeout: 10000 })
}

test('renders the role-scoped notifications center list', async ({ page }) => {
  await page.goto('/notifications')
  await waitForList(page)
  await expect(page.locator('.notification-center-item').first()).toBeVisible()
  await expect(page.locator('.notifications-center__count')).toBeVisible()
})

test('shows the six filter tabs and switches to unread', async ({ page }) => {
  await page.goto('/notifications')
  await waitForList(page)
  await expect(page.locator('.tabs__tab')).toHaveCount(6)
  await page.getByRole('tab', { name: /unread|غير مقروء/i }).first().click()
  await expect(page.locator('.notification-center-item.is-unread').first()).toBeVisible({
    timeout: 10000,
  })
})

test('marks a single notification as read', async ({ page }) => {
  await page.goto('/notifications')
  await waitForList(page)
  const markRead = page
    .locator('.notification-center-item.is-unread')
    .first()
    .getByRole('button', { name: /mark as read|تعليم كمقروء/i })
  await markRead.first().click()
  await expect(page.locator('.notifications-center__count')).toBeVisible()
})

test('opens the audit-log modal with the delivery entries', async ({ page }) => {
  await page.goto('/notifications')
  await waitForList(page)
  await page.getByRole('button', { name: /audit log|سجل التدقيق/i }).first().click()
  await expect(page.locator('.modal__panel')).toBeVisible()
  await expect(page.locator('.notification-audit')).toBeVisible()
})

test('capture notifications center screens for the design review', async ({ page }, testInfo) => {
  const suffix = testInfo.project.name === 'chromium-en-ltr' ? 'en-ltr' : 'ar-rtl'

  await page.goto('/notifications')
  await waitForList(page)
  await page.waitForTimeout(500)
  await page.screenshot({ path: `test-results/notifications-center-${suffix}.png`, fullPage: true })

  await page.getByRole('tab', { name: /unread|غير مقروء/i }).first().click()
  await page.waitForTimeout(600)
  await page.screenshot({
    path: `test-results/notifications-center-unread-${suffix}.png`,
    fullPage: true,
  })

  await page.getByRole('tab', { name: /^all$|الكل/i }).first().click()
  await waitForList(page)
  await page.getByRole('button', { name: /audit log|سجل التدقيق/i }).first().click()
  await expect(page.locator('.modal__panel')).toBeVisible()
  await page.waitForTimeout(600)
  await page.screenshot({
    path: `test-results/notifications-center-audit-${suffix}.png`,
    fullPage: true,
  })
})
