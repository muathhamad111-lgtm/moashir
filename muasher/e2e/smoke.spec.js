/* eslint-disable playwright/no-wait-for-timeout */
import { test, expect } from '@playwright/test'

async function loadLanding(page) {
  await page.goto('/')
  for (let attempt = 0; attempt < 6; attempt += 1) {
    const retry = page.locator('.error-state__retry')
    if (await retry.isVisible().catch(() => false)) await retry.click()
    if (
      await page
        .locator('.home-hero__title')
        .isVisible()
        .catch(() => false)
    )
      return
    await page.waitForTimeout(500)
  }
  await expect(page.locator('.home-hero__title')).toBeVisible({ timeout: 10000 })
}

test('boots in Arabic RTL dark and renders the landing through the data layer', async ({
  page,
}) => {
  await loadLanding(page)

  await expect(page.locator('html')).toHaveAttribute('lang', 'ar')
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  await expect(page.locator('.home-hero__title')).toBeVisible()
})

test('switches to English LTR at runtime', async ({ page }) => {
  await loadLanding(page)
  await page.locator('.public-navbar').getByRole('button', { name: 'English' }).click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')
  await expect(page.locator('.home-hero__eyebrow')).toHaveText(
    'Charitable Work Excellence Model Platform',
  )
})

test('public layout renders navbar and footer, and the AI launcher stays off the home page', async ({
  page,
}) => {
  await loadLanding(page)
  await expect(page.locator('.public-navbar')).toBeVisible()
  await expect(page.locator('.footer')).toBeVisible()
  await expect(page.locator('.chat-widget__launcher')).toHaveCount(0)
  await page.goto('/kpis')
  await expect(page.locator('.chat-widget__launcher')).toBeVisible()
})
