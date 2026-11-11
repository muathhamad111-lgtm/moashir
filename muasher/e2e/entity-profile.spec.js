import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { clearMockFault, forceMockFault } from './support/mockFaults'
import { closeSuccess, decisionAction } from './support/dialogs'
import { AA_TEXT, contrastOf } from './support/contrast'
import { settleFonts } from './support/screenshots'
import { DEV_OTP_CODE } from './support/signIn'

const SHOTS = '../agents/tasks/reports/Task-82b'
const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'

function entity(id, name) {
  return { id, roles: ['entity'], entityId: id, name }
}

const NAMAA = entity(6001, { ar: 'نورة بنت فهد العتيبي', en: 'Noura Fahad Al-Otaibi' })
const COMPLETE = entity(5001, { ar: 'أحمد بن سالم القحطاني', en: 'Ahmed Salem Al-Qahtani' })
const SUSPENDED = entity(6003, { ar: 'خالد بن عبدالله الدوسري', en: 'Khalid Abdullah Al-Dosari' })
const PENDING_EMAIL = entity(6005, { ar: 'فيصل بن ناصر المطيري', en: 'Faisal Nasser Al-Mutairi' })

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function dirOf(testInfo) {
  return localeOf(testInfo) === 'en' ? 'en-ltr' : 'ar-rtl'
}

async function openProfile(page, testInfo, user, { theme = 'light', viewport = DESKTOP } = {}) {
  await seedSignedIn(page, user, { locale: localeOf(testInfo), theme })
  await page.setViewportSize(viewport)
  await page.goto('/entity/profile')
  await expect(page.getByTestId('profile-completeness')).toBeVisible()
  await settleFonts(page)
}

function section(page, key) {
  return page.locator(`[data-section="${key}"]`)
}

async function shoot(page, testInfo, name) {
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await page.mouse.move(0, 0)
  const viewport = page.viewportSize()
  const height = await page.evaluate(() => document.documentElement.scrollHeight)
  await page.setViewportSize({ width: viewport.width, height })
  await page.screenshot({ path: `${SHOTS}/${name}-${dirOf(testInfo)}.png`, animations: 'disabled' })
  await page.setViewportSize(viewport)
}

test('reads the incomplete profile, edits one section and saves it', async ({ page }, testInfo) => {
  await openProfile(page, testInfo, NAMAA)
  await expect(page.getByTestId('portal-page-title')).toHaveText(
    localeOf(testInfo) === 'en' ? 'Entity profile' : 'ملف الجهة',
  )
  await expect(page.getByTestId('profile-completeness')).toContainText('60')
  await expect(section(page, 'governance').getByTestId('profile-missing')).toHaveCount(3)
  await expect(page.getByTestId('attention-strip')).not.toContainText(/60/)
  await shoot(page, testInfo, '01-desktop-incomplete')

  const governance = section(page, 'governance')
  await governance.locator('.profile-section__edit').click()
  await expect(governance.locator('[name="governanceGrade"]')).toBeFocused()
  await governance.locator('[name="governanceGrade"]').fill('82.50')
  await governance.locator('[name="employeesCount"]').fill('14')
  await governance.locator('[name="annualBeneficiariesCount"]').fill('3200')
  await expect(governance.locator('.profile-section__status-text--warning')).toBeVisible()
  await shoot(page, testInfo, '02-desktop-edit')
  await governance.locator('button[type="submit"]').click()

  await expect(governance.locator('.profile-section__status-text--success')).toBeVisible()
  await expect(governance.locator('.profile-section__edit')).toBeFocused()
  await expect(governance.getByTestId('profile-section-state')).toHaveClass(/--complete/)
  await expect(page.getByTestId('profile-completeness')).toContainText('80')
  await shoot(page, testInfo, '03-desktop-saved')
})

