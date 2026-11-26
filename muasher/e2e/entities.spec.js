import { test, expect } from '@playwright/test'
import { seedLocale, seedSignedIn } from './support/session'
import { clearMockFault, forceMockFault } from './support/mockFaults'
import { namedSelect } from './support/dropdown'
import {
  attachAuthorizationLetter,
  fillRegistrationEntityStep,
  goToRegistrationAccountStep,
} from './support/registration'
import { approvePendingProfileChange } from './support/profileChanges'

const DEV_OTP_CODE = '482913'
const ENTITY = {
  id: 5001,
  roles: ['entity'],
  entityId: 5001,
  name: { ar: 'أحمد بن سالم القحطاني', en: 'Ahmed Salem Al-Qahtani' },
}

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffix(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function localeOf(testInfo) {
  return isEn(testInfo) ? 'en' : 'ar'
}

function checkbox(page, name) {
  return page.locator(`label.checkbox:has([name="${name}"])`)
}

async function typeCode(page, code) {
  await page.locator('.otp-input__box').first().click()
  await page.keyboard.type(code)
}

async function fillRegistration(page) {
  await expect(page.locator('[name="cityId"]')).toBeDisabled()
  await fillRegistrationEntityStep(page, { governanceGrade: '84.5' })
  await goToRegistrationAccountStep(page)
  await page.locator('[name="authorizedPersonName"]').fill('سلمان بن فهد الحربي')
  await attachAuthorizationLetter(page)
  await page.locator('[name="officialEmail"]').fill('info@alwafa-charity.example.sa')
  await page.locator('[name="password"]').fill('Wafa-Charity-Account-2026')
  await page.locator('[name="passwordConfirmation"]').fill('Wafa-Charity-Account-2026')
}

test('an entity proves its e-mail inside the form, registers and is told it is under review', async ({
  page,
}, testInfo) => {
  await seedLocale(page, localeOf(testInfo))
  await page.goto('/auth/register')
  await expect(page.locator('[name="unifiedNumber"]')).toBeVisible()
  await namedSelect(page, 'entityType').click()
  await expect(page.locator('[role="listbox"] [role="option"]')).toHaveCount(3)
  await page.keyboard.press('Escape')
  await fillRegistration(page)
  await expect(page.locator('[name="marketingConsent"]')).toHaveCount(0)
  const policy = page.locator('#registration-password-policy')
  await expect(policy.locator('[data-rule="length"]')).toHaveAttribute('data-state', 'met')
  await expect(policy.locator('[data-rule="match"]')).toHaveAttribute('data-state', 'met')
  const emailBox = await page.locator('.email-check__field [name="officialEmail"]').boundingBox()
  const sendBox = await page
    .locator('.email-check__field')
    .getByTestId('email-check-send')
    .boundingBox()
  expect(sendBox.x > emailBox.x + emailBox.width / 2).toBe(isEn(testInfo))
  expect(sendBox.x + sendBox.width < emailBox.x + emailBox.width / 2).toBe(!isEn(testInfo))
  await checkbox(page, 'termsAccepted').click()

  await page.locator('form button[type="submit"]').click()
  const check = page.getByTestId('registration-email-check')
  await expect(check.locator('.field__error')).toContainText(
    isEn(testInfo) ? 'Verify the official e-mail first' : 'تحقق من البريد الرسمي أولًا',
  )

  await page.getByTestId('email-check-send').click()
  await typeCode(page, '000000')
  await expect(check.locator('.field__error')).toBeVisible()
  await typeCode(page, DEV_OTP_CODE)
  await expect(page.getByTestId('email-check-done')).toBeVisible()
  await page.screenshot({
    path: `test-results/entities-register-${suffix(testInfo)}.png`,
    fullPage: true,
  })

  await page.locator('form button[type="submit"]').click()
  const notice = page.getByTestId('registration-under-review')
  await expect(notice).toHaveAttribute('role', 'status')
  await expect(notice).toContainText(isEn(testInfo) ? 'reviewing it' : 'قيد المراجعة')
  await expect(page).toHaveURL(/\/auth\/register/)
})

test('the entity edits one profile section, staff approve it and it shows in the section history', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, ENTITY, { locale: localeOf(testInfo) })
  await page.goto('/entity/profile')
  const governance = page.locator('[data-section="governance"]')
  await expect(governance).toContainText('86.50')
  await page.screenshot({
    path: `test-results/entities-profile-${suffix(testInfo)}.png`,
    fullPage: true,
  })

  await governance.locator('.profile-section__edit').click()
  await governance.locator('[name="governanceGrade"]').fill('100.5')
  await governance.locator('[name="governanceGrade"]').blur()
  await expect(governance.locator('.field--error [name="governanceGrade"]')).toBeVisible()

  await governance.locator('[name="governanceGrade"]').fill('91.25')
  await expect(governance.locator('.field--error [name="governanceGrade"]')).toHaveCount(0)
  await governance.locator('button[type="submit"]').click()
  await expect(governance.locator('.profile-section__status-text--success')).toBeVisible()
  await expect(governance.getByTestId('section-pending')).toBeVisible()
  await expect(governance).toContainText('86.50')

  await approvePendingProfileChange(page, localeOf(testInfo))
  await seedSignedIn(page, ENTITY, { locale: localeOf(testInfo) })
  await page.goto('/entity/profile')
  await expect(governance).toContainText('91.25')
  await governance.locator('.profile-section__history').click()
  await expect(page.getByTestId('profile-history-entry').first()).toContainText('91.25')
})

test('the official e-mail change is verified by OTP and waits for approval', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, ENTITY, { locale: localeOf(testInfo) })
  await page.goto('/entity/profile')
  await page.getByTestId('email-change-open').click()
  await page.locator('.modal [name="newEmail"]').fill('info@albirr-riyadh.example.sa')
  await page.locator('.modal button[type="submit"]').click()
  await expect(page.locator('.modal .field--error [name="newEmail"]')).toBeVisible()
  await page.locator('.modal [name="newEmail"]').fill('office@albirr-riyadh.example.sa')
  await page.locator('.modal button[type="submit"]').click()
  await typeCode(page, DEV_OTP_CODE)
  await expect(page.locator('.email-change--done')).toContainText('o***@albirr-riyadh.example.sa')
  await page.locator('.email-change--done .btn').click()
  await expect(page.getByTestId('email-change-cancel')).toBeVisible()
  await expect(page.getByTestId('email-change-open')).toHaveCount(0)
})

test('a forced server error shows the error state and the retry recovers the profile', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, ENTITY, { locale: localeOf(testInfo) })
  await forceMockFault(page, { method: 'GET', path: '/api/v1/entities/me', status: 503 })
  await page.goto('/entity/profile')
  await expect(page.locator('.error-state')).toBeVisible()
  await clearMockFault(page)
  await page.locator('.error-state__retry .btn').click()
  await expect(page.locator('.profile-header__name')).toBeVisible()
})
