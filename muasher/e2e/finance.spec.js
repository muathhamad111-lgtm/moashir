import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { applyOpenListFilters, openListFilters, searchList } from './support/listFilters'
import { closeSuccess, decisionAction } from './support/dialogs'

const IGNORED_CONSOLE = /Failed to load resource|40\d|50\d/i
const PAID_ACCREDITED = '3f0c9a52-6d1e-4b7a-9c21-5a1e00000001'
const PAID_BAG = '3f0c9a52-6d1e-4b7a-9c21-5a1e00000002'
const PENDING_LIVE = '3f0c9a52-6d1e-4b7a-9c21-5a1e00000008'

const STAFF = {
  financeOfficer: { id: 22, roles: ['finance_officer'] },
  systemAdmin: { id: 1, roles: ['system_admin'] },
  superAdmin: { id: 900, roles: [], superAdmin: true },
}

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function seedStaff(page, testInfo, user) {
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

function pdfFile(name = 'bank-transfer-receipt.pdf', text = '%PDF-1.4\n1 0 obj\n%%EOF\n') {
  return { name, mimeType: 'application/pdf', buffer: Buffer.from(text) }
}

async function fillRefund(page, file) {
  const dialog = page.getByRole('dialog')
  await expect(dialog).toBeVisible()
  await dialog.locator('input[name="transferReference"]').fill('TRF-2026-0142')
  await dialog
    .locator('textarea[name="reason"]')
    .fill('The entity withdrew before the assessor started the review.')
  await dialog.locator('input[type="file"][name="evidence"]').setInputFiles(file)
  return dialog
}

test('the finance section opens on the operations with their summary cards', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto('/finance')
  await expect(page).toHaveURL(/\/finance\/transactions$/)
  await expect(page.locator('.finance-overview__card')).toHaveCount(4)
  await expect(page.locator('.finance-nav__link')).toHaveCount(0)
  await expect(page.locator('.tabs__tab')).toHaveText([
    isEn(testInfo) ? 'Operations' : 'العمليات',
    new RegExp(isEn(testInfo) ? 'Awaiting approval' : 'بانتظار الاعتماد'),
  ])
  await expect(page.locator('thead th')).toHaveCount(7)
  await expect(page.locator('.table__row').first()).toBeVisible()
  await expect(page.locator('tbody')).not.toContainText('@')
  await page.screenshot({
    path: `test-results/finance-summary-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  expect(errors).toEqual([])
})

test('transactions are searched, filtered, sorted and exported as a CSV', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto('/finance/transactions')
  await expect(page.locator('.table__row')).toHaveCount(10)
  await expect(page.locator('.pagination')).toBeVisible()
  await page.screenshot({
    path: `test-results/finance-transactions-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await searchList(page, isEn(testInfo) ? 'Namaa' : 'نماء')
  await expect(page.locator('.table__row')).toHaveCount(4)
  await expect(
    page.locator('.table__row td:nth-child(2) .transactions-table__title').first(),
  ).toContainText(isEn(testInfo) ? 'Namaa' : 'نماء')
  await searchList(page, '')
  await expect(page.locator('.table__row')).toHaveCount(10)

  const filters = await openListFilters(page)
  await filters.locator('.dropdown-combobox').nth(1).click()
  await page.getByRole('option', { name: isEn(testInfo) ? 'Payment failed' : 'فشل الدفع' }).click()
  await page.keyboard.press('Escape')
  await expect(filters.locator('.dropdown-tag')).toHaveCount(1)
  await applyOpenListFilters(page)
  await expect(page.locator('.table__row')).toHaveCount(2)
  const download = page.waitForEvent('download')
  await page.locator('.filter-toolbar__controls .btn:has([data-icon="download"])').click()
  expect((await download).suggestedFilename()).toMatch(/^transactions-\d{8}-\d{6}\.csv$/)
  expect(errors).toEqual([])
})

async function chooseMode(page, testInfo, { en, ar }) {
  const filters = await openListFilters(page)
  await filters.locator('.transaction-filters__mode .dropdown-field').click()
  await page.getByRole('option', { name: isEn(testInfo) ? en : ar }).click()
  await applyOpenListFilters(page)
}

test('the default lists payments of both modes, and either mode is one filter away', async ({
  page,
}, testInfo) => {
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto('/finance/transactions')
  await expect(page.locator('.table__row')).toHaveCount(10)
  const testBadge = page.locator('.transactions-table__badges .badge--warning')
  await expect(testBadge.first()).toBeVisible()

  await chooseMode(page, testInfo, { en: 'Preview payments', ar: 'مدفوعات المعاينة' })
  await expect(testBadge.first()).toBeVisible()
  await expect(page.locator('.table__row').filter({ hasNot: testBadge })).toHaveCount(0)

  await chooseMode(page, testInfo, { en: 'Live payments', ar: 'المدفوعات الفعلية' })
  await expect(page.locator('.table__row').first()).toBeVisible()
  await expect(testBadge).toHaveCount(0)
})

test('a status on the overview cards filters the operations, and the columns are the viewer’s choice', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto('/finance/transactions')
  await expect(page.locator('.table__row')).toHaveCount(10)
  const failed = page
    .locator('.finance-overview__split-card')
    .first()
    .locator('.finance-overview__cell')
    .nth(4)
  const failedCount = await failed.locator('.finance-overview__count').textContent()
  await failed.click()
  await expect(page.locator('.table__row')).toHaveCount(Number(failedCount))
  await expect(page.locator('.filter-dialog__count')).toHaveText('1')

  await page.locator('.column-chooser > button').click()
  await page.getByRole('checkbox', { name: isEn(testInfo) ? 'Receipt no.' : 'رقم الإيصال' }).check()
  await page.getByRole('checkbox', { name: isEn(testInfo) ? 'Payment' : 'الدفع' }).uncheck()
  await page.keyboard.press('Escape')
  const headers = page.locator('thead th')
  await expect(headers).toHaveCount(7)
  await expect(
    headers.filter({ hasText: isEn(testInfo) ? 'Receipt no.' : 'رقم الإيصال' }),
  ).toHaveCount(1)
  await page.reload()
  await expect(
    page.locator('thead th').filter({ hasText: isEn(testInfo) ? 'Receipt no.' : 'رقم الإيصال' }),
  ).toHaveCount(1)
  expect(errors).toEqual([])
})

test('the eye opens the receipt over the list, with its PDF and the full page', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto('/finance/transactions?status=paid')
  await page.locator('.transactions-table__view').first().click()
  const receipt = page.locator('.receipt-modal__body')
  await expect(receipt).toBeVisible()
  await expect(page.locator('.modal__title')).toContainText(isEn(testInfo) ? 'Receipt' : 'إيصال')
  await expect(receipt.locator('.pricing-breakdown')).toBeVisible()
  await expect(receipt).not.toContainText('@')
  await page.screenshot({ path: `test-results/finance-receipt-modal-${suffixOf(testInfo)}.png` })
  const download = page.waitForEvent('download')
  await page.locator('.finance-modal__footer .btn--primary').click()
  expect((await download).suggestedFilename()).toMatch(/\.pdf$/)
  await page.locator('.modal__close').click()
  await expect(receipt).toHaveCount(0)
  await expect(page).toHaveURL(/\/finance\/transactions/)
  expect(errors).toEqual([])
})

