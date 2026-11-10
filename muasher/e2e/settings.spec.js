/* eslint-disable playwright/no-conditional-in-test, playwright/no-wait-for-timeout */
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

function seed(page, locale) {
  return seedSignedIn(
    page,
    { roles: ['system_admin', 'content_officer'], name: 'Admin' },
    { locale },
  )
}

test.beforeEach(async ({ page }, testInfo) => {
  await seed(page, testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar')
})

async function retrySection(page, selector) {
  for (let attempt = 0; attempt < 8; attempt += 1) {
    const errorRetry = page.locator('.error-state__retry')
    if (await errorRetry.isVisible().catch(() => false)) await errorRetry.click()
    const loaded = page.locator(selector).first()
    if (await loaded.isVisible().catch(() => false)) return
    await page.waitForTimeout(500)
  }
}

test('renders the settings hub with launcher cards', async ({ page }) => {
  await page.goto('/admin/settings')
  await expect(page.locator('.settings-hub__title')).toBeVisible()
  await expect(page.locator('.settings-hub__card')).toHaveCount(19)
  await expect(page.locator('a.settings-hub__card[href="/admin/settings/finance"]')).toBeVisible()
  await expect(page.locator('a.settings-hub__card[href="/admin/settings/prices"]')).toBeVisible()
})

test('opens the sign-in security screen with the entity sign-in code channels', async ({
  page,
}) => {
  await page.goto('/admin/settings/sign-in')
  await retrySection(page, '.sign-in-channels')
  await expect(page.locator('.toggle-switch')).toHaveCount(0)
  await expect(page.locator('input[name="codeChannelEmail"]')).toBeChecked()
  await expect(page.locator('input[name="codeChannelEmail"]')).toBeDisabled()
  await expect(page.locator('input[name="codeChannelSms"]')).not.toBeChecked()
  await expect(page.locator('.sign-in-security__statement')).toBeVisible()
})

test('payment screen shows stored secrets as metadata only and empty write-only inputs', async ({
  page,
}) => {
  await page.goto('/admin/settings/payment')
  await retrySection(page, '.secret-input__status')
  await expect(page.locator('.secret-input__status').first()).toContainText('••••')
  const inputs = page.locator('.secret-input__control')
  for (const input of await inputs.all()) {
    await expect(input).toHaveAttribute('type', 'password')
    await expect(input).toHaveAttribute('autocomplete', 'off')
    await expect(input).toHaveValue('')
  }
  await expect(
    page.getByRole('button', { name: /test connection|اختبار الاتصال/i }).first(),
  ).toBeVisible()
})

test('assessment screen exposes the review deadline and the evidence upload limits', async ({
  page,
}) => {
  await page.goto('/admin/settings/assessment')
  await retrySection(page, 'input[name="reviewSlaDays"]')
  await expect(page.locator('input[name="reviewSlaDays"]')).toHaveValue('14')
  await expect(page.locator('input[name="uploadMaxFileMb"]')).toHaveValue('20')
  await expect(page.locator('.checkbox-group .checkbox')).toHaveCount(6)
})

test('capture settings screens for the design review', async ({ page }, testInfo) => {
  const suffix = testInfo.project.name === 'chromium-en-ltr' ? 'en-ltr' : 'ar-rtl'

  await page.goto('/admin/settings')
  await expect(page.locator('.settings-hub__title')).toBeVisible()
  await page.waitForTimeout(400)
  await page.screenshot({ path: `test-results/settings-hub-${suffix}.png`, fullPage: true })

  await page.goto('/admin/settings/sign-in')
  await retrySection(page, '.toggle-switch')
  await page.waitForTimeout(400)
  await page.screenshot({ path: `test-results/settings-sign-in-${suffix}.png`, fullPage: true })

  await page.goto('/admin/settings/assessment')
  await retrySection(page, 'select[name="scale"]')
  await page.waitForTimeout(400)
  await page.screenshot({ path: `test-results/settings-assessment-${suffix}.png`, fullPage: true })

  await page.goto('/admin/settings/notifications')
  await retrySection(page, '.notification-type')
  await page.waitForTimeout(400)
  await page.screenshot({
    path: `test-results/settings-notifications-${suffix}.png`,
    fullPage: true,
  })
})
