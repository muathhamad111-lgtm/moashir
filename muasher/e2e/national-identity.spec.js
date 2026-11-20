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
const VALID_ID = '1000000008'
const BAD_ID = '1000000009'
const REASON_TEXT = 'Correcting a spelling mistake in the registered name.'

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function say(testInfo, en, ar) {
  return isEn(testInfo) ? en : ar
}

function seed(page, testInfo, user) {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

async function submit(page, scope = page) {
  await scope.locator('button[type="submit"]').click()
}

test('a required account adds its national ID once, then asks to change its name', async ({
  page,
}, testInfo) => {
  await seedScenario(page, 'national-id-required')
  await seed(page, testInfo, ASSESSOR)
  await page.goto('/account')

  await expect(page).toHaveURL(/\/account\/complete-identity/)
  await expect(page.locator('[data-testid="complete-identity"] h1')).toHaveText(
    say(testInfo, 'Complete your identity details', 'أكمل بيانات هويتك'),
  )
  const field = page.locator('input[name="nationalId"]')
  await field.fill(BAD_ID)
  await submit(page)
  await expect(page.locator('[data-testid="complete-identity"]')).toContainText(
    say(
      testInfo,
      'Enter a valid 10-digit national ID or Iqama number.',
      'أدخل رقم هوية وطنية أو إقامة صحيحًا من 10 أرقام.',
    ),
  )
  await field.fill(VALID_ID)
  await submit(page)

  await expect(page).toHaveURL(/\/account$/)
  await expect(page.locator('[data-testid="national-id-masked"]')).toHaveText('••••••0008')

  await page.getByRole('button', { name: say(testInfo, 'Request a change', 'طلب تعديل') }).click()
  const form = page.locator('.modal__panel')
  await form.locator('input[name="newName"]').fill('Fahad Saad Al-Qahtani')
  await form.locator('textarea[name="reason"]').fill(REASON_TEXT)
  await submit(page, form)

  await expect(page.locator('.national-identity-card__pending')).toContainText(
    say(testInfo, 'under review', 'قيد المراجعة'),
  )
})

test('a reviewer opens a pending identity change and approves it', async ({ page }, testInfo) => {
  await seedScenario(page, 'identity-change-pending')
  await seed(page, testInfo, SYSTEM_ADMIN)
  await page.goto('/admin/identity-changes')

  const row = page.locator('.table:not(.table--loading) tbody .table__row').first()
  await expect(row).toContainText('Laura Bennett')
  await row
    .getByRole('button', { name: new RegExp(say(testInfo, 'View request', 'عرض الطلب')) })
    .click()
  const detail = page.locator('[data-testid="identity-change-detail"]')
  await expect(detail).toContainText('Laura M. Bennett')
  await detail.getByRole('button', { name: say(testInfo, 'Approve', 'اعتماد') }).click()

  const decision = page.locator('.modal__panel').last()
  await submit(page, decision)
  await expect(page.locator('.toast').first()).toContainText(
    say(testInfo, 'The change was approved and applied.', 'اعتُمد التعديل وطُبّق.'),
  )
  await expect(page.locator('.table:not(.table--loading) tbody .table__row')).toHaveCount(0)
})

test('the System Administrator chooses which accounts must give a national ID', async ({
  page,
}, testInfo) => {
  await seed(page, testInfo, SYSTEM_ADMIN)
  await page.goto('/admin/settings/sign-in')
  const card = page.locator('[data-testid="verification-settings"]')
  await expect(card).toContainText(say(testInfo, 'National ID on files', 'رقم الهوية على الملفات'))

  await card.locator('label:has([name="nationalIdEnabled"])').click()
  await card.locator('label:has([name="nationalIdRoles-assessor"])').click()
  await expect(card.locator('[data-testid="national-id-uncovered"]')).toBeVisible()
  await expect(card).toContainText(
    say(testInfo, 'Assessor (opens files)', 'المقيّم (يطّلع على الملفات)'),
  )

  const saved = page.waitForResponse(
    (response) =>
      new URL(response.url()).pathname === '/api/v1/settings/auth' &&
      response.request().method() === 'PUT',
  )
  await card.locator('button[type="submit"]').click()
  expect((await saved).status()).toBe(200)
})