test('a reviewer approves a queued transfer in place and rejects one from its review window', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto('/finance/transactions')
  await page.locator('.tabs__tab').nth(1).click()
  await expect(page).toHaveURL(/\/finance\/transfers$/)
  const rows = page.locator('.transfer-queue__table tbody tr')
  await expect(rows).toHaveCount(3)
  await rows.first().locator('.transfer-queue__approve').click()
  await decisionAction(page).click()
  await closeSuccess(page)
  await expect(rows).toHaveCount(2)

  await rows.first().locator('.transfer-queue__view').click()
  await expect(page.locator('.transfer-review-modal__layout')).toBeVisible()
  await page.screenshot({ path: `test-results/finance-transfer-modal-${suffixOf(testInfo)}.png` })
  await page.locator('.finance-modal__footer .btn--secondary').first().click()
  await expect(page.locator('.transfer-review-modal__layout')).toHaveCount(0)
  await page.locator('.modal__panel').getByRole('radio').first().check()
  await page.locator('.modal__footer .btn--danger').click()
  await closeSuccess(page)
  await expect(rows).toHaveCount(1)
  expect(errors).toEqual([])
})

test('the System Administrator sees the queue read-only and the events as a tab', async ({
  page,
}, testInfo) => {
  await seedStaff(page, testInfo, STAFF.systemAdmin)
  await page.goto('/finance/transfers')
  await expect(page.locator('.transfers-page__read-only')).toBeVisible()
  await expect(page.locator('.transfer-queue__approve')).toHaveCount(0)
  await expect(page.locator('.tabs__tab')).toHaveCount(3)
  await page.locator('.tabs__tab').nth(2).click()
  await expect(page).toHaveURL(/\/finance\/payment-events$/)
  await expect(page.locator('.payment-events__ref').first()).toBeVisible()
})

