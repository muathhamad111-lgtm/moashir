/* eslint-disable playwright/no-conditional-in-test, playwright/no-wait-for-timeout, playwright/expect-expect */
import { test, expect } from '@playwright/test'
import { seedSignedIn, seedLocale } from './support/session'
import { openSignIn, requestSignInCode, signInTab } from './support/signIn'

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixFor(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function seedEntity(page, locale) {
  return seedSignedIn(
    page,
    {
      id: 5001,
      roles: ['entity'],
      entityId: 5001,
      name: { ar: 'أحمد بن سالم القحطاني', en: 'Ahmed Salem Al-Qahtani' },
    },
    { locale },
  )
}

function localeOnly(page, locale) {
  if (locale === 'en') {
    return seedLocale(page, 'en')
  }
  return Promise.resolve()
}

async function loadLoginForm(page) {
  await openSignIn(page)
}

async function goToOtpViaLogin(page) {
  await openSignIn(page)
  await requestSignInCode(page, 'info@albirr-riyadh.example.sa')
  await expect(page.locator('.otp-input__box').first()).toBeVisible()
}

test('register renders the full §5 field set', async ({ page }, testInfo) => {
  await localeOnly(page, isEn(testInfo) ? 'en' : 'ar')
  await page.goto('/auth/register')
  await expect(page.locator('.auth-form__title')).toBeVisible()
  await expect(page.locator('[name="authorizedPersonName"]')).toBeVisible()
  await expect(page.locator('[name="officialEmail"]')).toBeVisible()
  await expect(page.locator('[name="unifiedNumber"]')).toBeVisible()
  await expect(page.locator('[name="entityType"]')).toBeVisible()
  await expect(page.locator('[name="developmentSector"]')).toBeVisible()
  await expect(page.locator('[name="governanceGrade"]')).toBeVisible()
  await expect(page.locator('[name="annualBeneficiariesCount"]')).toBeVisible()
})

test('login renders the password tab first, then the code tab without a password', async ({
  page,
}, testInfo) => {
  await localeOnly(page, isEn(testInfo) ? 'en' : 'ar')
  await loadLoginForm(page)
  await expect(page.locator('.auth-form__title')).toBeVisible()
  await expect(page.locator('[role="tablist"] [role="tab"]')).toHaveCount(2)
  await expect(signInTab(page, 'password')).toHaveAttribute('aria-selected', 'true')
  await expect(page.locator('[name="password"]')).toBeVisible()
  await expect(page.locator('.auth-form__forgot')).toBeVisible()
  await signInTab(page, 'code').click()
  await expect(page.locator('[name="password"]')).toHaveCount(0)
  await expect(page.locator('[data-note="entity-only"]')).toBeVisible()
})

test('login code → OTP shows the code boxes, expiry and resend', async ({ page }, testInfo) => {
  await localeOnly(page, isEn(testInfo) ? 'en' : 'ar')
  await goToOtpViaLogin(page)
  await expect(page.locator('.otp-input__box')).toHaveCount(6)
  await expect(page.locator('.auth-otp__expiry')).toBeVisible()
  await expect(page.locator('.auth-otp__resend')).toBeVisible()
})

test('profile renders the entity details behind the session', async ({ page }, testInfo) => {
  await seedEntity(page, isEn(testInfo) ? 'en' : 'ar')
  await page.goto('/entity/profile')
  for (let attempt = 0; attempt < 6; attempt += 1) {
    if (
      await page
        .locator('.entity-profile-hub .portal-area__title')
        .isVisible()
        .catch(() => false)
    )
      break
    const retry = page.locator('.error-state__retry')
    if (await retry.isVisible().catch(() => false)) await retry.click()
    await page.waitForTimeout(600)
  }
  await expect(page.locator('.entity-profile-hub .portal-area__title')).toBeVisible()
})

test('entity accounts land on the journey home in the portal shell', async ({ page }, testInfo) => {
  await seedEntity(page, isEn(testInfo) ? 'en' : 'ar')
  await page.goto('/dashboard')
  await expect(page.locator('.portal-top-bar')).toBeVisible()
  await expect(page.locator('.sidebar')).toHaveCount(0)
  await expect(page.locator('[data-testid="path-tiles"]')).toBeVisible()
  await expect(page.locator('[data-testid="next-step"]')).toBeVisible()
})

test('capture entity-auth screens for the design review', async ({ page }, testInfo) => {
  const suffix = suffixFor(testInfo)

  await localeOnly(page, isEn(testInfo) ? 'en' : 'ar')
  await page.goto('/auth/register')
  await page.waitForTimeout(900)
  await page.screenshot({ path: `test-results/entity-register-${suffix}.png`, fullPage: true })

  await loadLoginForm(page)
  await page.waitForTimeout(500)
  await page.screenshot({ path: `test-results/entity-login-${suffix}.png`, fullPage: true })

  await goToOtpViaLogin(page)
  await page.waitForTimeout(500)
  await page.screenshot({ path: `test-results/entity-otp-${suffix}.png`, fullPage: true })

  await seedEntity(page, isEn(testInfo) ? 'en' : 'ar')
  await page.goto('/entity/profile')
  await page.waitForTimeout(1000)
  await page.screenshot({ path: `test-results/entity-profile-${suffix}.png`, fullPage: true })

  await page.goto('/dashboard')
  await page.waitForTimeout(1000)
  await page.screenshot({ path: `test-results/entity-dashboard-${suffix}.png`, fullPage: true })
})
