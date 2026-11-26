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
  await expect(overview.locator('.board-row')).toHaveCount(8)
  await expect(overview.locator('.overview-panel')).toHaveCount(19)
  await expect(
    overview.locator('.board-row').last().locator('[data-panel]').last(),
  ).toHaveAttribute('data-panel', 'storage')
  await expect(overview.locator('.overview-cards__card--featured')).toHaveAttribute(
    'data-card',
    'payments_collected',
  )
  await expect(overview.locator('.chart-donut__slice').first()).toBeVisible()
  await expect(page.locator('[data-board="system_admin"]')).toHaveCount(0)
  const sections = await page
    .locator('.staff-dashboard > *')
    .evaluateAll((nodes) => nodes.map((node) => node.className))
  expect(sections[0]).toContain('executive-overview')
  await expect(page.locator('.action-center')).toHaveCount(0)
  await expect(page.locator('.sidebar__item[href="/dashboard/executive"]')).toHaveCount(0)
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
  await page.screenshot({ path: `${SHOTS}/overview-${locale(testInfo)}.png`, fullPage: true })

  await overview.locator('.board-head .filter-dialog__toggle').click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.locator('[data-period="month"]')).toHaveAttribute('aria-pressed', 'true')
  await dialog.locator('[data-period="year"]').click()
  await expect(page).toHaveURL(/period=year/)
  await expect(overview.locator('.executive-overview__period')).toContainText(
    locale(testInfo) === 'en' ? 'January 1' : '1 يناير',
  )
  await expect(overview.locator('.executive-overview__compare')).toContainText(
    locale(testInfo) === 'en' ? 'the same span of the previous period' : 'بالمدة نفسها',
  )

  await dialog.locator('[data-period="custom"]').click()
  await expect(dialog.locator('.date-calendar')).toBeVisible()
  await page.keyboard.press('Enter')
  await page.keyboard.press('Enter')
  await expect(page).toHaveURL(/from=([\d-]+)&to=\1/)
  await expect(dialog.locator('.date-calendar')).toHaveCount(0)
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)
  await expect(overview.locator('.executive-overview__print')).toHaveAccessibleName(
    locale(testInfo) === 'en' ? 'Print dashboard' : 'طباعة اللوحة',
  )

  await page.emulateMedia({ media: 'print' })
  await expect(page.locator('.sidebar')).toBeHidden()
  await expect(overview.locator('.board-head__tools')).toBeHidden()
  await page.screenshot({ path: `${SHOTS}/overview-print-${locale(testInfo)}.png`, fullPage: true })
})

test('the overview filters narrow every card, show as chips and stay in the address', async ({
  page,
}, testInfo) => {
  await seedRoles(page, testInfo, ['system_admin'], 7000)
  await page.goto('/dashboard')
  const overview = page.locator('[data-testid="executive-overview"]')
  const active = overview.locator('[data-card="entities_active"] .overview-cards__value')
  const before = Number((await active.innerText()).replace(/\D/g, ''))
  await overview.locator('.board-head .filter-dialog__toggle').click()
  const dialog = page.getByRole('dialog')
  await dialog
    .getByRole('combobox', { name: locale(testInfo) === 'en' ? 'Region' : 'المنطقة' })
    .click()
  await page
    .getByRole('option', { name: locale(testInfo) === 'en' ? 'Riyadh Region' : 'منطقة الرياض' })
    .click()
  await dialog.getByRole('button', { name: locale(testInfo) === 'en' ? 'Apply' : 'تطبيق' }).click()
  await expect(page).toHaveURL(/region=riyadh/)
  await expect(overview.locator('[data-chip="region"]')).toBeVisible()
  await expect
    .poll(async () => Number((await active.innerText()).replace(/\D/g, '')))
    .toBeLessThan(before)
  await page.reload()
  await expect(overview.locator('[data-chip="region"]')).toBeVisible()
  await overview
    .locator('.board-head')
    .getByRole('button', { name: locale(testInfo) === 'en' ? 'Clear filters' : 'مسح الفلترة' })
    .click()
  await expect(page).not.toHaveURL(/region=/)
})

test('the overview rows end full: every card in a row shares its height', async ({
  page,
}, testInfo) => {
  await seedRoles(page, testInfo, ['system_admin'], 7000)
  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.goto('/dashboard')
  await expect(page.locator('[data-panel="storage"]')).toBeVisible()
  const rows = await page.locator('.board-row').evaluateAll((nodes) =>
    nodes.map((row) => {
      const cards = [...row.children].map((card) => card.getBoundingClientRect())
      const box = row.getBoundingClientRect()
      return {
        heights: new Set(cards.map((card) => Math.round(card.height))).size,
        lines: new Set(cards.map((card) => Math.round(card.top))).size,
        gap: Math.abs(
          Math.max(...cards.map((card) => card.right)) -
            Math.min(...cards.map((card) => card.left)) -
            box.width,
        ),
      }
    }),
  )
  for (const row of rows) {
    expect(row.lines).toBe(1)
    expect(row.heights).toBe(1)
    expect(row.gap).toBeLessThan(2)
  }
  await page.locator('[data-panel="geography"]').screenshot({
    path: `${SHOTS}/overview-map-${locale(testInfo)}.png`,
  })
})

test('the overview map shades the regions, switches the measure and lists a region’s cities', async ({
  page,
}, testInfo) => {
  await seedRoles(page, testInfo, ['system_admin'], 7000)
  await page.goto('/dashboard')
  const map = page.locator('[data-panel="geography"]')
  await expect(map.locator('.region-map__region')).toHaveCount(13)
  await expect(map.locator('.region-map__city').first()).toBeVisible()
  await expect(map.locator('[data-region="riyadh"]')).toHaveAttribute('aria-pressed', 'true')

  await map.locator('[data-metric="collected"]').click()
  await expect(map.locator('[data-metric="collected"]')).toHaveAttribute('aria-pressed', 'true')
  await map.locator('[data-region="makkah"]').focus()
  await page.keyboard.press('Enter')
  await expect(map.locator('[data-testid="geography-cities"] h4')).toContainText(
    locale(testInfo) === 'en' ? 'Makkah' : 'مكة',
  )
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
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

test('the assessor home is its overview: live tiles, period tiles and five charts, no Action Center', async ({
  page,
}, testInfo) => {
  await seedRoles(page, testInfo, ['assessor'], 10)
  await page.goto('/dashboard')
  const overview = page.locator('[data-testid="assessor-overview"]')
  await expect(overview).toBeVisible()
  await expect(page.locator('#dashboard-board-title')).toHaveClass(/--hidden/)
  await expect(page.locator('.action-center')).toHaveCount(0)
  await expect(overview.locator('[data-card="open"]')).toHaveClass(/overview-cards__card--featured/)
  await expect(overview.locator('.assessor-cards__state')).toHaveCount(3)
  await expect(overview.locator('.assessor-cards > li')).toHaveCount(7)
  await expect(overview.locator('.overview-panel')).toHaveCount(5)
  await overview.locator('[data-period="year"]').click()
  await expect(page).toHaveURL(/period=year/)
  await expect(overview.locator('[data-period="year"]')).toHaveAttribute('aria-pressed', 'true')
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
  await page.screenshot({ path: `${SHOTS}/assessor-${locale(testInfo)}.png`, fullPage: true })
})