test('«Add» opens the section focused on that field', async ({ page }, testInfo) => {
  await openProfile(page, testInfo, NAMAA)
  const identity = section(page, 'identity')
  await identity.locator('[data-field="licenseNumber"] .profile-missing__add').click()
  await expect(identity.locator('[name="licenseNumber"]')).toBeFocused()
  await expect(identity.locator('.field--required [name="unifiedNumber"]')).toHaveCount(1)
  await expect(identity.locator('.field--required [name="licenseNumber"]')).toHaveCount(0)
  await identity.locator('.profile-section__next-missing').click()
  await expect(identity.locator('[name="foundedOn"]')).toBeFocused()
})

test('shows field errors and the summary when the section is invalid', async ({
  page,
}, testInfo) => {
  await openProfile(page, testInfo, NAMAA)
  const governance = section(page, 'governance')
  await governance.locator('.profile-section__edit').click()
  await governance.locator('[name="governanceGrade"]').fill('120')
  await governance.locator('[name="employeesCount"]').fill('-3')
  await governance.locator('button[type="submit"]').click()
  await expect(governance.getByTestId('profile-error-summary')).toBeVisible()
  await expect(governance.locator('.field--error [name="governanceGrade"]')).toBeVisible()
  await expect(governance.locator('.field--error [name="employeesCount"]')).toBeVisible()
  await shoot(page, testInfo, '04-desktop-validation-error')
})

test('a stale save reloads the section with a short message', async ({ page }, testInfo) => {
  await openProfile(page, testInfo, NAMAA)
  const governance = section(page, 'governance')
  await governance.locator('.profile-section__edit').click()
  await governance.locator('[name="employeesCount"]').fill('14')
  await forceMockFault(page, {
    method: 'PATCH',
    path: '/api/v1/entities/me/profile/governance',
    status: 409,
    code: 'profile.section_stale',
  })
  await governance.locator('button[type="submit"]').click()
  await expect(governance.getByTestId('profile-stale')).toBeVisible()
  await expect(governance.locator('[name="employeesCount"]')).toHaveValue('')
  await shoot(page, testInfo, '05-desktop-stale-409')
  await clearMockFault(page)
  await governance.locator('[name="employeesCount"]').fill('14')
  await governance.locator('button[type="submit"]').click()
  await expect(governance.locator('.profile-section__status-text--success')).toBeVisible()
})

test('a suspended account reads the profile without any edit action', async ({
  page,
}, testInfo) => {
  await openProfile(page, testInfo, SUSPENDED)
  await expect(page.getByTestId('profile-read-only')).toBeVisible()
  await expect(page.locator('.profile-section__edit')).toHaveCount(0)
  await expect(page.locator('.profile-missing__add')).toHaveCount(0)
  await expect(page.getByTestId('profile-change-logo')).toHaveCount(0)
  await expect(page.getByTestId('profile-complete-action')).toHaveCount(0)
  await expect(page.getByTestId('email-change-open')).toHaveCount(0)
  await shoot(page, testInfo, '06-desktop-read-only')
})

test('the entity cancels its pending e-mail change', async ({ page }, testInfo) => {
  await openProfile(page, testInfo, PENDING_EMAIL)
  const email = page.getByTestId('profile-official-email')
  await expect(email.locator('.badge')).toBeVisible()
  await shoot(page, testInfo, '07-desktop-pending-email')
  await email.getByTestId('email-change-cancel').click()
  await decisionAction(page).click()
  await closeSuccess(page)
  await expect(email.getByTestId('email-change-cancel')).toHaveCount(0)
  await expect(email.getByTestId('email-change-open')).toBeVisible()
})

