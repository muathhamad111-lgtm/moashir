import process from 'node:process'
import { test, expect } from '@playwright/test'
import { seedPreferences } from './support/session'
import {
  DEV_TOTP_CODE,
  openSignIn,
  signInEntity,
  signInEntityWithCode,
  submitSignIn,
  typeCode,
} from './support/signIn'

const STAFF_ENROLLED = 'yasser.alghamdi@moashir.example.sa'
const STAFF_NOT_ENROLLED = 'laura.bennett@moashir.example.sa'
const ENTITY_EMAIL = 'info@albirr-riyadh.example.sa'

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

async function signInStaff(page, email) {
  await openSignIn(page)
  await submitSignIn(page, email)
  await expect(page.locator('.auth-mfa').first()).toBeVisible()
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
  await signInEntity(page, ENTITY_EMAIL)
  await expect(page).toHaveURL(/\/dashboard/)
  expect(await browserStorage(page)).toEqual([])
  const cookies = await page.context().cookies()
  expect(cookies.map((cookie) => cookie.name)).toContain('XSRF-TOKEN')

  await page.reload()
  await expect(page.locator('.portal-top-bar')).toBeVisible()
  await expect(page).toHaveURL(/\/dashboard/)
})

test('entity code sign-in opens the same cookie session with nothing in browser storage', async ({
  page,
}, testInfo) => {
  await seedPreferences(page, { locale: localeOf(testInfo) })
  await signInEntityWithCode(page, ENTITY_EMAIL)
  await expect(page).toHaveURL(/\/dashboard/)
  expect(await browserStorage(page)).toEqual([])
  await page.reload()
  await expect(page.locator('.portal-top-bar')).toBeVisible()
})

test('staff sign-in continues to the TOTP challenge before the workspace opens', async ({
  page,
}, testInfo) => {
  await seedPreferences(page, { locale: localeOf(testInfo) })
  await signInStaff(page, STAFF_ENROLLED)
  await expect(page).toHaveURL(/\/auth\/mfa/)
  await page.goto('/dashboard')
  await expect(page).toHaveURL(/\/auth\/login/)

  await signInStaff(page, STAFF_ENROLLED)
  await typeCode(page, DEV_TOTP_CODE)
  await expect(page.locator('.navbar--app')).toBeVisible()
  expect(await browserStorage(page)).toEqual([])
})

test('first staff sign-in enrols TOTP, shows the recovery codes once, then opens the workspace', async ({
  page,
}, testInfo) => {
  await seedPreferences(page, { locale: localeOf(testInfo) })
  await signInStaff(page, STAFF_NOT_ENROLLED)
  await expect(page.locator('.auth-mfa__qr-image')).toBeVisible()
  await expect(page.locator('.auth-mfa__manual-key')).not.toBeEmpty()
  await typeCode(page, DEV_TOTP_CODE)
  await expect(page.locator('.auth-mfa__recovery-code')).toHaveCount(8)
  await page.locator('.auth-mfa__recovery .btn').click()
  await expect(page.locator('.navbar--app')).toBeVisible()
})

test('signing out ends the cookie session', async ({ page }, testInfo) => {
  await seedPreferences(page, { locale: localeOf(testInfo) })
  await signInEntity(page, ENTITY_EMAIL)
  await page.locator('.account-menu__chip').click()
  await page.locator('.account-menu__panel .portal-menu-entry--danger').click()
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
