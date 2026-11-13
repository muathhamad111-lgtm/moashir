import { mkdirSync } from 'node:fs'
import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedScenario, seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'
import { closeSuccess, decisionAction } from './support/dialogs'
import { chooseNamed } from './support/dropdown'

const SHOTS = '../agents/tasks/reports/Task-80b'
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const OPENED_AT = new Date('2026-11-05T12:00:00+03:00')
const AFTER_WINDOW = new Date('2026-12-25T12:00:00+03:00')
const PDF = Buffer.from('%PDF-1.4\n% Task-80 transfer receipt\n%%EOF\n')

const USERS = {
  admin: {
    id: 2,
    roles: ['system_admin'],
    name: { ar: 'منيرة بنت خالد العتيبي', en: 'Munira Al-Otaibi' },
  },
  finance: { id: 22, roles: ['finance_officer'] },
  albirr: { id: 5001, roles: ['entity'], entityId: 5001 },
  ruwad: { id: 6005, roles: ['entity'], entityId: 6005 },
}

mkdirSync(SHOTS, { recursive: true })

const isEn = (testInfo) => testInfo.project.name === 'chromium-en-ltr'
const say = (testInfo, en, ar) => (isEn(testInfo) ? en : ar)
const suffix = (testInfo) => (isEn(testInfo) ? 'en-ltr' : 'ar-rtl')
const row = (page, key) => page.locator(`[data-testid="price-row-${key}"]`)

async function signIn(page, testInfo, user) {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar', theme: 'light' })
}

async function open(page, path, ready) {
  await page.goto(path)
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator(ready).first()).toBeVisible({ timeout: 15000 })
}

async function elementShot(locator, name, testInfo) {
  await settleFonts(locator.page())
  await locator.screenshot({ path: `${SHOTS}/${name}-${suffix(testInfo)}.png` })
}

async function shot(page, name, testInfo) {
  await settleFonts(page)
  await page.screenshot({ path: `${SHOTS}/${name}-${suffix(testInfo)}.png`, fullPage: true })
}

async function savePrice(page, key, { price, availability }) {
  if (price) await row(page, key).locator('input[name="priceBeforeVat"]').fill(price)
  if (availability) await chooseNamed(row(page, key), 'availability', availability)
  await row(page, key).locator('.path-price-row__actions .btn--primary').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
}

async function payByTransfer(page) {
  await page.locator('.method-option[data-value="bank_transfer"]').click()
  await page.locator('.checkout__start .btn--primary').click()
  await page
    .locator('input[type="file"][name="proof"]')
    .setInputFiles({ name: 'transfer-receipt.pdf', mimeType: 'application/pdf', buffer: PDF })
  await expect(page.locator('.proof-chip .badge')).toBeVisible()
  await page.locator('.transfer-panel__submit .btn--primary').click()
  await expect(page).toHaveURL(/\/payments\/[0-9a-f-]{36}\/complete$/)
  await closeSuccess(page).catch(() => null)
  return page.url().split('/').at(-2)
}

async function approveTransfer(page, testInfo, paymentId) {
  await signIn(page, testInfo, USERS.finance)
  await open(page, `/finance/transfers/${paymentId}`, '.transfer-review__title')
  await page.locator('.transfer-review__decisions .btn--primary').click()
  await decisionAction(page).click()
  await closeSuccess(page)
  await expect(page.locator('.transfer-decision-line')).toBeVisible()
}

