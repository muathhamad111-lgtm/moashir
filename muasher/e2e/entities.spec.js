import { test, expect } from '@playwright/test'
import { seedLocale, seedSignedIn } from './support/session'
import { clearMockFault, forceMockFault } from './support/mockFaults'
import { chooseNamed, namedSelect } from './support/dropdown'

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
  await page.locator('[name="officialEmail"]').fill('info@alwafa-charity.example.sa')
  await page.locator('[name="password"]').fill('Wafa-Charity-Account-2026')
  await page.locator('[name="passwordConfirmation"]').fill('Wafa-Charity-Account-2026')
  await page.locator('[name="name.ar"]').fill('جمعية الوفاء الخيرية')
  await page.locator('[name="name.en"]').fill('Al-Wafa Charity')
  await page.locator('[name="unifiedNumber"]').fill('7000000911')
  await page.locator('[name="authorizedPersonName"]').fill('سلمان بن فهد الحربي')
  await chooseNamed(page, 'entityType', 'civil_association')
  await chooseNamed(page, 'developmentSector', 'social')
  await chooseNamed(page, 'orgSize', 'medium')
  await page.locator('[name="foundingYear"]').fill('2016')
  await expect(page.locator('[name="cityId"]')).toBeDisabled()
  await chooseNamed(page, 'regionId', '2')
  await chooseNamed(page, 'cityId', '12')
  await page.locator('[name="governanceGrade"]').fill('84.5')
}

test('an entity registers with terms, verifies the OTP and lands on its profile', async ({
  page,
}, testInfo) => {
  await seedLocale(page, localeOf(testInfo))
  await page.goto('/auth/register')
  await expect(page.locator('[name="officialEmail"]')).toBeVisible()
  await namedSelect(page, 'entityType').click()
  await expect(page.locator('[role="listbox"] [role="option"]')).toHaveCount(3)
  await page.keyboard.press('Escape')
  await fillRegistration(page)
  await expect(page.locator('[name="marketingConsent"]')).not.toBeChecked()

  await page.locator('form button[type="submit"]').click()
  await expect(
    checkbox(page, 'termsAccepted').locator('xpath=..').locator('.field__error'),
  ).toBeVisible()
  await page.locator('.accordion__trigger').first().click()
  await expect(page.locator('.entity-form__terms')).toBeVisible()
  await checkbox(page, 'termsAccepted').click()
  await page.screenshot({
    path: `test-results/entities-register-${suffix(testInfo)}.png`,
    fullPage: true,
  })

  await page.locator('form button[type="submit"]').click()
  await expect(page).toHaveURL(/\/auth\/otp/)
  await typeCode(page, DEV_OTP_CODE)
  await expect(page).toHaveURL(/\/entity\/profile\?welcome=1/)
  await expect(page.locator('.entity-profile-hub .portal-area__title')).toContainText(
    isEn(testInfo) ? 'Al-Wafa Charity' : 'جمعية الوفاء الخيرية',
  )
  await expect(page.locator('.completion-prompt')).toBeVisible()
})

test('the entity edits its profile, saves the basic tab and sees the change history entry', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, ENTITY, { locale: localeOf(testInfo) })
  await page.goto('/entity/profile')
  await expect(page.locator('[name="governanceGrade"]')).toHaveValue('86.50')
  await page.screenshot({
    path: `test-results/entities-profile-${suffix(testInfo)}.png`,
    fullPage: true,
  })

  await page.locator('[name="governanceGrade"]').fill('90.125')
  await page.locator('[name="governanceGrade"]').blur()
  await expect(page.locator('.field--error [name="governanceGrade"]')).toBeVisible()

  await page.locator('[name="governanceGrade"]').fill('91.25')
  await expect(page.locator('.field--error [name="governanceGrade"]')).toHaveCount(0)
  await page.locator('.portal-area__actions .btn--primary').click()
  await expect(page.locator('.toast--success')).toBeVisible()
  await expect(page.locator('[name="governanceGrade"]')).toHaveValue('91.25')
  await expect(page.locator('.profile-history tbody tr').first()).toContainText('91.25')
})

test('the official e-mail change is verified by OTP and waits for approval', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, ENTITY, { locale: localeOf(testInfo) })
  await page.goto('/entity/profile?tab=contact')
  await page.locator('.official-email .btn').click()
  await page.locator('.modal [name="newEmail"]').fill('info@albirr-riyadh.example.sa')
  await page.locator('.modal button[type="submit"]').click()
  await expect(page.locator('.modal .field--error [name="newEmail"]')).toBeVisible()
  await page.locator('.modal [name="newEmail"]').fill('office@albirr-riyadh.example.sa')
  await page.locator('.modal button[type="submit"]').click()
  await typeCode(page, DEV_OTP_CODE)
  await expect(page.locator('.email-change--done')).toContainText('o***@albirr-riyadh.example.sa')
  await page.locator('.email-change--done .btn').click()
  await expect(page.locator('.official-email__pending')).toBeVisible()
  await expect(page.locator('.official-email .btn')).toHaveCount(0)
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
  await expect(page.locator('.entity-profile-hub .portal-area__title')).toBeVisible()
})
