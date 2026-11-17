import { test, expect } from '@playwright/test'
import { loadLanding } from './support/landing'

test('boots in Arabic RTL dark and renders the landing through the data layer', async ({
  page,
}) => {
  await loadLanding(page)

  await expect(page.locator('html')).toHaveAttribute('lang', 'ar')
  await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  await expect(page.locator('#lp-hero-title')).toBeVisible()
})

test('switches to English LTR at runtime', async ({ page }) => {
  await loadLanding(page)
  await page.locator('.lp-top__locale').click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')
  await expect(page.locator('.lp-hero__chip')).toHaveText(
    'The Charitable Work Excellence Model Platform',
  )
})

test('public layout renders navbar and footer, and the AI launcher stays off the home page', async ({
  page,
}) => {
  await loadLanding(page)
  await expect(page.locator('.lp-top')).toBeVisible()
  await expect(page.locator('.lp-foot')).toBeVisible()
  await expect(page.locator('.chat-widget__launcher')).toHaveCount(0)
  await page.goto('/kpis')
  await expect(page.locator('.chat-widget__launcher')).toBeVisible()
})
