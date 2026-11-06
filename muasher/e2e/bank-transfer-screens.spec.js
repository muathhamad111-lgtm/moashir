import process from 'node:process'
import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'

const OUTPUT = process.env.MOASHIR_BANK_TRANSFER_SCREENS
const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const UNDER_REVIEW = '3f0c9a52-6d1e-4b7a-9c21-5a1e00000022'
const REJECTED = '3f0c9a52-6d1e-4b7a-9c21-5a1e00000024'

const USERS = {
  admin: { id: 2, roles: ['system_admin'] },
  finance: { id: 22, roles: ['finance_officer'] },
  entity: { id: 5001, roles: ['entity'], entityId: 5001 },
  rejectedEntity: { id: 6005, roles: ['entity'], entityId: 6005 },
}

const VARIANTS = [
  { theme: 'light', viewport: DESKTOP, device: 'desktop' },
  { theme: 'dark', viewport: DESKTOP, device: 'desktop' },
  { theme: 'light', viewport: MOBILE, device: 'mobile' },
]

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

async function capture(page, name, testInfo, variant) {
  await settleFonts(page)
  await expect(page.locator('[aria-busy="true"], .skeleton')).toHaveCount(0)
  const file = `${name}-${localeOf(testInfo)}-${variant.theme}-${variant.device}.png`
  await page.screenshot({ path: `${OUTPUT}/${file}`, fullPage: true })
}

async function prepare(page, testInfo, user, variant) {
  await page.setViewportSize(variant.viewport)
  await seedSignedIn(page, user, { locale: localeOf(testInfo), theme: variant.theme })
}

for (const variant of VARIANTS) {
  test(`bank-transfer screens · ${variant.theme} · ${variant.device}`, async ({
    page,
  }, testInfo) => {
    test.setTimeout(120000)
    await prepare(page, testInfo, USERS.admin, variant)
    await page.goto('/admin/settings/payment')
    await expect(
      page
        .locator(
          variant.device === 'mobile' ? '.bank-account-card' : '.bank-accounts__table tbody tr',
        )
        .first(),
    ).toBeVisible()
    await capture(page, 'a1-payment-settings', testInfo, variant)

    await prepare(page, testInfo, USERS.entity, variant)
    await page.goto('/assessment/accredited/pay')
    await expect(page.locator('.method-option').first()).toBeVisible()
    await capture(page, 'b1-choose-method', testInfo, variant)
    await page.locator('.method-option[data-value="bank_transfer"]').click()
    await page.locator('.checkout__start .btn--primary').click()
    await page.locator('input[type="file"][name="proof"]').setInputFiles({
      name: 'bank-transfer-receipt.pdf',
      mimeType: 'application/pdf',
      buffer: Buffer.from('%PDF-1.4\n%%EOF\n'),
    })
    await capture(page, 'b2-bank-transfer', testInfo, variant)
    await page.locator('.transfer-panel__submit .btn--primary').click()
    await expect(page.locator('.transfer-status--info')).toBeVisible()
    await capture(page, 'b5-under-review', testInfo, variant)

    await prepare(page, testInfo, USERS.rejectedEntity, variant)
    await page.goto(`/payments/${REJECTED}/complete`)
    await expect(page.locator('.transfer-status--danger')).toBeVisible()
    await capture(page, 'b6-rejected', testInfo, variant)
    await page.goto('/payments')
    await expect(
      page.locator(variant.device === 'mobile' ? '.my-payment-card' : '.my-payments__row').first(),
    ).toBeVisible()
    await capture(page, 'b9-my-payments', testInfo, variant)

    await prepare(page, testInfo, USERS.finance, variant)
    await page.goto('/finance/transfers')
    await expect(
      page.locator(variant.device === 'mobile' ? '.transfer-card' : '.transfer-queue__row').first(),
    ).toBeVisible()
    await capture(page, 'c1-review-queue', testInfo, variant)
    await page.goto(`/finance/transfers/${UNDER_REVIEW}`)
    await expect(page.locator('.proof-viewer__image')).toBeVisible()
    await capture(page, 'c2-review-detail', testInfo, variant)
    await page.locator('.transfer-review__decisions .btn--secondary').click()
    await expect(page.locator('.modal__panel')).toBeVisible()
    await capture(page, 'c4-reject-dialog', testInfo, variant)
    await page.keyboard.press('Escape')
    await page.goto('/finance/transactions')
    await expect(page.locator('.transaction-filters')).toBeVisible()
    await expect(page.locator('.transactions-table__link').first()).toBeAttached()
    await capture(page, 'c5-transactions', testInfo, variant)
  })
}
