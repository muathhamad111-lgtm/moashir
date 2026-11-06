import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedMockSession, seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'

const SHOTS = '../agents/tasks/reports/Task-66B'
const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001, name: 'أحمد بن سالم القحطاني' }
const RAWABI = { id: 7603, roles: ['entity'], entityId: 6003, name: 'سارة عبدالله' }
const ADMIN = { id: 9101, roles: ['system_admin'], name: 'مدير النظام' }
const ASSESSOR = { id: 10, roles: ['assessor'], name: 'المقيّم المعتمد' }
const CONTENT_OFFICER = { id: 9301, roles: ['content_officer'], name: 'مسؤول المحتوى' }
const LATEST = 9102
const PREVIOUS = 9109
const UNDER_REVIEW = 9108
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'

mkdirSync(SHOTS, { recursive: true })

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function suffixOf(testInfo) {
  return localeOf(testInfo) === 'en' ? 'en-ltr' : 'ar-rtl'
}

async function open(page, path, ready) {
  await page.goto(path)
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator(ready).first()).toBeVisible({ timeout: 15000 })
}

async function capture(page, name, testInfo, { fullPage = true } = {}) {
  await settleFonts(page)
  await page.screenshot({ path: `${SHOTS}/${name}-${suffixOf(testInfo)}.png`, fullPage })
}

async function horizontalOverflow(page) {
  return page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  )
}

