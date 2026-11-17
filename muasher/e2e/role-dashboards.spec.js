import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

function locale(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function seedRoles(page, testInfo, roles, id = 5001) {
  return seedSignedIn(
    page,
    {
      id,
      roles,
      entityId: roles.includes('entity') ? 5001 : null,
      name: { ar: 'سارة بنت عبدالله', en: 'Sarah Abdullah' },
    },
    { locale: locale(testInfo) },
  )
}

test('a multi-role account lands on the highest board and keeps the back office', async ({
  page,
}, testInfo) => {
  await seedRoles(page, testInfo, ['entity', 'assessor', 'system_admin'])
  await page.goto('/dashboard')
  await expect(page.locator('[data-testid="executive-overview"]')).toBeVisible()
  // The overview covers the System Administrator board, so only the other boards are offered.
  await expect(page.locator('[data-board="system_admin"]')).toHaveCount(0)
  await expect(page.locator('.dashboard-board-nav__link')).toHaveCount(2)
  await expect(page.locator('.dashboard-board-nav__link[href*="board=executive"]')).toHaveCount(0)
  await expect(page.locator('.journey-home')).toHaveCount(0)
  await expect(page.locator('.sidebar')).toBeVisible()
})

test('an entity-only account gets the journey home instead of a staff board', async ({
  page,
}, testInfo) => {
  await seedRoles(page, testInfo, ['entity'])
  await page.goto('/dashboard')
  await expect(page.locator('.journey-home')).toBeVisible()
  await expect(page.locator('.staff-dashboard')).toHaveCount(0)
})

test('an unknown board in the link falls back to the landing board', async ({ page }, testInfo) => {
  await seedRoles(page, testInfo, ['finance_officer'], 41)
  await page.goto('/dashboard?board=system_admin')
  await expect(page.locator('[data-board="finance_officer"]')).toBeVisible()
  await expect(page).not.toHaveURL(/board=system_admin/)
})

const SHOTS = 'e2e/__screenshots__/dashboard'

test('the dashboard opens with the platform overview for the System Administrator', async ({
  page,
}, testInfo) => {
  await seedRoles(page, testInfo, ['system_admin'], 7000)
  await page.goto('/dashboard')
  const overview = page.locator('[data-testid="executive-overview"]')
  await expect(overview.locator('.overview-cards__card')).toHaveCount(6)
  await expect(overview.locator('.overview-panel')).toHaveCount(10)
  await expect(overview.locator('[data-period="month"]')).toHaveAttribute('aria-pressed', 'true')
  await expect(overview.locator('.chart--line .chart__line')).toHaveCount(1)
  await overview.locator('.chart--line [data-series="requests"]').click()
  await expect(overview.locator('.chart--line .chart__line')).toHaveCount(2)
  await overview.locator('.chart--line [data-series="requests"]').click()
  await expect(overview.locator('.overview-cards__card--featured')).toHaveAttribute(
    'data-card',
    'payments_collected',
  )
  await expect(overview.locator('.chart-donut__slice').first()).toBeVisible()
  await expect(page.locator('[data-board="system_admin"]')).toHaveCount(0)
  const sections = await page
    .locator('.staff-dashboard > *')
    .evaluateAll((nodes) => nodes.map((node) => node.className))
  expect(sections[0]).toContain('action-center')
  expect(sections[1]).toContain('executive-overview')
  await expect(page.locator('.sidebar__item[href="/dashboard/executive"]')).toHaveCount(0)
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
  await page.screenshot({ path: `${SHOTS}/overview-${locale(testInfo)}.png`, fullPage: true })

  await overview.locator('[data-period="year"]').click()
  await expect(page).toHaveURL(/period=year/)
  await expect(overview.locator('.executive-overview__period')).toContainText(
    locale(testInfo) === 'en' ? 'January 1' : '1 يناير',
  )

  await page.emulateMedia({ media: 'print' })
  await expect(page.locator('.sidebar')).toBeHidden()
  await expect(overview.locator('.executive-overview__tools')).toBeHidden()
  await page.screenshot({ path: `${SHOTS}/overview-print-${locale(testInfo)}.png`, fullPage: true })
})

test('the old executive link lands on the dashboard, and the overview stays with its permission', async ({
  page,
}, testInfo) => {
  await seedRoles(page, testInfo, ['finance_officer'], 41)
  await page.goto('/dashboard/executive')
  await expect(page).toHaveURL(/\/dashboard$/)
  await expect(page.locator('[data-board="finance_officer"]')).toBeVisible()
  await expect(page.locator('[data-testid="executive-overview"]')).toHaveCount(0)
})