test('a large export is queued and downloaded once it is ready', async ({ page }, testInfo) => {
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto('/finance/transactions')
  await expect(page.locator('.table__row')).toHaveCount(10)
  await page.locator('.filter-toolbar__controls .btn:has([data-icon="download"])').click()
  await expect(page).toHaveURL(/\/finance\/exports\/[0-9a-f-]{36}$/)
  await expect(page.locator('.payment-notice--info')).toBeVisible()
  await expect(page.locator('.payment-notice--success')).toBeVisible({ timeout: 15000 })
  const download = page.waitForEvent('download')
  await page.locator('.payment-notice--success .btn--primary').click()
  expect((await download).suggestedFilename()).toMatch(/\.csv$/)
})

test('a full refund is recorded with a scanned transfer receipt and voids the payment receipt', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto(`/finance/transactions/${PAID_ACCREDITED}`)
  await expect(page.locator('.receipt-card__number')).toContainText('RCP-2026-000001')
  await page.locator('.transaction-detail__actions .btn--primary').click()
  const dialog = await fillRefund(page, pdfFile())
  await expect(dialog.locator('input[name="amount"]')).toHaveValue('1150.00')
  await expect(dialog.locator('input[name="amount"]')).toBeDisabled()
  await expect(dialog.locator('.refund-evidence__file')).toBeVisible()
  await page.screenshot({ path: `test-results/finance-refund-form-${suffixOf(testInfo)}.png` })
  await dialog.locator('button[type="submit"]').click()
  await expect(dialog).toHaveCount(0)
  await expect(page.locator('.refund-record__evidence .badge--success')).toBeVisible()
  await expect(page.locator('.receipt-card--voided')).toBeVisible()
  await expect(page.locator('.transaction-detail__actions .btn--primary')).toHaveCount(0)
  await page.screenshot({
    path: `test-results/finance-transaction-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  expect(errors).toEqual([])
})

test('a transfer receipt whose content does not match or that carries malware is refused', async ({
  page,
}, testInfo) => {
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto(`/finance/transactions/${PAID_BAG}`)
  await page.locator('.transaction-detail__actions .btn--primary').click()
  const dialog = await fillRefund(page, pdfFile('transfer.png'))
  await dialog.locator('button[type="submit"]').click()
  await expect(dialog.locator('.refund-evidence__refusal')).toBeVisible()
  await expect(dialog.locator('.refund-evidence__file .badge--danger')).toBeVisible()
  await dialog
    .locator('input[type="file"][name="evidence"]')
    .setInputFiles(pdfFile('scan.pdf', '%PDF-1.4 EICAR-STANDARD-ANTIVIRUS-TEST-FILE'))
  await dialog.locator('button[type="submit"]').click()
  await expect(dialog.locator('.refund-evidence__refusal')).toBeVisible()
  await expect(dialog).toBeVisible()
})

test('the receipt is reissued with a reason', async ({ page }, testInfo) => {
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto(`/finance/transactions/${PAID_BAG}`)
  await page.locator('.receipt-card__actions .btn--ghost').click()
  const dialog = page.getByRole('dialog')
  await dialog
    .locator('textarea[name="reason"]')
    .fill('A corrected copy was requested by the entity.')
  await dialog.locator('button[type="submit"]').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await expect(page.locator('.receipt-card__fact').last()).toContainText('1')
})

test('only the Super Admin settles an open order manually, with a reason', async ({
  page,
}, testInfo) => {
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto(`/finance/transactions/${PENDING_LIVE}`)
  await expect(page.locator('.transaction-detail__title')).toBeVisible()
  await expect(page.locator('.transaction-detail__actions .btn')).toHaveCount(0)

  await seedStaff(page, testInfo, STAFF.superAdmin)
  await page.goto(`/finance/transactions/${PENDING_LIVE}`)
  await page.locator('.transaction-detail__actions .btn--secondary').click()
  const dialog = page.getByRole('dialog')
  await dialog
    .locator('textarea[name="reason"]')
    .fill('Confirmed as paid in the Moyasar dashboard today.')
  await dialog.locator('button[type="submit"]').click()
  await expect(dialog).toHaveCount(0)
  await expect(page.locator('.transaction-detail__badges .badge--success')).toBeVisible()
  await expect(page.locator('.receipt-card__number')).toContainText('RCP-2026-')
})

test('the payment events log is for the System Administrator only', async ({ page }, testInfo) => {
  await seedStaff(page, testInfo, STAFF.systemAdmin)
  await page.goto('/finance/payment-events')
  await expect(page.locator('.table__row').first()).toBeVisible()
  await expect(page.locator('.payment-events__ref').first()).toContainText('…')
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto('/finance/payment-events')
  await expect(page).toHaveURL(/\/403$/)
})

test('refunds open from the operations, and abandoned carts are listed for their permission holders', async ({
  page,
}, testInfo) => {
  await seedStaff(page, testInfo, STAFF.systemAdmin)
  await page.goto('/finance/refunds')
  await expect(page).toHaveURL(/\/finance\/transactions$/)
  await page.goto('/finance/transactions?type=refund')
  await expect(page.locator('.table__row')).toHaveCount(1)
  await expect(page.locator('.filter-dialog__count')).toHaveText('1')
  await page.locator('.transactions-table__view').first().click()
  await expect(page.locator('.receipt-modal__body')).toBeVisible()
  await page.locator('.finance-modal__footer a:has([data-icon="link"])').click()
  await expect(page).toHaveURL(/\/finance\/transactions\/[0-9a-f-]{36}$/)
  await expect(page.locator('.transaction-events')).toBeVisible()
  const download = page.waitForEvent('download')
  await page.locator('.card:has(.refund-record) .card__footer .btn').click()
  expect((await download).suggestedFilename()).toBe('bank-transfer-receipt.pdf')
  await page.goto('/admin/settings/abandoned-carts')
  await expect(page.locator('.table__row')).toHaveCount(4)
  await expect(page.locator('.settings-form__contact').first()).toContainText('***@')
})

test('the general settings edit the VAT rate from the tax card', async ({ page }, testInfo) => {
  const errors = trackConsole(page)
  await seedStaff(page, testInfo, STAFF.systemAdmin)
  await page.goto('/admin/settings/finance')
  await expect(page).toHaveURL(/\/admin\/settings\/general$/)
  await expect(page.locator('input[name="vatRate"]')).toHaveValue('15.00')
  await page.screenshot({
    path: `test-results/finance-settings-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await page.locator('input[name="vatRate"]').fill('5')
  await page.locator('[data-testid="tax-settings"] .btn--primary').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await expect(page.locator('input[name="vatRate"]')).toHaveValue('5.00')
  await expect(page.locator('.finance-preview__card')).toHaveCount(0)
  expect(errors).toEqual([])
})

test('the Super Admin gives a reason before a finance setting changes', async ({
  page,
}, testInfo) => {
  await seedStaff(page, testInfo, STAFF.superAdmin)
  await page.goto('/admin/settings/general')
  await expect(page.locator('input[name="bagPrice"]')).toHaveCount(0)
  await page.locator('input[name="vatRate"]').fill('5')
  await page.locator('[data-testid="tax-settings"] .btn--primary').click()
  const dialog = page.getByRole('dialog')
  await dialog.locator('textarea[name="reason"]').fill('Approved VAT change for the next cycle.')
  await dialog.locator('button[type="submit"]').click()
  await expect(page.locator('input[name="vatRate"]')).toHaveValue('5.00')
})