test.describe('excellence tools — entity', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(DESKTOP)
    await seedSignedIn(page, ALBIRR, { locale: localeOf(testInfo), theme: 'light' })
  })

  test('shows the three exhaustive classes with quoted assessor texts', async ({
    page,
  }, testInfo) => {
    await open(page, '/tools/strengths-and-gaps', '[data-testid="gap-column"]')
    await expect(page.locator('[data-testid="gap-column"]')).toHaveCount(3)
    const total = await page.locator('[data-testid="sg-total"] .sg-summary__number').innerText()
    const tiles = await page.locator('.sg-summary__tile:not(.sg-summary__tile--total)').all()
    let sum = 0
    for (const tile of tiles) sum += Number(await tile.locator('.sg-summary__number').innerText())
    expect(sum).toBe(Number(total))
    await expect(page.locator('.assessor-quote').first()).toBeVisible()
    await expect(page.locator('[data-testid="insights-source"]')).toContainText('ACC-2026-000001')
    await expect(page.locator('[data-testid="insights-rules-line"]')).toBeVisible()
    const navItem = page.locator(
      '[data-testid="portal-side-nav"] a[href="/tools/strengths-and-gaps"]',
    )
    await expect(navItem).toHaveAttribute('aria-current', 'page')
    await expect(navItem.locator('.coming-soon-chip')).toHaveCount(0)
    await capture(page, 'strengths-gaps', testInfo)
  })

  test('keeps the selected result in the query string across the tools', async ({ page }) => {
    await open(page, '/tools/strengths-and-gaps', '[data-testid="insights-selector"]')
    await page.locator('[data-testid="insights-selector"] .dropdown-field').click()
    await page.getByRole('option').nth(1).click()
    await expect(page).toHaveURL(new RegExp(`result=${PREVIOUS}`))
    await expect(page.locator('[data-testid="insights-source"]')).toContainText('ACC-2025-000012')
    await page.locator('.insights-links__link').nth(1).click()
    await expect(page).toHaveURL(new RegExp(`/tools/maturity\\?result=${PREVIOUS}`))
    await expect(page.locator('[data-testid="maturity-comparison"]')).toBeVisible()
  })

  test('shows maturity bands with the delta against the earlier result', async ({
    page,
  }, testInfo) => {
    await open(page, `/tools/maturity?result=${LATEST}`, '[data-testid="maturity-card"]')
    await expect(page.locator('[data-testid="maturity-comparison"]')).toContainText(
      'ACC-2025-000012',
    )
    await expect(page.locator('[data-testid="maturity-card"] .delta-chip').first()).toBeVisible()
    await expect(page.locator('[data-testid="maturity-overall"] .band-chip')).toBeVisible()
    const card = page.locator('[data-testid="maturity-card"]').first()
    await card.locator('.maturity-card__toggle').click()
    await expect(card.locator('.maturity-card__subs')).toBeVisible()
    await capture(page, 'maturity', testInfo)
  })

  test('shows the executive summary and downloads the board report PDF', async ({
    page,
  }, testInfo) => {
    const locale = localeOf(testInfo)
    await open(page, '/tools/board-reports', '[data-testid="executive-summary"]')
    await expect(page.locator('[data-testid="executive-assessor-summary"]')).toBeVisible()
    await expect(page.locator('[data-testid="library-group"]')).toHaveCount(2)
    await expect(page.locator('.library-row--soon')).toHaveCount(3)
    await page.locator(`input[name="board-report-locale"][value="${locale}"]`).check()
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('[data-testid="board-download-action"]').click(),
    ])
    expect(download.suggestedFilename()).toBe(`board-report-ACC-2026-000001-${locale}.pdf`)
    await capture(page, 'board-reports', testInfo)
  })

  test('renders the three tools in dark mode', async ({ page }, testInfo) => {
    await page.addInitScript(() => localStorage.setItem('muasher.theme', 'dark'))
    await open(page, `/tools/strengths-and-gaps?result=${PREVIOUS}`, '[data-testid="gap-column"]')
    await expect(page.locator('.insight-chip--critical').first()).toBeVisible()
    await capture(page, 'strengths-gaps-dark', testInfo)
    await open(page, '/tools/maturity', '[data-testid="maturity-card"]')
    await capture(page, 'maturity-dark', testInfo)
    await open(page, '/tools/board-reports', '[data-testid="executive-summary"]')
    await capture(page, 'board-reports-dark', testInfo)
  })

  test('works on a 390 px phone with class tabs and no overflow', async ({ page }, testInfo) => {
    await page.setViewportSize(MOBILE)
    await open(page, '/tools/strengths-and-gaps', '.sg-board__tab')
    await expect(page.locator('[data-testid="gap-column"]:visible')).toHaveCount(1)
    await page.locator('.sg-board__tab').nth(1).click()
    await expect(
      page.locator('[data-testid="gap-column"][data-class="critical_gap"]'),
    ).toBeVisible()
    expect(await horizontalOverflow(page)).toBeLessThanOrEqual(1)
    await capture(page, 'strengths-gaps-mobile', testInfo)
    await open(page, '/tools/maturity', '[data-testid="maturity-card"]')
    expect(await horizontalOverflow(page)).toBeLessThanOrEqual(1)
    await capture(page, 'maturity-mobile', testInfo)
    await open(page, '/tools/board-reports', '[data-testid="executive-summary"]')
    expect(await horizontalOverflow(page)).toBeLessThanOrEqual(1)
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator('[data-testid="board-download-action"]').click(),
    ])
    expect(download.suggestedFilename()).toMatch(/^board-report-ACC-2026-000001-(ar|en)\.pdf$/)
    await capture(page, 'board-reports-mobile', testInfo)
  })
})

