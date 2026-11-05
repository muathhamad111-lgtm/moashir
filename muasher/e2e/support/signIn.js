import { expect } from '@playwright/test'

export const MOCK_PASSWORD = 'Muasher@2026'
export const DEV_OTP_CODE = '482913'
export const DEV_TOTP_CODE = '246810'

export async function openSignIn(page) {
  await page.goto('/auth/login')
  await expect(page.locator('[name="identifier"]')).toBeVisible()
}

export async function fillSignIn(page, identifier, password = MOCK_PASSWORD) {
  await page.locator('[name="identifier"]').fill(identifier)
  await page.locator('[name="password"]').fill(password)
}

export async function submitSignIn(page, identifier, password = MOCK_PASSWORD) {
  await fillSignIn(page, identifier, password)
  const answered = page.waitForResponse((response) =>
    new URL(response.url()).pathname.endsWith('/api/v1/auth/login'),
  )
  await page.locator('form button[type="submit"]').click()
  await answered
}

export async function typeCode(page, code) {
  await page.locator('.otp-input__box').first().click()
  await page.keyboard.type(code)
}

export async function signInEntity(page, identifier) {
  await openSignIn(page)
  await submitSignIn(page, identifier)
  await expect(page).toHaveURL(/\/auth\/otp/)
  await typeCode(page, DEV_OTP_CODE)
  await expect(page.locator('.portal-top-bar')).toBeVisible()
}
