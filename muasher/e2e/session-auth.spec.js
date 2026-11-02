/* eslint-disable playwright/no-wait-for-timeout */
import process from 'node:process'
import { test, expect } from '@playwright/test'
import { seedPreferences } from './support/session'

const MOCK_PASSWORD = 'Muasher@2026'
const DEV_TOTP_CODE = '246810'
const STAFF_ENROLLED = 'yasser.alghamdi@moashir.example.sa'
const STAFF_NOT_ENROLLED = 'laura.bennett@moashir.example.sa'
const ENTITY_EMAIL = 'info@albirr-riyadh.example.sa'

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

async function openPasswordLogin(page) {
  await page.goto('/auth/login')
  for (let attempt = 0; attempt < 8; attempt += 1) {
    if (
      await page
        .locator('.auth-form__mode')
        .first()
        .isVisible()
        .catch(() => false)
    )
      break
    const retry = page.locator('.error-state__retry')
    if (await retry.isVisible().catch(() => false)) await retry.click()
    await page.waitForTimeout(500)
  }
  await page.locator('.auth-form__mode').first().click()
}

async function submitPassword(page, email, nextSelector) {
  for (let attempt = 0; attempt < 6; attempt += 1) {
    await page.locator('[name="officialEmail"]').fill(email)
    await page.locator('[name="password"]').fill(MOCK_PASSWORD)
    await page.locator('form button[type="submit"]').click()
    if (
      await page
        .locator(nextSelector)
        .first()
        .waitFor({ timeout: 2500 })
        .then(
          () => true,
          () => false,
        )
    )
      return
  }
}

async function typeCode(page, code) {
  const boxes = page.locator('.otp-input__box')
  await boxes.first().click()
  await page.keyboard.type(code)
}

async function browserStorage(page) {
  return page.evaluate(() => {
    const entries = [...Object.entries(localStorage), ...Object.entries(sessionStorage)]
    return entries
      .filter(
        ([key, value]) =>
          /auth|token|user|session|permission|role/i.test(key) || /@|permissions|roles/.test(value),
      )
      .map(([key]) => key)
  })
}

test('entity password sign-in opens a cookie session with nothing in browser storage', async ({
  page,
}, testInfo) => {
  await seedPreferences(page, { locale: localeOf(testInfo) })
  await openPasswordLogin(page)
  await submitPassword(page, ENTITY_EMAIL, '.navbar--app')
  await expect(page).toHaveURL(/\/dashboard/)
  expect(await browserStorage(page)).toEqual([])
  const cookies = await page.context().cookies()
  expect(cookies.map((cookie) => cookie.name)).toContain('XSRF-TOKEN')

  await page.reload()
  await expect(page.locator('.navbar--app')).toBeVisible()
  await expect(page).toHaveURL(/\/dashboard/)
})

test('staff sign-in continues to the TOTP challenge before the workspace opens', async ({
  page,
}, testInfo) => {
  await seedPreferences(page, { locale: localeOf(testInfo) })
  await openPasswordLogin(page)
  await submitPassword(page, STAFF_ENROLLED, '.auth-mfa')
  await expect(page).toHaveURL(/\/auth\/mfa/)
  await page.goto('/dashboard')
  await expect(page).toHaveURL(/\/auth\/login/)

  await openPasswordLogin(page)
  await submitPassword(page, STAFF_ENROLLED, '.auth-mfa')
  await typeCode(page, DEV_TOTP_CODE)
  await expect(page.locator('.navbar--app')).toBeVisible()
  expect(await browserStorage(page)).toEqual([])
})

test('first staff sign-in enrols TOTP, shows the recovery codes once, then opens the workspace', async ({
  page,
}, testInfo) => {
  await seedPreferences(page, { locale: localeOf(testInfo) })
  await openPasswordLogin(page)
  await submitPassword(page, STAFF_NOT_ENROLLED, '.auth-mfa')
  await expect(page.locator('.auth-mfa__qr-image')).toBeVisible()
  await expect(page.locator('.auth-mfa__manual-key')).not.toBeEmpty()
  await typeCode(page, DEV_TOTP_CODE)
  await expect(page.locator('.auth-mfa__recovery-code')).toHaveCount(8)
  await page.locator('.auth-mfa__recovery .btn').click()
  await expect(page.locator('.navbar--app')).toBeVisible()
})

test('signing out ends the cookie session', async ({ page }, testInfo) => {
  await seedPreferences(page, { locale: localeOf(testInfo) })
  await openPasswordLogin(page)
  await submitPassword(page, ENTITY_EMAIL, '.navbar--app')
  await page.locator('.navbar__avatar').click()
  await page.getByRole('menuitem').last().click()
  await expect(page).toHaveURL(/\/auth\/login/)
  await page.goto('/dashboard')
  await expect(page).toHaveURL(/\/auth\/login/)
})

const RUNS_ON_DEV_SERVER = !process.env.CI

if (RUNS_ON_DEV_SERVER) {
  test('the demo login exists in local development only and opens a mock cookie session', async ({
    page,
  }, testInfo) => {
    await seedPreferences(page, { locale: localeOf(testInfo) })
    await page.goto('/auth/login')
    await page.locator('.auth-form__demo .btn').first().click()
    await expect(page).toHaveURL(/\/admin\/settings/)
    expect(await browserStorage(page)).toEqual([])
  })
}
