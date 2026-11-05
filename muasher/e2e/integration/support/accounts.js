import { expect } from '@playwright/test'
import { mailMark, waitForCode } from './mailbox'
import { millisecondsToNextStep, timeStepOf, totpCode } from './totp'

export const STAFF = {
  systemAdmin: 'system.admin@moashir.example.sa',
  requestsFollower: 'requests.follower@moashir.example.sa',
}

const authenticators = new Map()

export async function typeCode(page, code) {
  await page.locator('.otp-input__box').first().click()
  await page.keyboard.type(code)
}

async function chooseEmailCodeMode(page) {
  const modes = page.locator('.auth-form__mode')
  await expect(page.locator('[name="officialEmail"]')).toBeVisible()
  if ((await modes.count()) > 1)
    await modes
      .filter({ hasText: /code|رمز/i })
      .first()
      .click()
}

export async function requestEmailCode(page, email) {
  await page.goto('/auth/login')
  await chooseEmailCodeMode(page)
  const mark = mailMark(email)
  await page.locator('[name="officialEmail"]').fill(email)
  await page.locator('form button[type="submit"]').click()
  await expect(page).toHaveURL(/\/auth\/otp/)
  return waitForCode(email, mark)
}

async function freshTotp(email) {
  const authenticator = authenticators.get(email)
  if (!authenticator)
    throw new Error(
      `${email} already has an authenticator; run scripts/integration/backend.sh reset first.`,
    )
  if (authenticator.lastStep === timeStepOf()) {
    await new Promise((resolve) => setTimeout(resolve, millisecondsToNextStep() + 500))
  }
  authenticator.lastStep = timeStepOf()
  return totpCode(authenticator.secret, authenticator.lastStep)
}

async function enrol(page, email) {
  const key = (await page.locator('.auth-mfa__manual-key').textContent()).replace(/\s/g, '')
  authenticators.set(email, { secret: key, lastStep: null })
  await typeCode(page, await freshTotp(email))
  await expect(page.locator('.auth-mfa__recovery-code').first()).toBeVisible()
  await page.locator('.auth-mfa__recovery .btn').click()
}

export async function signInStaff(page, email) {
  const code = await requestEmailCode(page, email)
  await typeCode(page, code)
  await expect(page).toHaveURL(/\/auth\/mfa/)
  const enrolling = page.locator('.auth-mfa__manual-key')
  await expect(enrolling.or(page.locator('.auth-mfa__form')).first()).toBeVisible()
  if (await enrolling.isVisible()) await enrol(page, email)
  else await typeCode(page, await freshTotp(email))
  await expect(page.locator('.navbar--app')).toBeVisible()
}

export function hasAuthenticator(email) {
  return authenticators.has(email)
}