test.describe('«أسعار المسارات» and the priced self path', () => {
  test('prices card → paused accredited page → «ادفع وابدأ» → paid self receipt', async ({
    page,
  }, testInfo) => {
    test.setTimeout(120000)
    await signIn(page, testInfo, USERS.admin)
    await open(page, '/admin/settings/prices', '[data-testid="path-prices"]')
    await expect(page.locator('.path-price-row')).toHaveCount(4)
    await savePrice(page, 'self', { price: '100.00' })
    await savePrice(page, 'accredited', { availability: 'paused' })
    await expect(row(page, 'self').locator('[data-testid="price-in-force-self"]')).toContainText(
      '115.00',
    )
    await row(page, 'accredited').locator('[data-testid="price-history-toggle-accredited"]').click()
    const history = row(page, 'accredited').locator('[data-testid="price-history-accredited"]')
    await expect(history.locator('tbody tr')).toHaveCount(2)
    await expect(history).toContainText(say(testInfo, 'System', 'النظام'))
    await expect(page.locator('.toast')).toHaveCount(0, { timeout: 10000 })
    await shot(page, '01-prices-card-history', testInfo)
    await elementShot(row(page, 'accredited'), '01b-prices-row-history', testInfo)

    await signIn(page, testInfo, USERS.ruwad)
    await open(page, '/paths/accredited', '[data-testid="path-status-card"]')
    await expect(page.locator('[data-testid="path-status-card"]')).toContainText(
      say(testInfo, 'Currently unavailable', 'غير متاح حالياً'),
    )
    await expect(page.locator('[data-testid="path-primary-action"]')).toHaveCount(0)
    await shot(page, '02-path-accredited-paused', testInfo)

    await open(page, '/dashboard', '[data-testid="path-tiles"]')
    const tile = page.locator('[data-testid="path-tile-self"]')
    await expect(tile).toHaveAttribute('data-state', 'payStart')
    await expect(tile.locator('[data-testid="path-tile-action-self"]')).toContainText(
      say(testInfo, 'Pay and start', 'ادفع وابدأ'),
    )
    await expect(tile.locator('[data-testid="path-price"]')).toContainText('100.00')
    await shot(page, '03-self-pay-start-tile', testInfo)
    await elementShot(page.locator('[data-testid="path-tiles"]'), '03b-path-tiles', testInfo)

    await tile.locator('[data-testid="path-tile-action-self"]').click()
    await expect(page).toHaveURL(/\/paths\/self$/)
    await page.locator('[data-testid="path-primary-action"]').click()
    await expect(page).toHaveURL(/\/assessment\/self\/pay$/)
    await expect(page.locator('.checkout-summary__service')).toContainText(
      say(testInfo, 'Self assessment', 'التقييم الذاتي'),
    )
    const paymentId = await payByTransfer(page)

    await approveTransfer(page, testInfo, paymentId)
    await expect(page.locator('[data-testid="linked-request-card"]')).toBeVisible()

    await signIn(page, testInfo, USERS.ruwad)
    await open(page, `/payments/${paymentId}`, '.receipt-card')
    await expect(page.locator('.receipt-card')).toContainText(
      say(testInfo, 'Self assessment', 'التقييم الذاتي'),
    )
    await shot(page, '07-self-receipt', testInfo)
    await open(page, '/paths/self', '[data-testid="path-status-card"]')
    await expect(page.locator('[data-testid="path-status-card"]')).toHaveAttribute(
      'data-state',
      'draft',
    )
  })

  test('an open card order is the rank-1 next step with its channel and expiry', async ({
    page,
  }, testInfo) => {
    test.setTimeout(90000)
    await signIn(page, testInfo, USERS.admin)
    await open(page, '/admin/settings/prices', '[data-testid="path-prices"]')
    await savePrice(page, 'self', { price: '100.00' })
    await signIn(page, testInfo, USERS.ruwad)
    await page.goto('/assessment/self/pay')
    await page.locator('.method-option[data-value="electronic"]').click()
    await page.locator('.checkout__start .btn--primary').click()
    await expect(page.locator('[data-fake-moyasar="form"]')).toBeVisible()
    await open(page, '/dashboard', '[data-testid="next-step"]')
    const step = page.locator('[data-testid="next-step"]')
    await expect(step).toHaveAttribute('data-rank', '1')
    await expect(step.locator('[data-testid="next-step-payment"]')).toContainText(
      say(testInfo, 'Electronic', 'إلكتروني'),
    )
    await expect(step.locator('[data-testid="next-step-expiry"]')).toContainText(
      say(testInfo, 'The order expires', 'تنتهي صلاحية الطلب'),
    )
    await expect(page.locator('[data-testid="path-tile-self"]')).toHaveAttribute(
      'data-state',
      'awaitingPayment',
    )
    await shot(page, '08-next-step-rank1', testInfo)
    await elementShot(step, '08b-next-step-card', testInfo)
  })
})

test.describe('award entry fee', () => {
  test('the fee is editable on a draft cycle and locked once applications open', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.admin)
    await open(page, '/admin/award/cycles/32', '[data-testid="cycle-fee"]')
    const fee = page.locator('[data-testid="cycle-fee"]')
    await expect(fee.locator('input[name="entryFee"]')).toHaveValue('750.00')
    await expect(fee.locator('input[name="entryFee"]')).toBeEnabled()
    await expect(fee.locator('.pricing-breakdown')).toContainText('862.50')
    await shot(page, '04-cycle-fee-editable', testInfo)
    await elementShot(fee, '04b-cycle-fee-field-editable', testInfo)
    await open(page, '/admin/award/cycles/31', '[data-testid="cycle-fee"]')
    await expect(fee.locator('input[name="entryFee"]')).toBeDisabled()
    await expect(page.locator('[data-testid="cycle-fee-locked"]')).toContainText(
      say(testInfo, 'cannot change once', 'لا تُعدَّل الرسوم'),
    )
    await shot(page, '05-cycle-fee-locked', testInfo)
    await elementShot(fee, '05b-cycle-fee-field-locked', testInfo)
  })

  test('apply → pay the cycle fee by transfer → approved after the window → window-closed state', async ({
    page,
  }, testInfo) => {
    test.setTimeout(120000)
    await page.clock.install({ time: OPENED_AT })
    await seedScenario(page, 'award-fee')
    await signIn(page, testInfo, USERS.albirr)
    await open(page, '/paths/award', '[data-testid="path-status-card"]')
    await expect(page.locator('[data-testid="path-status-card"]')).toHaveAttribute(
      'data-state',
      'eligible',
    )
    await expect(page.locator('[data-testid="path-price"]')).toContainText('500.00')
    await page.locator('[data-testid="path-primary-action"]').click()
    await expect(page).toHaveURL(/\/assessment\/advanced$/)
    await page.locator('.advanced-request__actions .btn--primary').click()
    await expect(page).toHaveURL(/\/assessment\/advanced\/pay\?cycle=31$/)
    await expect(page.locator('.checkout-summary')).toContainText('575.00')
    const paymentId = await payByTransfer(page)

    await page.clock.setSystemTime(AFTER_WINDOW)
    await approveTransfer(page, testInfo, paymentId)

    await signIn(page, testInfo, USERS.albirr)
    await open(page, '/paths/award', '[data-testid="path-status-card"]')
    const card = page.locator('[data-testid="path-status-card"]')
    await expect(card).toHaveAttribute('data-state', 'latePayment')
    await expect(card).toContainText(
      say(
        testInfo,
        'The submission window closed before the transfer was approved',
        'انتهت نافذة التقديم قبل اعتماد التحويل',
      ),
    )
    await expect(page.locator('[data-testid="path-primary-action"]')).toHaveCount(0)
    await shot(page, '06-award-window-closed', testInfo)
    await elementShot(page.locator('.path-header'), '06b-award-header-window-closed', testInfo)
  })
})
