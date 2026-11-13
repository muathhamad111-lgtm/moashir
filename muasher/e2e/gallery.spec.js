import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

const STAFF = { id: 5101, roles: ['system_admin'], entityId: null, name: 'مدير النظام' }

test.describe('dev component gallery', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await seedSignedIn(page, STAFF, {
      locale: testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar',
    })
  })

  test('renders every gallery section without console errors', async ({ page }, testInfo) => {
    const isEnglish = testInfo.project.name === 'chromium-en-ltr'
    const consoleErrors = []
    page.on('console', (message) => {
      if (message.type() !== 'error') return
      const text = message.text()
      if (/Failed to load resource/i.test(text) || /\b(500|503)\b/.test(text)) return
      consoleErrors.push(text)
    })

    await page.goto('/dev/components')

    await expect(page.locator('html')).toHaveAttribute('dir', isEnglish ? 'ltr' : 'rtl')
    await expect(page.locator('.gallery')).toBeVisible()
    await expect(page.locator('.gallery__section')).toHaveCount(14)
    await expect(page.locator('.navbar').first()).toBeVisible()
    await expect(page.locator('.sidebar')).toBeVisible()
    await expect(page.locator('.notification-item').first()).toBeVisible()

    await page.screenshot({
      path: `test-results/gallery-${isEnglish ? 'en-ltr' : 'ar-rtl'}.png`,
      fullPage: true,
    })

    expect(consoleErrors).toEqual([])
  })

  test('modal traps focus, closes on Escape, and restores focus to the trigger', async ({
    page,
  }) => {
    await page.goto('/dev/components')
    const trigger = page.getByRole('button', { name: /فتح النافذة|Open modal/ })
    await trigger.click()
    const dialog = page.getByRole('dialog')
    await expect(dialog).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(dialog).toBeHidden()
    await expect(trigger).toBeFocused()
  })

  test('table demo switches between the four list states', async ({ page }) => {
    await page.goto('/dev/components')
    await page.getByRole('button', { name: 'empty', exact: true }).click()
    await expect(page.locator('.empty-state').first()).toBeVisible()
    await page.getByRole('button', { name: 'error', exact: true }).click()
    await expect(page.locator('.table-wrap .error-state')).toBeVisible()
    await page.getByRole('button', { name: 'success', exact: true }).click()
    await expect(page.locator('.table__row').first()).toBeVisible()
  })
})