test('a new mobile number is saved, then verified inline with a code', async ({
  page,
}, testInfo) => {
  await openProfile(page, testInfo, NAMAA)
  const contact = section(page, 'contact')
  await contact.getByTestId('profile-phone').locator('.profile-field__action').click()
  await expect(contact.locator('[name="phone"]')).toBeFocused()
  await contact.locator('[name="phone"]').fill('0501234567')
  await contact.locator('button[type="submit"]').click()
  const phone = contact.getByTestId('profile-phone')
  await expect(phone.locator('.badge')).toBeVisible()
  await phone.locator('.profile-field__action').click()
  await expect(phone.getByTestId('phone-otp')).toBeVisible()
  await shoot(page, testInfo, '08-desktop-unverified-phone-otp')
  await phone.locator('.otp-input__box').first().click()
  await page.keyboard.type(DEV_OTP_CODE)
  await expect(phone.getByTestId('phone-otp')).toHaveCount(0)
  await expect(phone.locator('.profile-field__action')).toHaveCount(0)
})

test('the history drawer opens filtered, closes with Esc and returns focus', async ({
  page,
}, testInfo) => {
  await openProfile(page, testInfo, COMPLETE)
  await expect(page.getByTestId('profile-complete-action')).toHaveCount(0)
  await shoot(page, testInfo, '09-desktop-complete')
  const trigger = section(page, 'governance').locator('.profile-section__history')
  await trigger.click()
  const drawer = page.getByTestId('profile-history-drawer')
  await expect(drawer).toBeVisible()
  await expect(drawer.locator('[aria-pressed="true"]')).toHaveCount(1)
  await expect(drawer.getByTestId('profile-history-entry').first()).toBeVisible()
  await shoot(page, testInfo, '10-desktop-history-drawer')
  await page.keyboard.press('Escape')
  await expect(drawer).toHaveCount(0)
  await expect(trigger).toBeFocused()
})

test('the 390 px layout puts completeness first and does not overflow', async ({
  page,
}, testInfo) => {
  await openProfile(page, testInfo, NAMAA, { viewport: MOBILE })
  const card = await page.getByTestId('profile-completeness').boundingBox()
  const first = await section(page, 'identity').boundingBox()
  expect(card.y).toBeLessThan(first.y)
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  )
  expect(overflow).toBeLessThanOrEqual(0)
  await shoot(page, testInfo, '11-mobile-incomplete')
  await section(page, 'governance').locator('.profile-section__edit').click()
  await section(page, 'governance').locator('[name="governanceGrade"]').fill('120')
  await section(page, 'governance').locator('button[type="submit"]').click()
  await expect(section(page, 'governance').getByTestId('profile-error-summary')).toBeVisible()
  await shoot(page, testInfo, '12-mobile-validation-error')
})

test('the 390 px read-only layout and the dark mode', async ({ page }, testInfo) => {
  await openProfile(page, testInfo, SUSPENDED, { viewport: MOBILE })
  await shoot(page, testInfo, '13-mobile-read-only')
  await expect(page.getByTestId('profile-read-only')).toBeVisible()
  await openProfile(page, testInfo, NAMAA, { theme: 'dark' })
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  await shoot(page, testInfo, '14-desktop-dark')
})

test('the compact facts row and the missing marker keep AA contrast', async ({
  page,
}, testInfo) => {
  await openProfile(page, testInfo, NAMAA)
  for (const selector of ['.profile-facts__label', '.profile-facts__value']) {
    expect(await contrastOf(page, selector)).toBeGreaterThanOrEqual(AA_TEXT)
  }
  for (const selector of ['.profile-missing__text', '.profile-missing__add']) {
    expect(await contrastOf(page, selector)).toBeGreaterThanOrEqual(AA_TEXT)
  }
  await expect(page.locator('.profile-missing__icon').first()).toBeVisible()
  await expect(page.locator('.profile-missing__text').first()).not.toBeEmpty()
})

test('the side-nav level card and the header use the eye icon for the certificate', async ({
  page,
}, testInfo) => {
  await openProfile(page, testInfo, NAMAA)
  await expect(page.getByTestId('level-mini-card-eye')).toBeVisible()
  const eye = page.getByTestId('profile-view-certificate')
  await eye.focus()
  await expect(page.locator('.tooltip__bubble')).toBeVisible()
  await eye.click()
  await expect(page).toHaveURL(/\/certificates/)
})
