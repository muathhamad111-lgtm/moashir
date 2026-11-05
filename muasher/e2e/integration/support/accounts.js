import { randomUUID } from 'node:crypto'
import { expect } from '@playwright/test'
import { mailMark, waitForCode, waitForResetToken } from './mailbox'
import { millisecondsToNextStep, timeStepOf, totpCode } from './totp'
import { paced } from './pacing'

export const STAFF = {
  systemAdmin: 'system.admin@moashir.example.sa',
  requestsFollower: 'requests.follower@moashir.example.sa',
  assessor: 'assessor@moashir.example.sa',
  filteringOfficer: 'filtering.officer@moashir.example.sa',
  awardSecretary: 'award.secretary@moashir.example.sa',
  contentOfficer: 'content.officer@moashir.example.sa',
}

const authenticators = new Map()
const passwords = new Map()

export async function typeCode(page, code) {
  await page.locator('.otp-input__box').first().click()
  await page.keyboard.type(code)
}

export function rememberPassword(email, password) {
  passwords.set(email, password)
}

export async function signInWithPassword(page, identifier, password) {
  await paced('signIn')
  await page.goto('/auth/login')
  await expect(page.locator('[name="identifier"]')).toBeVisible()
  await page.locator('[name="identifier"]').fill(identifier)
  await page.locator('[name="password"]').fill(password)
  await page.locator('form button[type="submit"]').click()
}

export async function requestSignInCode(page, identifier) {
  await paced('signInCode')
  await page.goto('/auth/login?method=code')
  await expect(page.locator('[role="tab"][data-method="code"]')).toHaveAttribute(
    'aria-selected',
    'true',
  )
  await page.locator('[name="identifier"]').fill(identifier)
  await page.locator('form button[type="submit"]').click()
}

export async function signInWithCode(page, identifier, email) {
  const mark = mailMark(email)
  await requestSignInCode(page, identifier)
  await expect(page).toHaveURL(/\/auth\/otp/)
  await typeCode(page, await waitForCode(email, mark))
}

export async function resetPassword(page, email, password = `Moashir-${randomUUID()}`) {
  await paced('passwordReset')
  await page.goto('/auth/forgot-password')
  const mark = mailMark(email)
  await page.locator('[name="email"]').fill(email)
  await page.locator('form button[type="submit"]').click()
  await expect(page.locator('.auth-status--success')).toBeVisible()
  const token = await waitForResetToken(email, mark)
  await page.goto(`/auth/reset-password/${token}`)
  await page.locator('[name="email"]').fill(email)
  await page.locator('[name="password"]').fill(password)
  await page.locator('[name="passwordConfirmation"]').fill(password)
  await page.locator('form button[type="submit"]').click()
  await expect(page).toHaveURL(/\/auth\/login\?notice=password-reset/)
  passwords.set(email, password)
  return password
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
  const password = passwords.get(email) ?? (await resetPassword(page, email))
  await paced('mfa', 2)
  await signInWithPassword(page, email, password)
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