test.describe('excellence tools — empty states and entry points', () => {
  test.beforeEach(async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(DESKTOP)
  })

  test('shows the in-progress empty state with the request CTA', async ({ page }, testInfo) => {
    await seedSignedIn(page, RAWABI, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/tools/strengths-and-gaps', '[data-testid="insights-empty"]')
    await expect(page.locator('[data-testid="insights-empty"]')).toHaveAttribute(
      'data-reason',
      'in_progress',
    )
    await expect(page.locator('[data-testid="insights-empty"] .btn--primary')).toBeVisible()
    await capture(page, 'empty-in-progress', testInfo)
  })

  test('links the home hero and the accredited result to the tools', async ({ page }, testInfo) => {
    await seedSignedIn(page, ALBIRR, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/dashboard', '[data-testid="hero-next-level"]')
    await page.locator('[data-testid="hero-next-level"]').click()
    await expect(page).toHaveURL(/\/tools\/strengths-and-gaps/)
    await open(page, `/assessment/accredited/result/${LATEST}`, '[data-testid="result-tools"]')
    await page.locator('[data-testid="result-tools"] a').first().click()
    await expect(page).toHaveURL(new RegExp(`/tools/board-reports\\?result=${LATEST}`))
  })
})

test.describe('excellence tools — staff and settings', () => {
  test.beforeEach(async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(DESKTOP)
  })

  test('shows the entity tools read only on the staff entity record', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, ADMIN, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/admin/entities/5001', '[data-testid="entity-insights"]')
    const panel = page.locator('[data-testid="entity-insights"]')
    await expect(panel.locator('[data-testid="insights-read-only"]')).toBeVisible()
    await expect(panel.locator('[data-testid="gap-column"]').first()).toBeVisible()
    await expect(panel.locator('textarea, input[type="text"], .insights-empty .btn')).toHaveCount(0)
    await panel.getByRole('tab').nth(1).click()
    await expect(panel.locator('[data-testid="maturity-card"]').first()).toBeVisible()
    await panel.getByRole('tab').nth(2).click()
    await expect(panel.locator('[data-testid="executive-summary"]')).toBeVisible()
    await panel.getByRole('tab').first().click()
    await expect(panel.locator('[data-testid="gap-item"]').first()).toBeVisible()
    await page.addStyleTag({ content: '.navbar--app { position: static !important; }' })
    await settleFonts(page)
    await panel.screenshot({ path: `${SHOTS}/staff-entity-tools-${suffixOf(testInfo)}.png` })
  })

  test('a System Administrator threshold change reaches the entity as a new rules version', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, ADMIN, { locale: localeOf(testInfo), theme: 'light' })
    await open(
      page,
      '/admin/settings/assessment?tab=insights',
      '[data-testid="insights-settings"] form',
    )
    await page.locator('input[name="criticalBelow"]').fill('55')
    await expect(page.locator('[data-testid="insights-alignment"]')).toBeVisible()
    await capture(page, 'settings-insights', testInfo)
    await page.locator('#insights-settings-form button[type="submit"]').click()
    await expect(page.locator('[data-testid="insights-settings-version"]')).toContainText('2')
    await seedMockSession(page, { ...ALBIRR, locale: localeOf(testInfo) })
    await open(page, '/tools/strengths-and-gaps', '[data-testid="insights-rules-line"]')
    const rules = page.locator('[data-testid="insights-rules-line"]')
    await expect(rules).toContainText('55%')
    await expect(rules).toContainText('2')
  })

  test('lets the System Administrator set the VAT number and shows the reminder when empty', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, ADMIN, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/admin/settings/finance', 'input[name="vatRegistrationNumber"]')
    await expect(page.locator('[data-testid="vat-number-reminder"]')).toBeVisible()
    await page.locator('input[name="vatRegistrationNumber"]').fill('300012345600003')
    await capture(page, 'settings-finance-vat', testInfo)
    await page.locator('button[form="finance-settings-form"]').click()
    await expect(page.locator('[data-testid="vat-number-reminder"]')).toHaveCount(0)
  })

  test('lets the assessor write the summary under review', async ({ page }, testInfo) => {
    await seedSignedIn(page, ASSESSOR, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, `/admin/requests/${UNDER_REVIEW}/review`, '[data-testid="review-summary"]')
    await page.locator('textarea[name="assessor-summary"]').fill('ملخص تجريبي لنتيجة التقييم.')
    await page.locator('[data-testid="review-summary-save"]').click()
    await expect(page.locator('[data-testid="review-summary-version"]')).toBeVisible()
    await page.locator('[data-testid="review-summary"]').scrollIntoViewIfNeeded()
    await capture(page, 'assessor-summary', testInfo, { fullPage: false })
  })

  test('lets the Content Officer read the maturity texts', async ({ page }, testInfo) => {
    await seedSignedIn(page, CONTENT_OFFICER, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/admin/settings/maturity-texts', '[data-testid="maturity-texts"]')
    await expect(page.locator('.maturity-texts__row[data-band]')).toHaveCount(4)
    await page.getByRole('button', { name: /نص خاص بمعيار|text for a criterion/i }).click()
    await expect(page.locator('[data-testid="maturity-editor"]')).toBeVisible()
    await capture(page, 'maturity-texts', testInfo)
  })
})
