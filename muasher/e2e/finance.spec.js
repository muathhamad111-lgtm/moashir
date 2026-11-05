import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

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

test('the finance officer reads the live summary by service', async ({ page }, testInfo) => {
  const errors = trackConsole(page)
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto('/finance')
  await expect(page.locator('.stats-card')).toHaveCount(7)
  await expect(page.locator('.purpose-breakdown tbody tr')).toHaveCount(2)
  await expect(page.locator('.finance-nav__link')).toHaveCount(4)
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

  await page.locator('.toolbar__search input').fill(isEn(testInfo) ? 'Namaa' : 'نماء')
  await expect(page.locator('.table__row')).toHaveCount(4)
  await expect(page.locator('.transactions-table__entity-name').first()).toContainText(
    isEn(testInfo) ? 'Namaa' : 'نماء',
  )
  await page.locator('.toolbar__search input').fill('')
  await expect(page.locator('.table__row')).toHaveCount(10)

  const filters = page.locator('.transaction-filters .dropdown-filter__trigger')
  await filters.nth(0).click()
  await page.getByRole('option', { name: isEn(testInfo) ? 'Failed' : 'لم تكتمل' }).click()
  await expect(page.locator('.table__row')).toHaveCount(2)
  const download = page.waitForEvent('download')
  await page.locator('.finance-header__actions .btn').click()
  expect((await download).suggestedFilename()).toMatch(/^transactions-\d{8}-\d{6}\.csv$/)
  expect(errors).toEqual([])
})

test('a large export is queued and downloaded once it is ready', async ({ page }, testInfo) => {
  await seedStaff(page, testInfo, STAFF.financeOfficer)
  await page.goto('/finance/transactions')
  await expect(page.locator('.table__row')).toHaveCount(10)
  await page.locator('.finance-header__actions .btn').click()
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

test('refunds and abandoned carts are listed for their permission holders', async ({
  page,
}, testInfo) => {
  await seedStaff(page, testInfo, STAFF.systemAdmin)
  await page.goto('/finance/refunds')
  await expect(page.locator('.table__row')).toHaveCount(1)
  const download = page.waitForEvent('download')
  await page.locator('.refunds-table__evidence .btn').click()
  expect((await download).suggestedFilename()).toBe('bank-transfer-receipt.pdf')
  await page.goto('/admin/settings/abandoned-carts')
  await expect(page.locator('.table__row')).toHaveCount(4)
  await expect(page.locator('.settings-form__contact').first()).toContainText('***@')
})

test('finance settings edit prices and VAT with a server preview', async ({ page }, testInfo) => {
  const errors = trackConsole(page)
  await seedStaff(page, testInfo, STAFF.systemAdmin)
  await page.goto('/admin/settings/finance')
  await expect(page.locator('input[name="vatRate"]')).toHaveValue('15.00')
  await page.screenshot({
    path: `test-results/finance-settings-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await page.locator('input[name="vatRate"]').fill('5')
  await page.locator('.settings-section .btn--primary').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await expect(page.locator('input[name="vatRate"]')).toHaveValue('5.00')
  await expect(page.locator('.finance-preview__card').first()).toContainText('1,050.00')
  expect(errors).toEqual([])
})

test('the Super Admin gives a reason before a finance setting changes', async ({
  page,
}, testInfo) => {
  await seedStaff(page, testInfo, STAFF.superAdmin)
  await page.goto('/admin/settings/finance')
  await page.locator('input[name="bagPrice"]').fill('3000')
  await page.locator('.settings-section .btn--primary').click()
  const dialog = page.getByRole('dialog')
  await dialog.locator('textarea[name="reason"]').fill('Approved price list for the next cycle.')
  await dialog.locator('button[type="submit"]').click()
  await expect(page.locator('input[name="bagPrice"]')).toHaveValue('3000.00')
})
