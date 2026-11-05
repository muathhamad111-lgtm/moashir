import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

const IGNORED_CONSOLE = /Failed to load resource|40\d|50\d/i
const EXAMPLE_IBAN = 'SA03 8000 0000 6080 1016 7519'
const NEW_IBAN = 'SA53 0000 0000 0012 3456 7890'
const WRONG_IBAN = 'SA03 8000 0000 6080 1016 7518'
const REASON = 'Approved after matching the fees account statement.'
const MOBILE = { width: 390, height: 844 }

const USERS = {
  admin: {
    id: 2,
    roles: ['system_admin'],
    name: { ar: 'منيرة بنت خالد العتيبي', en: 'Munira Al-Otaibi' },
  },
  finance: { id: 22, roles: ['finance_officer'] },
  superAdmin: { id: 900, roles: [], superAdmin: true },
  entity: {
    id: 5001,
    roles: ['entity'],
    entityId: 5001,
    name: { ar: 'أحمد بن سالم القحطاني', en: 'Ahmed Salem Al-Qahtani' },
  },
}

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function signIn(page, testInfo, user) {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

function trackConsole(page) {
  const errors = []
  page.on('console', (message) => {
    if (['error', 'warning'].includes(message.type()) && !IGNORED_CONSOLE.test(message.text()))
      errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))
  return errors
}

function receipt(name = 'bank-transfer-receipt.pdf') {
  return { name, mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n1 0 obj\n%%EOF\n') }
}

const dialog = (page) => page.locator('.modal__panel').last()
const successToast = (page) => page.locator('.toast--success').last()

async function chooseChannels(page, choice) {
  await page.locator(`.channels-card .method-option[data-value="${choice}"]`).click()
  await page.locator('.channels-card__actions .btn').click()
  await expect(successToast(page)).toBeVisible()
}

async function startTransfer(page) {
  await page.goto('/assessment/accredited/pay')
  await page.locator('.method-option[data-value="bank_transfer"]').click()
  await page.locator('.checkout__start .btn--primary').click()
  await expect(page.locator('.transfer-panel')).toBeVisible()
}

async function sendTransfer(page) {
  await page.locator('input[type="file"][name="proof"]').setInputFiles(receipt())
  await expect(page.locator('.proof-chip .badge')).toBeVisible()
  await page.locator('.transfer-panel__submit .btn--primary').click()
  await expect(page).toHaveURL(/\/payments\/[0-9a-f-]{36}\/complete$/)
  await expect(page.locator('.transfer-status--info')).toBeVisible()
  return page.url().split('/').at(-2)
}

async function openReview(page, paymentId) {
  await page.goto(`/finance/transfers/${paymentId}`)
  await expect(page.locator('.transfer-review__title')).toBeVisible()
}

test('the administrator adds a bank account and offers both payment channels', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await signIn(page, testInfo, USERS.admin)
  await page.goto('/admin/settings/payment')
  await expect(page.locator('.bank-accounts__table tbody tr')).toHaveCount(1)
  await expect(page.locator('.bank-accounts__table')).toContainText(EXAMPLE_IBAN)
  await chooseChannels(page, 'electronic')

  await page.locator('.bank-accounts-card__header .btn--primary').click()
  const form = dialog(page)
  await form.locator('[name="bankName.ar"]').fill('مصرف العيّنة')
  await form.locator('[name="bankName.en"]').fill('Sample Bank')
  await form.locator('[name="accountHolderName.ar"]').fill('منصة مؤشر — حساب الرسوم')
  await form.locator('[name="accountHolderName.en"]').fill('Moashir Platform — fees account')
  await form.locator('[name="iban"]').fill(WRONG_IBAN.replaceAll(' ', '').toLowerCase())
  await expect(form.locator('[name="iban"]')).toHaveValue(WRONG_IBAN)
  await form.locator('button[type="submit"]').click()
  await expect(form).toContainText(isEn(testInfo) ? 'check digits do not match' : 'خانتا التحقق')
  await form.locator('[name="iban"]').fill(NEW_IBAN)
  await form.locator('button[type="submit"]').click()
  await expect(page.locator('.modal__panel')).toHaveCount(0)
  await expect(page.locator('.bank-accounts__table tbody tr')).toHaveCount(2)
  await expect(page.locator('.bank-accounts__table tbody tr').nth(1)).toContainText(NEW_IBAN)

  await chooseChannels(page, 'both')
  await expect(
    page.locator('.channels-card .method-option[data-value="bank_transfer"]'),
  ).toContainText(isEn(testInfo) ? '2 active accounts' : 'حسابان نشطان')
  await page.screenshot({
    path: `test-results/bank-transfer-settings-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  expect(errors).toEqual([])
})

test('the entity pays by transfer, Finance rejects it, and a new request is approved', async ({
  page,
  context,
}, testInfo) => {
  test.setTimeout(90000)
  const errors = trackConsole(page)
  await context.grantPermissions(['clipboard-read', 'clipboard-write'])
  await signIn(page, testInfo, USERS.entity)
  await startTransfer(page)
  await expect(page.locator('.checkout-summary__pending')).toContainText(
    isEn(testInfo) ? 'created when you send' : 'يُنشأ عند الإرسال',
  )
  await expect(page.locator('.transfer-panel__submit .btn--primary')).toBeDisabled()
  await page.locator('[data-copy="iban"] button').click()
  expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(
    EXAMPLE_IBAN.replaceAll(' ', ''),
  )
  const first = await sendTransfer(page)
  await expect(page.locator('.transfer-progress [aria-current="step"]')).toBeVisible()

  await signIn(page, testInfo, USERS.finance)
  await openReview(page, first)
  await page.locator('.transfer-review__decisions .btn--secondary').click()
  await dialog(page).locator('label.radio:has(input[value="not_received"])').click()
  await dialog(page)
    .locator('textarea[name="note"]')
    .fill('The amount has not reached the fees account yet.')
  await dialog(page).locator('button[type="submit"]').click()
  await expect(page.locator('.transfer-decision-line--rejected')).toBeVisible()

  await signIn(page, testInfo, USERS.entity)
  await page.goto(`/payments/${first}/complete`)
  await expect(page.locator('.transfer-status--danger')).toBeVisible()
  await expect(page.locator('.transfer-status__reason')).toContainText(
    isEn(testInfo) ? 'Not found in the bank statement' : 'لم يظهر التحويل',
  )
  await expect(page.locator('.transfer-status')).not.toContainText('الدواسر')
  await page.screenshot({
    path: `test-results/bank-transfer-rejected-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await page.locator('.transfer-status a.btn--primary').click()
  await expect(page).toHaveURL(/\/assessment\/accredited\/pay$/)
  await page.locator('.method-option[data-value="bank_transfer"]').click()
  await page.locator('.checkout__start .btn--primary').click()
  const second = await sendTransfer(page)
  expect(second).not.toBe(first)

  await signIn(page, testInfo, USERS.finance)
  await openReview(page, second)
  await page.locator('.transfer-review__decisions .btn--primary').click()
  await dialog(page).locator('button[type="submit"]').click()
  await expect(successToast(page)).toBeVisible()
  await expect(page.locator('.transfer-decision-line')).toBeVisible()

  await signIn(page, testInfo, USERS.entity)
  await page.goto(`/payments/${second}`)
  await expect(page.locator('.receipt-card--test')).toBeVisible()
  await expect(page.locator('.transfer-record')).toBeVisible()
  await page.goto('/payments')
  await expect(page.locator('.my-payments__table')).toContainText(
    isEn(testInfo) ? 'Bank transfer' : 'تحويل بنكي',
  )
  expect(errors).toEqual([])
})

test('the entity cancels an unbound card order to pay by transfer instead', async ({
  page,
}, testInfo) => {
  await signIn(page, testInfo, USERS.entity)
  await page.goto('/assessment/accredited/pay')
  await page.locator('.checkout__start .btn--primary').click()
  await expect(page.locator('[data-fake-moyasar="form"]')).toBeVisible()
  const reference = await page.locator('.checkout-summary__reference bdi').textContent()
  await page.locator('.checkout__change').click()
  await expect(dialog(page)).toContainText(reference)
  await dialog(page).locator('.modal__footer .btn--primary').click()
  await expect(page.locator('.method-option[data-value="bank_transfer"]')).toHaveClass(
    /is-selected/,
  )
  await page.locator('.checkout__start .btn--primary').click()
  await expect(page.locator('.transfer-panel')).toBeVisible()
  await page.goto('/payments?tab=cancelled')
  await expect(page.locator('.my-payments__table')).toContainText(reference)
})

test('Finance approves the selected transfers at once', async ({ page }, testInfo) => {
  const errors = trackConsole(page)
  await signIn(page, testInfo, USERS.finance)
  await page.goto('/finance/transfers')
  await expect(page.locator('.transfer-queue__table tbody tr')).toHaveCount(3)
  await expect(page.locator('.transfer-queue__table tbody tr').nth(1)).toContainText(
    isEn(testInfo) ? 'Duplicate file' : 'ملف مكرر',
  )
  await page.locator('.transfer-queue__table thead .selection-checkbox').click()
  await expect(page.locator('.transfer-bulk-bar')).toContainText(
    isEn(testInfo) ? '3 selected' : 'تم تحديد 3',
  )
  await page.screenshot({
    path: `test-results/bank-transfer-queue-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await page.locator('.transfer-bulk-bar .btn--primary').click()
  await expect(dialog(page)).toContainText('PAY-2026-000021')
  await dialog(page).locator('button[type="submit"]').click()
  await expect(page.locator('.bulk-outcome__line--success')).toContainText(
    isEn(testInfo) ? 'decided: 3' : 'المنفَّذ: 3',
  )
  await expect(page.locator('.transfer-queue .empty-state')).toBeVisible()
  expect(errors).toEqual([])
})

test('a bank account with transfers awaiting approval cannot be deleted until they are decided', async ({
  page,
}, testInfo) => {
  test.setTimeout(90000)
  await signIn(page, testInfo, USERS.admin)
  await page.goto('/admin/settings/payment')
  await page.locator('.bank-accounts__table .dropdown-menu > button').first().click()
  await page
    .getByRole('menuitem', { name: isEn(testInfo) ? 'Delete account' : 'حذف الحساب' })
    .click()
  await dialog(page).locator('.modal__footer .btn--danger').click()
  const blocked = dialog(page)
  await expect(blocked.locator('.pending-transfers__table tbody tr')).toHaveCount(3)
  await expect(blocked).toContainText(
    isEn(testInfo) ? 'A transfer reviewer must approve or reject them first.' : 'مراجع الحوالات',
  )
  await expect(blocked.locator('input[type="checkbox"]')).toHaveCount(0)
  await blocked.locator('.modal__footer .btn--secondary').click()

  await signIn(page, testInfo, USERS.superAdmin)
  await page.goto('/admin/settings/payment')
  await page.locator('.channels-card .method-option[data-value="electronic"]').click()
  await page.locator('.channels-card__actions .btn').click()
  await dialog(page).locator('[name="reason"]').fill(REASON)
  await dialog(page).locator('button[type="submit"]').click()
  await expect(successToast(page)).toBeVisible()
  await page.locator('.bank-accounts__table .dropdown-menu > button').first().click()
  await page
    .getByRole('menuitem', { name: isEn(testInfo) ? 'Delete account' : 'حذف الحساب' })
    .click()
  await dialog(page).locator('.modal__footer .btn--danger').click()
  await dialog(page).locator('[name="reason"]').fill(REASON)
  await dialog(page).locator('button[type="submit"]').click()
  const reviewer = page.locator('.modal__panel:has(.pending-transfers)')
  await expect(reviewer.locator('.pending-transfers__table tbody tr')).toHaveCount(3)
  await reviewer.locator('thead .selection-checkbox').click()
  await reviewer.locator('.transfer-bulk-bar .btn--primary').click()
  await dialog(page).locator('[name="reason"]').fill(REASON)
  await dialog(page).locator('button[type="submit"]').click()
  await expect(reviewer.locator('.pending-transfers__cleared')).toBeVisible()
  await page.screenshot({ path: `test-results/bank-transfer-blocked-${suffixOf(testInfo)}.png` })
  await reviewer.locator('.modal__footer .btn--danger').click()
  await dialog(page).locator('[name="reason"]').fill(REASON)
  await dialog(page).locator('button[type="submit"]').click()
  await expect(successToast(page)).toBeVisible()
  await expect(page.locator('.bank-accounts-card .empty-state')).toBeVisible()
  await page.locator('.channels-card .method-option[data-value="bank_transfer"]').click()
  await expect(page.locator('.channels-card__alert--danger')).toBeVisible()
  await expect(page.locator('.channels-card__actions .btn')).toBeDisabled()
})

test('on a phone the entity transfer page and the Finance queue keep their actions within reach', async ({
  page,
}, testInfo) => {
  await page.setViewportSize(MOBILE)
  await signIn(page, testInfo, USERS.entity)
  await startTransfer(page)
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth - window.innerWidth,
  )
  expect(overflow).toBeLessThanOrEqual(1)
  await page.locator('input[type="file"][name="proof"]').setInputFiles(receipt())
  const send = page.locator('.transfer-panel__submit .btn--primary')
  await expect(send).toBeEnabled()
  const box = await send.boundingBox()
  expect(box.height).toBeGreaterThanOrEqual(44)
  await page.screenshot({
    path: `test-results/bank-transfer-mobile-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await signIn(page, testInfo, USERS.finance)
  await page.goto('/finance/transfers')
  await expect(page.locator('.transfer-card')).toHaveCount(3)
  await expect(page.locator('.transfer-queue__table-wrap')).toBeHidden()
  await page.locator('.transfer-queue__select-all .selection-checkbox').click()
  await expect(page.locator('.transfer-bulk-bar')).toBeVisible()
})
