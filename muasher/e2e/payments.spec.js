import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { forceMockFault } from './support/mockFaults'
import { guardMoyasar } from './support/moyasarGuard'

const IGNORED_CONSOLE = /Failed to load resource|40\d|50\d/i
const PAID_ACCREDITED = '3f0c9a52-6d1e-4b7a-9c21-5a1e00000001'
const OWN_PENDING = '3f0c9a52-6d1e-4b7a-9c21-5a1e00000011'
const OWN_PENDING_PROVIDER = 'mock_pay_settles_after_checks_0011'

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function seedEntity(page, testInfo) {
  return seedSignedIn(
    page,
    {
      id: 5001,
      roles: ['entity'],
      entityId: 5001,
      name: { ar: 'أحمد بن سالم القحطاني', en: 'Ahmed Salem Al-Qahtani' },
    },
    { locale: isEn(testInfo) ? 'en' : 'ar' },
  )
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

async function openAccreditedCheckout(page) {
  await page.goto('/assessment/accredited/pay')
  await page.locator('.checkout__start .btn--primary').click()
  await expect(page.locator('[data-fake-moyasar="form"]')).toBeVisible()
}

async function payWith(page, outcome) {
  await page.locator('#fake-moyasar-sandbox-outcome').selectOption(outcome)
  await page.locator('.fake-moyasar__pay').click()
  await expect(page).toHaveURL(/\/payments\/[0-9a-f-]{36}\/return\?id=/)
}

test('the entity pays through the Moyasar form, the server verifies it and a TEST receipt is issued', async ({
  page,
}, testInfo) => {
  const leaks = await guardMoyasar(page)
  const errors = trackConsole(page)
  const platformBodies = []
  page.on('request', (request) => {
    if (request.url().includes('/api/v1/')) platformBodies.push(request.postData() ?? '')
  })
  await seedEntity(page, testInfo)
  await page.goto('/assessment/accredited/pay')
  await expect(page.locator('.checkout-summary__pending')).toBeVisible()
  await page.screenshot({
    path: `test-results/checkout-start-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await page.locator('.checkout__start .btn--primary').click()
  await expect(page.locator('[data-fake-moyasar="form"]')).toBeVisible()
  await expect(page.locator('.test-mode-notice')).toBeVisible()
  await expect(page.locator('.checkout-summary .pricing-breakdown__row--total')).toContainText(
    '1,150.00',
  )
  await expect(page.locator('.order-countdown__time')).toHaveText(/^(29|30):\d{2}$/)
  await page.screenshot({ path: `test-results/checkout-${suffixOf(testInfo)}.png`, fullPage: true })

  await payWith(page, 'paid')
  await expect(page.locator('.payment-outcome--paid')).toBeVisible()
  await page.screenshot({
    path: `test-results/return-paid-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await page.locator('.payment-return__link--primary').first().click()
  await expect(page.locator('.receipt-card--test')).toBeVisible()
  await expect(page.locator('.receipt-card__number')).not.toContainText('RCP-')

  expect(platformBodies.some((body) => /card|cvc|cvv|number|sandbox_outcome/i.test(body))).toBe(
    false,
  )
  expect(leaks).toEqual([])
  expect(errors).toEqual([])
})

test('a declined payment shows the failure reason and the return page ignores the status in the URL', async ({
  page,
}, testInfo) => {
  const leaks = await guardMoyasar(page)
  await seedEntity(page, testInfo)
  await openAccreditedCheckout(page)
  await payWith(page, 'declined')
  await expect(page.locator('.payment-outcome--failed')).toBeVisible()
  await expect(page.locator('.payment-outcome--failed .payment-notice__message')).toContainText(
    isEn(testInfo) ? 'declined' : 'رفضت',
  )
  await page.screenshot({
    path: `test-results/return-failed-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  const tampered = page
    .url()
    .replace('status=failed', 'status=paid')
    .replace(/message=[^&]*/, 'message=APPROVED')
  await page.goto(tampered)
  await expect(page.locator('.payment-outcome--failed')).toBeVisible()
  await expect(page.locator('.payment-outcome--paid')).toHaveCount(0)
  await expect(page.locator('.payment-return__link--primary')).toBeVisible()
  expect(leaks).toEqual([])
})

test('a payment still in 3-D Secure shows pending and the page polls until the server confirms it', async ({
  page,
}, testInfo) => {
  await seedEntity(page, testInfo)
  await page.goto(
    `/payments/${OWN_PENDING}/return?id=${OWN_PENDING_PROVIDER}&status=failed&message=DECLINED`,
  )
  await expect(page.locator('.payment-outcome--pending')).toBeVisible()
  await page.screenshot({
    path: `test-results/return-pending-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await expect(page.locator('.payment-outcome--paid')).toBeVisible({ timeout: 10000 })
})

test('an expired order is shown as cancelled and a new attempt opens a new order', async ({
  page,
}, testInfo) => {
  await page.clock.install()
  await seedEntity(page, testInfo)
  await openAccreditedCheckout(page)
  const firstReference = await page.locator('.checkout-summary__reference bdi').textContent()
  await page.clock.fastForward('31:00')
  await expect(page.locator('.payment-notice--warning .payment-notice__title')).toContainText(
    isEn(testInfo) ? 'expired' : 'انتهت',
  )
  await page.locator('.payment-notice--warning .btn--primary').click()
  await expect(page.locator('[data-fake-moyasar="form"]')).toBeVisible()
  await expect(page.locator('.checkout-summary__reference bdi')).not.toHaveText(
    firstReference ?? '',
  )
})

test('an open payment of the same service is reported instead of a second checkout', async ({
  page,
}, testInfo) => {
  await seedEntity(page, testInfo)
  await page.goto('/bag/pay')
  await page.locator('.checkout__start .btn--primary').click()
  await expect(page.locator('.payment-notice--warning')).toBeVisible()
  await expect(page.locator('[data-fake-moyasar="form"]')).toHaveCount(0)
  await page.locator('.payment-notice__link').click()
  await expect(page).toHaveURL(new RegExp(`/payments/${OWN_PENDING}$`))
})

test('My Payments lists receipts, live-only stats and opens an official receipt for download', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedEntity(page, testInfo)
  await page.goto('/payments')
  await expect(page.locator('.my-payments__row')).toHaveCount(4)
  await expect(page.locator('.payment-stats__tile').nth(2)).toContainText('1,150.00')
  await page.screenshot({
    path: `test-results/my-payments-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await page.goto(`/payments/${PAID_ACCREDITED}`)
  await expect(page.locator('.receipt-card__number')).toContainText('RCP-2026-000001')
  await expect(page.locator('.receipt-card__hijri').first()).toBeVisible()
  await page.screenshot({ path: `test-results/receipt-${suffixOf(testInfo)}.png`, fullPage: true })
  const download = page.waitForEvent('download')
  await page.locator('.receipt-card__actions .btn--secondary').click()
  expect((await download).suggestedFilename()).toBe('RCP-2026-000001.pdf')
  expect(errors).toEqual([])
})

test('a receipt that cannot be rendered is reported as temporarily unavailable', async ({
  page,
}, testInfo) => {
  await seedEntity(page, testInfo)
  await forceMockFault(page, {
    method: 'POST',
    path: `/api/v1/payments/${PAID_ACCREDITED}/receipt/download`,
    status: 503,
    code: 'document.renderer_unavailable',
  })
  await page.goto(`/payments/${PAID_ACCREDITED}`)
  await page.locator('.receipt-card__actions .btn--secondary').click()
  await expect(page.locator('.receipt-card .payment-notice--warning')).toBeVisible()
})

test('staff and guests cannot reach the entity payment screens', async ({ page }, testInfo) => {
  await seedSignedIn(
    page,
    { id: 22, roles: ['finance_officer'] },
    { locale: isEn(testInfo) ? 'en' : 'ar' },
  )
  await page.goto('/payments')
  await expect(page).toHaveURL(/\/403$/)
  await page.goto('/assessment/accredited/pay')
  await expect(page).toHaveURL(/\/403$/)
})
