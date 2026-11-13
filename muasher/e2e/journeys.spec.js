/* eslint-disable playwright/no-wait-for-timeout */
import { test, expect } from '@playwright/test'
import { DEV_OTP_CODE, requestSignInCode, submitSignIn, typeCode } from './support/signIn'
import { seedSignedIn, seedLocale } from './support/session'

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function localeOnly(page, locale) {
  if (locale === 'en') {
    return seedLocale(page, 'en')
  }
  return Promise.resolve()
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

async function gotoWithRetry(page, path, selector) {
  await page.goto(path)
  for (let attempt = 0; attempt < 12; attempt += 1) {
    if (
      await page
        .locator(selector)
        .first()
        .isVisible()
        .catch(() => false)
    )
      return
    const retry = page.locator('.error-state__retry')
    if (
      await retry
        .first()
        .isVisible()
        .catch(() => false)
    )
      await retry
        .first()
        .click()
        .catch(() => {})
    await page.waitForTimeout(500)
  }
}

async function waitForRetry(page, selector) {
  for (let attempt = 0; attempt < 12; attempt += 1) {
    if (
      await page
        .locator(selector)
        .first()
        .isVisible()
        .catch(() => false)
    )
      return
    const retry = page.locator('.error-state__retry')
    if (
      await retry
        .first()
        .isVisible()
        .catch(() => false)
    )
      await retry
        .first()
        .click()
        .catch(() => {})
    await page.waitForTimeout(500)
  }
}

test.describe('Journeys — Notes 8a/8b/8c', () => {
  test.describe.configure({ retries: 2 })

  test('Note 8a: an authenticated entity reaches the self-assessment screen directly from the CTA', async ({
    page,
  }, testInfo) => {
    await seedEntity(page, isEn(testInfo) ? 'en' : 'ar')
    await gotoWithRetry(page, '/', '.home-paths')
    const cta = page.locator('.home-path--calm .home-path__cta .btn')
    await expect(cta).toBeVisible({ timeout: 15000 })
    await cta.click()
    await page.waitForURL(/\/assessment\/self$/, { timeout: 15000 })
    await waitForRetry(page, '.self-form__title')
    await expect(page.locator('.self-form__title')).toBeVisible({ timeout: 15000 })
  })

  test('Note 8a: an unauthenticated self-assess CTA bounces to login with a redirect back', async ({
    page,
  }, testInfo) => {
    await localeOnly(page, isEn(testInfo) ? 'en' : 'ar')
    await gotoWithRetry(page, '/', '.home-paths')
    const cta = page.locator('.home-path--calm .home-path__cta .btn')
    await expect(cta).toBeVisible({ timeout: 15000 })
    await cta.click()
    await expect(page).toHaveURL(/\/auth\/login\?redirect=(%2F|\/)assessment(%2F|\/)self/)
    await expect(page.locator('.auth-form__title')).toBeVisible({ timeout: 15000 })
  })

  test('Note 8a: after a successful login the entity returns to the self-assessment screen', async ({
    page,
  }, testInfo) => {
    await localeOnly(page, isEn(testInfo) ? 'en' : 'ar')
    await gotoWithRetry(page, '/auth/login?redirect=%2Fassessment%2Fself', '.auth-form__body')
    await submitSignIn(page, 'info@albirr-riyadh.example.sa')
    await page.waitForURL(/\/assessment\/self$/, { timeout: 15000 })
    await waitForRetry(page, '.self-form__title')
    await expect(page.locator('.self-form__title')).toBeVisible({ timeout: 15000 })
  })

  test('Note 8a: the code sign-in keeps the redirect back to the self-assessment screen', async ({
    page,
  }, testInfo) => {
    await localeOnly(page, isEn(testInfo) ? 'en' : 'ar')
    await gotoWithRetry(page, '/auth/login?redirect=%2Fassessment%2Fself', '.auth-form__body')
    await requestSignInCode(page, '7100005001')
    await expect(page).toHaveURL(/\/auth\/otp\?redirect=/)
    await typeCode(page, DEV_OTP_CODE)
    await page.waitForURL(/\/assessment\/self$/, { timeout: 15000 })
    await waitForRetry(page, '.self-form__title')
    await expect(page.locator('.self-form__title')).toBeVisible({ timeout: 15000 })
  })

  test('Note 8b: the accredited Intro renders CMS-managed benefits with the live fee', async ({
    page,
  }, testInfo) => {
    await seedEntity(page, isEn(testInfo) ? 'en' : 'ar')
    await gotoWithRetry(page, '/assessment/accredited', '.accredited-intro__layout')
    await expect(page.locator('.accredited-intro__title')).toBeVisible()
    await expect(page.locator('.accredited-price .pricing-breakdown__row--total')).toContainText(
      '1,150.00',
    )
    await expect(page.locator('.accredited-intro__benefit').first()).toBeVisible({ timeout: 15000 })
  })

  test('Note 8b: a successful payment lands on the verified result page', async ({
    page,
  }, testInfo) => {
    await seedEntity(page, isEn(testInfo) ? 'en' : 'ar')
    await page.goto('/assessment/accredited/pay')
    await page.locator('.checkout__start .btn--primary').click()
    await expect(page.locator('[data-fake-moyasar="form"]')).toBeVisible({ timeout: 15000 })
    await page.locator('.fake-moyasar__pay').click()
    await expect(page.locator('.payment-outcome--paid')).toBeVisible({ timeout: 15000 })
  })

  test('Note 8c: the advanced Intro renders CMS-managed award brief + winners', async ({
    page,
  }, testInfo) => {
    await seedEntity(page, isEn(testInfo) ? 'en' : 'ar')
    await gotoWithRetry(page, '/assessment/advanced', '.advanced-intro__layout')
    await expect(page.locator('.advanced-intro__title')).toBeVisible()
    await expect(page.locator('.advanced-content__item').first()).toBeVisible({ timeout: 15000 })
    await expect(page.locator('.advanced-content__card')).toHaveCount(2)
    await expect(page.locator('.advanced-content__group')).toHaveCount(2)
  })
})
