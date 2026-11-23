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
  await expect(page.locator('.settings-hub__card')).toHaveCount(17)
  await expect(page.locator('a.settings-hub__card[href="/admin/settings/finance"]')).toHaveCount(0)
  // Operator note 2026-11-22: the cities are a fixed list in code, so their card left the hub.
  await expect(page.locator('a.settings-hub__card[href="/admin/settings/cities"]')).toHaveCount(0)
  await expect(page.locator('a.settings-hub__card[href="/admin/settings/prices"]')).toHaveCount(0)
  await expect(
    page.locator('a.settings-hub__card[href="/admin/settings/assessment"]'),
  ).toBeVisible()
  await page.goto('/admin/settings/cities')
  await expect(page.locator('.error-page')).toBeVisible()
})

test('the old sign-in security address opens the general screen with the verification mechanism', async ({
  page,
}) => {
  await page.goto('/admin/settings/sign-in')
  await expect(page).toHaveURL(/\/admin\/settings\/general$/)
  await retrySection(page, '.sign-in-channels')
  // Three sign-in switches plus the two assignment-reason switches (admin review R3).
  await expect(page.locator('.toggle-switch')).toHaveCount(5)
  await expect(page.locator('input[name="assignRequired"]')).not.toBeChecked()
  await expect(page.locator('input[name="reassignRequired"]')).toBeChecked()
  await expect(page.locator('input[name="entityRegistrationReview"]')).toBeChecked()
  await expect(page.locator('input[name="codeChannelEmail"]')).toBeChecked()
  await expect(page.locator('input[name="codeChannelEmail"]')).toBeEnabled()
  await expect(page.locator('input[name="codeChannelSms"]')).not.toBeChecked()
  await expect(page.locator('input[name="twoFactorEnabled"]')).toBeChecked()
  await expect(page.locator('input[name="nationalIdEnabled"]')).not.toBeChecked()
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
    page.getByRole('button', { name: /check connection|التحقق من الاتصال/i }).first(),
  ).toBeVisible()
})

test('assessment screen exposes the review deadline and the evidence upload limits', async ({
  page,
}) => {
  await page.goto('/admin/settings/assessment?tab=accredited')
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
  await retrySection(page, '.notification-groups')
  await page.waitForTimeout(400)
  await page.screenshot({
    path: `test-results/settings-notifications-${suffix}.png`,
    fullPage: true,
  })
})
