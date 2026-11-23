import { test, expect } from '@playwright/test'
import { seedScenario, seedSignedIn } from './support/session'

const ASSESSOR = {
  id: 21,
  roles: ['assessor'],
  name: { ar: 'فهد بن سعد القحطاني', en: 'Fahad Al-Qahtani' },
}
const SYSTEM_ADMIN = {
  id: 2,
  roles: ['system_admin'],
  name: { ar: 'منيرة بنت خالد العتيبي', en: 'Munira Al-Otaibi' },
}
const SUMMARY = {
  ar: 'إضافة بند يمنع تصوير الشاشة.',
  en: 'A clause that forbids screenshots was added.',
}

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function say(testInfo, en, ar) {
  return isEn(testInfo) ? en : ar
}

function seed(page, testInfo, user) {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

function scrollAgreementToEnd(page) {
  return page.locator('[data-testid="nda-text"]').evaluate((text) => {
    text.scrollTop = text.scrollHeight
    text.dispatchEvent(new Event('scroll'))
  })
}

test.describe('staff non-disclosure agreement', () => {
  test('a pending staff member cannot dismiss the agreement and continues once it is accepted', async ({
    page,
  }, testInfo) => {
    await seedScenario(page, 'nda-pending')
    await seed(page, testInfo, ASSESSOR)
    await page.goto('/account')

    const dialog = page.getByRole('dialog')
    await expect(dialog).toBeVisible()
    await expect(dialog).toContainText(say(testInfo, 'Non-disclosure', 'عدم الإفصاح'))
    await expect(dialog.locator('.modal__close')).toHaveCount(0)

    await page.keyboard.press('Escape')
    await page.mouse.click(5, 5)
    await expect(dialog).toBeVisible()

    const accept = page.locator('[data-testid="nda-accept"]')
    await expect(accept).toBeDisabled()
    await scrollAgreementToEnd(page)
    await accept.click()

    await expect(dialog).toBeHidden()
    await expect(page).toHaveURL(/\/account$/)
    await expect(page.locator('[data-testid="my-agreements"]')).toContainText('1.0')
  })

  test('an administrator publishes a new version and follows the acceptance register', async ({
    page,
  }, testInfo) => {
    await seed(page, testInfo, SYSTEM_ADMIN)
    await page.goto('/admin/settings/nda')
    await expect(page.locator('[data-testid="nda-versions"]')).toContainText('1.0')

    await page.locator('[data-testid="nda-versions"] .legal-admin__header .btn--primary').click()
    await page.locator('.legal-editor button[type="submit"]').click()
    await expect(page.locator('[data-testid="nda-open-publish"]')).toBeVisible()
    await page.locator('[data-testid="nda-open-publish"]').click()

    const publish = page.locator('[data-testid="nda-publish"]')
    await expect(publish).toContainText(say(testInfo, 'will be asked to accept', 'سيُطلب من'))
    await page.locator('textarea[name="changeSummary.ar"]').fill(SUMMARY.ar)
    await page.locator('textarea[name="changeSummary.en"]').fill(SUMMARY.en)
    await page
      .locator('textarea[name="reason"]')
      .fill(say(testInfo, 'Legal review of the clauses.', 'مراجعة البنود من الشؤون القانونية.'))
    await page.locator('[name="confirmIrreversible"]').check()
    await page.locator('.modal form button[type="submit"]').click()
    await expect(page.getByRole('dialog')).toContainText(
      say(testInfo, 'was published', 'نُشرت النسخة'),
    )

    // The mocks live in the page, so the register is opened in place rather than by reloading.
    await page.keyboard.press('Escape')
    await page.locator('.nda-admin__actions a').click()
    const register = page.locator('[data-testid="nda-register"]')
    await expect(register).toBeVisible()
    await page
      .getByRole('radio', { name: say(testInfo, 'Pending acceptance', 'بانتظار الموافقة') })
      .click()
    await expect(register).toContainText('منيرة بنت خالد العتيبي')
    await expect(register.locator('.stats-card').first()).toContainText('0')
  })
})
