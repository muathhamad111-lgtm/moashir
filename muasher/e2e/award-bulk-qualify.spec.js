import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

// «تأهيل كل من بلغ حد التأهل» (operator decision D-4, 2026-11-22): the filtering officer qualifies every request
// at the threshold in one confirmed action; the request below the threshold stays for an individual decision.

const OFFICER = {
  id: 13,
  roles: ['filtering_officer'],
  name: { ar: 'مسؤول التصفية', en: 'Filtering officer' },
}

const isEn = (testInfo) => testInfo.project.name === 'chromium-en-ltr'
const text = (testInfo, en, ar) => (isEn(testInfo) ? en : ar)

test.beforeEach(async ({ page }, testInfo) => {
  await seedSignedIn(page, OFFICER, { locale: isEn(testInfo) ? 'en' : 'ar' })
})

test('the filtering officer qualifies every request at the threshold at once', async ({
  page,
}, testInfo) => {
  await page.goto('/admin/award/filtering')
  await expect(page).toHaveURL(/\/admin\/award\/cycles\/31\/filtering/)
  const action = page.locator('[data-testid="bulk-qualify"]')
  await expect(action).toContainText('(1)')
  await action.click()
  const dialog = page.getByRole('alertdialog')
  await expect(dialog).toContainText('60.00%')
  await dialog
    .getByRole('button', { name: text(testInfo, 'Qualify the requests', 'تأهيل الطلبات') })
    .click()
  await expect(page.locator('.toast--success').first()).toContainText(
    text(testInfo, '1 requests were qualified.', 'أُهِّل 1 طلبًا.'),
  )
  await expect(action).toHaveCount(0)
})
