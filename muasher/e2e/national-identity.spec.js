import { test, expect } from '@playwright/test'
import { seedScenario, seedSignedIn } from './support/session'
import { searchList } from './support/listFilters'

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
const FOLLOWER = { id: 8, roles: ['request_follower', 'filtering_officer'] }
const ENTITY_OWNER = {
  id: 6001,
  roles: ['entity'],
  entityId: 6001,
  name: { ar: 'نورة بنت فهد العتيبي', en: 'Noura Fahad Al-Otaibi' },
}
const VALID_ID = '1000000008'
const BAD_ID = '3000000008'

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

function completion(page) {
  return page.locator('.modal__panel').filter({ has: page.getByTestId('account-completion') })
}

test('a required account is asked in a modal, can defer it, and sends its number for approval', async ({
  page,
}, testInfo) => {
  await seedScenario(page, 'national-id-required')
  await seed(page, testInfo, ASSESSOR)
  await page.goto('/account')

  await expect(page).toHaveURL(/\/account$/)
  const modal = completion(page)
  await expect(modal).toContainText(
    say(testInfo, 'Complete your account details', 'أكمل بيانات حسابك'),
  )
  await expect(modal).toContainText(
    say(
      testInfo,
      'Starts with 1 or 2, 10 digits, digits only',
      'يبدأ بالرقم 1 أو 2، 10 أرقام، أرقام فقط',
    ),
  )
  const field = modal.locator('input[name="nationalId"]')
  await expect(field).toHaveAttribute('maxlength', '10')
  await field.pressSequentially('1a-2b')
  await expect(field).toHaveValue('12')
  await field.fill(BAD_ID)
  await submit(page, modal)
  await expect(modal).toContainText(
    say(
      testInfo,
      'The national ID or Iqama number starts with 1 or 2.',
      'رقم الهوية أو الإقامة يبدأ بالرقم 1 أو 2.',
    ),
  )

  await modal.getByRole('button', { name: say(testInfo, 'Later', 'لاحقًا') }).click()
  await expect(modal).toBeHidden()
  const banner = page.getByTestId('account-completion-banner')
  await expect(banner).toBeVisible()
  await banner.getByTestId('account-completion-action').click()

  await completion(page).locator('input[name="nationalId"]').fill(VALID_ID)
  await submit(page, completion(page))
  await expect(page.locator('.toast').first()).toContainText(
    say(
      testInfo,
      'Your number was sent to the administration for approval.',
      'أُرسل رقمك إلى الإدارة للاعتماد.',
    ),
  )
  await expect(page.getByTestId('national-id-pending')).toHaveText(
    say(testInfo, 'Awaiting approval', 'بانتظار الاعتماد'),
  )
  await expect(banner).toBeHidden()
})

test('a staff account without a gender completes it from the modal', async ({ page }, testInfo) => {
  await seedScenario(page, 'account-completion')
  await seed(page, testInfo, ASSESSOR)
  await page.goto('/account')

  const modal = completion(page)
  await expect(modal.locator('input[name="nationalId"]')).toHaveCount(0)
  await modal.locator('label:has(input[name="gender"][value="male"])').click()
  await submit(page, modal)
  await expect(page.locator('.toast').first()).toContainText(
    say(testInfo, 'Your account details were saved.', 'حُفظت بيانات حسابك.'),
  )
  await expect(modal).toBeHidden()
  await expect(page.getByTestId('account-completion-banner')).toHaveCount(0)
})

