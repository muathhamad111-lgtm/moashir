import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { MOCK_PASSWORD } from './support/signIn'

const NEXT = 'Najd-Plateau-2026!'
const ASSESSOR = { id: 7101, roles: ['assessor'], name: { ar: 'نورة بنت سعد', en: 'Noura Saad' } }
const ENTITY = { id: 7601, roles: ['entity'], entityId: 6001, name: 'نورة بنت فهد العتيبي' }

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function pick(testInfo, ar, en) {
  return isEn(testInfo) ? en : ar
}

async function fillPasswordForm(form, current) {
  await form.locator('input[name="currentPassword"]').fill(current)
  await form.locator('input[name="password"]').fill(NEXT)
  await form.locator('input[name="passwordConfirmation"]').fill(NEXT)
  await form.locator('button[type="submit"]').click()
}

async function expectSavedInPlace(page, testInfo, path) {
  const form = page.getByTestId('change-password-form')
  await fillPasswordForm(form, 'Not-The-Password-1')
  await expect(form).toContainText(
    pick(testInfo, 'كلمة المرور الحالية غير صحيحة.', 'The current password is incorrect.'),
  )
  await expect(page.getByTestId('change-password-saved')).toHaveCount(0)

  await fillPasswordForm(form, MOCK_PASSWORD)
  await expect(page.getByTestId('change-password-saved')).toContainText(
    pick(testInfo, 'حُفظت كلمة المرور الجديدة', 'new password was saved'),
  )
  await expect(page).toHaveURL(new RegExp(`${path}$`))
  await expect(form.locator('input[name="currentPassword"]')).toHaveValue('')
}

test('an assessor changes the password on the account page without leaving it', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, ASSESSOR, { locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/account')
  await expect(page.locator('.account-page__role-list .badge').first()).toBeVisible()
  await expect(page.locator('.permission-module')).toHaveCount(0)
  await expectSavedInPlace(page, testInfo, '/account')
})

test('an entity account changes the password on the entity profile page without leaving it', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, ENTITY, { locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/entity/profile')
  await expect(page.getByTestId('entity-profile')).toBeVisible({ timeout: 10000 })
  await expectSavedInPlace(page, testInfo, '/entity/profile')
})
