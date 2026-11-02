/* eslint-disable playwright/expect-expect, playwright/no-conditional-in-test, playwright/no-wait-for-timeout */
import { test } from '@playwright/test'
import { seedLocale } from './support/session'

test.describe('visual review captures', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (testInfo.project.name === 'chromium-en-ltr') {
      await seedLocale(page, 'en')
    }
  })

  for (const [name, path] of [
    ['home', '/'],
    ['auth', '/auth/login'],
  ]) {
    test(`capture ${name}`, async ({ page }, testInfo) => {
      const suffix = testInfo.project.name === 'chromium-en-ltr' ? 'en-ltr' : 'ar-rtl'
      await page.goto(path)
      await page.waitForTimeout(800)
      await page.screenshot({ path: `test-results/review-${name}-${suffix}.png`, fullPage: true })
    })
  }

  test('capture gallery light theme + mobile', async ({ page }, testInfo) => {
    const suffix = testInfo.project.name === 'chromium-en-ltr' ? 'en-ltr' : 'ar-rtl'
    await page.addInitScript(() => localStorage.setItem('muasher.theme', 'light'))
    await page.goto('/dev/components')
    await page.waitForTimeout(800)
    await page.screenshot({ path: `test-results/review-gallery-light-${suffix}.png`, fullPage: false })
    await page.setViewportSize({ width: 375, height: 800 })
    await page.waitForTimeout(400)
    await page.screenshot({ path: `test-results/review-gallery-mobile-${suffix}.png`, fullPage: false })
  })
})