test('the administrator invites a staff member with gender and national ID and sees them in the details', async ({
  page,
}, testInfo) => {
  await seed(page, testInfo, SYSTEM_ADMIN)
  await page.goto('/admin/settings/users')
  await page
    .getByRole('button', { name: /invite user|دعوة مستخدم/i })
    .first()
    .click()
  const form = page.locator('.modal__panel')
  await form.locator('input[name="name"]').fill('نورة بنت سالم العنزي')
  await form.locator('input[name="email"]').fill('noura.alanazi@moashir.example.sa')
  await form.locator('label:has(input[name="gender"][value="female"])').click()
  await form.locator('input[name="nationalId"]').fill('1000000505')
  await form.locator('.user-form__roles label').first().click()
  await submit(page, form)
  await expect(form).toBeHidden()

  await searchList(page, 'noura.alanazi')
  const name = 'نورة بنت سالم العنزي'
  await page
    .locator('.table tbody .table__row', { hasText: name })
    .locator('[data-action="details"]')
    .click()
  const details = page.getByTestId('user-details')
  await expect(details.getByTestId('user-national-id')).toHaveText('••••••0505')
  await expect(details.getByTestId('user-gender')).toHaveText(say(testInfo, 'Female', 'أنثى'))
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
  await expect(card).toContainText(
    say(testInfo, 'National ID on assessment evidence', 'رقم الهوية على شواهد التقييم'),
  )

  await card.locator('label:has([name="nationalIdEnabled"])').click()
  await card.locator('label:has([name="nationalIdRoles-assessor"])').click()
  await expect(card.locator('[data-testid="national-id-uncovered"]')).toBeVisible()
  await expect(card).toContainText(
    say(testInfo, 'Assessor (opens assessment evidence)', 'المقيّم (يطّلع على الشواهد)'),
  )

  const saved = page.waitForResponse(
    (response) =>
      new URL(response.url()).pathname === '/api/v1/settings/auth' &&
      response.request().method() === 'PUT',
  )
  await card.locator('button[type="submit"]').click()
  expect((await saved).status()).toBe(200)
})

test('an entity account sends its national ID for approval from the modal on the entity profile', async ({
  page,
}, testInfo) => {
  await seedScenario(page, 'national-id-required')
  await seed(page, testInfo, ENTITY_OWNER)
  await page.goto('/entity/profile')

  await expect(page).toHaveURL(/\/entity\/profile$/)
  const modal = completion(page)
  await expect(modal.locator('input[name="gender"]')).toHaveCount(0)
  await modal.locator('input[name="nationalId"]').fill(VALID_ID)
  await submit(page, modal)

  await expect(page.getByTestId('profile-completeness')).toBeVisible()
  const card = page.locator('.entity-profile__national-identity')
  await expect(card).toContainText(say(testInfo, 'National ID', 'رقم الهوية'))
  await expect(card.getByTestId('national-id-pending')).toHaveText(
    say(testInfo, 'Awaiting approval', 'بانتظار الاعتماد'),
  )
})

test('a required follower without an approved number cannot prepare the evidence bundle', async ({
  page,
}, testInfo) => {
  await seedScenario(page, 'national-id-required')
  await seed(page, testInfo, FOLLOWER)
  await page.goto('/admin/requests/9108/review')

  await completion(page)
    .getByRole('button', { name: say(testInfo, 'Later', 'لاحقًا') })
    .click()
  await expect(page.locator('.review-header')).toBeVisible()
  await page.locator('.review-page__title-row .btn--secondary').click()
  const refusal = page.getByTestId('file-access-refusal')
  await expect(refusal).toContainText(
    say(
      testInfo,
      'You can open or download assessment evidence once you enter your national ID',
      'لا يمكنك فتح شواهد التقييم أو تنزيلها قبل إدخال رقم هويتك',
    ),
  )
  await expect(page).toHaveURL(/\/admin\/requests\/9108\/review$/)

  await page.getByTestId('file-access-enter-id').click()
  const modal = completion(page)
  await modal.locator('input[name="nationalId"]').fill(VALID_ID)
  await submit(page, modal)
  await expect(page.locator('.toast').first()).toContainText(
    say(
      testInfo,
      'Your number was sent to the administration for approval.',
      'أُرسل رقمك إلى الإدارة للاعتماد.',
    ),
  )
  await page.locator('.review-page__title-row .btn--secondary').click()
  await expect(page.getByTestId('file-access-refusal')).toContainText(
    say(testInfo, 'awaiting approval', 'بانتظار اعتماد الإدارة'),
  )
  await expect(page.getByTestId('file-access-enter-id')).toHaveCount(0)
})

test('a finance reviewer opens a transfer receipt as uploaded, with no national ID asked', async ({
  page,
}, testInfo) => {
  await seedScenario(page, 'national-id-required')
  await seed(page, testInfo, { id: 22, roles: ['finance_officer'] })
  await page.goto('/finance/transfers/3f0c9a52-6d1e-4b7a-9c21-5a1e00000022')

  await expect(page.locator('.document-viewer__image')).toBeVisible()
  await expect(page.getByTestId('account-completion')).toHaveCount(0)
  await expect(page.getByTestId('file-access-refusal')).toHaveCount(0)
})
