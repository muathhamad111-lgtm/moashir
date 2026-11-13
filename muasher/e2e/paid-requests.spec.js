import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

const IGNORED_CONSOLE = /Failed to load resource|40\d|50\d/i
const DRAFT_NUMBER = 'ACC-2026-000004'
const DRAFT_PAYMENT = '3f0c9a52-6d1e-4b7a-9c21-5a1e00000012'
const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }

const USERS = {
  systemAdmin: { id: 1, roles: ['system_admin'] },
  follower: { id: 8, roles: ['request_follower'] },
  finance: { id: 22, roles: ['finance_officer'] },
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

function horizontalOverflow(page) {
  return page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  )
}

test('the System Administrator finds a paid draft in its own tab and follows it to the payment', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await page.setViewportSize(DESKTOP)
  await signIn(page, testInfo, USERS.systemAdmin)
  await page.goto('/admin/requests?path=accredited')
  const tabs = page.locator('.tabs__tab')
  await expect(tabs.nth(1)).toContainText(
    isEn(testInfo) ? 'Waiting for the entity' : 'بانتظار استكمال الجهة',
  )
  await expect(page.locator('.request-queue__link', { hasText: DRAFT_NUMBER })).toHaveCount(0)

  await tabs.nth(1).click()
  await expect(page).toHaveURL(/stage=preparing/)
  await expect(page.locator('.preparing-requests__note')).toBeVisible()
  const row = page.locator('tbody tr', { hasText: DRAFT_NUMBER })
  await expect(row).toContainText('PAY-2026-000012')
  await expect(row.locator('[role="progressbar"]')).toBeVisible()
  await expect(page.locator('.stats-card')).toHaveCount(4)
  await page.screenshot({
    path: `test-results/paid-requests-tab-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await row.locator(`a[href^="/admin/requests/"]`).click()
  const card = page.locator('[data-testid="request-payment-card"]')
  await expect(card).toContainText('PAY-2026-000012')
  await page.screenshot({
    path: `test-results/paid-request-detail-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await card.locator(`a[href="/finance/transactions/${DRAFT_PAYMENT}"]`).first().click()
  const linked = page.locator('[data-testid="linked-request-card"]')
  await expect(linked).toContainText(DRAFT_NUMBER)
  await expect(linked.locator('a[href^="/admin/requests/"]')).toBeVisible()
  expect(errors).toEqual([])
})

test('a deep link opens the paid drafts tab for the follower on mobile', async ({
  page,
}, testInfo) => {
  await page.setViewportSize(MOBILE)
  await signIn(page, testInfo, USERS.follower)
  await page.goto('/admin/requests?path=accredited&stage=preparing')
  await expect(page.locator('.tabs__tab[aria-selected="true"]')).toContainText(
    isEn(testInfo) ? 'Waiting for the entity' : 'بانتظار استكمال الجهة',
  )
  await expect(page.locator('tbody tr', { hasText: DRAFT_NUMBER })).toBeVisible()
  await page.screenshot({
    path: `test-results/paid-requests-tab-mobile-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
})

test('Finance sees the linked request number and status without opening it', async ({
  page,
}, testInfo) => {
  await page.setViewportSize(DESKTOP)
  await signIn(page, testInfo, USERS.finance)
  await page.goto(`/finance/transactions/${DRAFT_PAYMENT}`)
  const linked = page.locator('[data-testid="linked-request-card"]')
  await expect(linked).toContainText(DRAFT_NUMBER)
  await expect(linked.locator('.linked-request__restricted')).toBeVisible()
  await expect(linked.locator('a[href^="/admin/requests/"]')).toHaveCount(0)
  await page.screenshot({
    path: `test-results/linked-request-finance-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
})

for (const viewport of [DESKTOP, MOBILE]) {
  test(`the transactions page never scrolls sideways at ${viewport.width} px`, async ({
    page,
  }, testInfo) => {
    await page.setViewportSize(viewport)
    await signIn(page, testInfo, USERS.finance)
    await page.goto('/finance/transactions')
    await expect(page.locator('.transactions-table__link').first()).toBeVisible()
    expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0)
  })
}
