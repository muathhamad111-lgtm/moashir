import process from 'node:process'
import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'

const OUTPUT = process.env.MOASHIR_DIALOG_SCREENS
const PHASE = process.env.MOASHIR_DIALOG_PHASE ?? 'after'
const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const UNDER_REVIEW = '3f0c9a52-6d1e-4b7a-9c21-5a1e00000022'
const PDF = Buffer.from('%PDF-1.4\n% Accredited evidence\n%%EOF\n')

const USERS = {
  admin: { id: 2, roles: ['system_admin'] },
  finance: { id: 22, roles: ['finance_officer'] },
  entity: { id: 6001, roles: ['entity'], entityId: 6001 },
}

const VARIANTS = [
  { name: 'ar-light', locale: 'ar', theme: 'light', viewport: DESKTOP },
  { name: 'ar-dark', locale: 'ar', theme: 'dark', viewport: DESKTOP },
  { name: 'en-light', locale: 'en', theme: 'light', viewport: DESKTOP },
  { name: 'ar-mobile-390', locale: 'ar', theme: 'light', viewport: MOBILE },
]

const dialog = (page) => page.locator('.modal__panel').last()

async function capture(page, flow, variant) {
  await settleFonts(page)
  await page.screenshot({ path: `${OUTPUT}/${flow}-${PHASE}-${variant.name}.png` })
}

async function prepare(page, user, variant) {
  await page.setViewportSize(variant.viewport)
  await seedSignedIn(page, user, { locale: variant.locale, theme: variant.theme })
}

function pdf(name) {
  return { name, mimeType: 'application/pdf', buffer: PDF }
}

async function completeAccreditedForm(page, variant) {
  await page.goto('/assessment/accredited/form/9101')
  await page.locator('.station-row__button').nth(3).click()
  const governance = page.locator('#accredited-sub-402')
  await governance
    .locator('.evidence-file--refused .evidence-upload__input')
    .setInputFiles(pdf('audited-statements-2025-final.pdf'))
  await page.locator('.station-row__button').nth(7).click()
  const impact = page.locator('#accredited-sub-802')
  await impact
    .locator('textarea')
    .fill('تقيس الجهة أثر برامجها سنويًا وفق منهجية العائد الاجتماعي على الاستثمار.')
  await impact
    .locator('.evidence-slots__slot')
    .first()
    .locator('.evidence-upload__input')
    .setInputFiles(pdf('impact-report-2026.pdf'))
  await expect(impact.locator('.scan-status').first()).toContainText(
    variant.locale === 'en' ? 'Clean' : 'سليم',
    { timeout: 20000 },
  )
  await page.locator('.accredited-actions .btn--secondary').click()
  await expect(page.locator('.accredited-completeness')).toHaveClass(
    /accredited-completeness--done/,
  )
}

async function openBankAccountDelete(page, variant) {
  await page.goto('/admin/settings/payment')
  const scope = variant.viewport === MOBILE ? '.bank-account-card' : '.bank-accounts__table'
  await page.locator(`${scope} .dropdown-menu > button`).first().click()
  await page
    .getByRole('menuitem', { name: variant.locale === 'en' ? 'Delete account' : 'حذف الحساب' })
    .click()
  await expect(dialog(page)).toBeVisible()
}

test.describe.configure({ mode: 'serial' })

for (const variant of VARIANTS) {
  test(`dialog screens · ${variant.name}`, async ({ page }) => {
    test.setTimeout(180000)

    await prepare(page, USERS.entity, variant)
    await completeAccreditedForm(page, variant)
    await page.locator('.accredited-actions .btn--primary').click()
    await expect(dialog(page)).toBeVisible()
    await capture(page, 'submit-request', variant)
    await dialog(page).locator('.modal__footer .btn--primary').click()
    await expect(page).toHaveURL(/\/assessment\/accredited$/)
    await expect(page.locator('.modal__panel, .toast--success').last()).toBeVisible()
    await capture(page, 'submit-success', variant)

    await prepare(page, USERS.finance, variant)
    await page.goto(`/finance/transfers/${UNDER_REVIEW}`)
    await expect(page.locator('.transfer-review__title')).toBeVisible()
    await page.locator('.transfer-review__decisions .btn--primary').click()
    await expect(dialog(page)).toBeVisible()
    await capture(page, 'approve-transfer', variant)
    await page.keyboard.press('Escape')
    await page.locator('.transfer-review__decisions .btn--secondary').click()
    await expect(dialog(page)).toBeVisible()
    await capture(page, 'reject-transfer', variant)
    await page.keyboard.press('Escape')

    await prepare(page, USERS.admin, variant)
    await openBankAccountDelete(page, variant)
    await capture(page, 'delete-bank-account', variant)
  })
}
