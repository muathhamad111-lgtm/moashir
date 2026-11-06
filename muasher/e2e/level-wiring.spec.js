import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'
import { chooseValue } from './support/dropdown'

const SHOTS = '../agents/tasks/reports/Task-68'
const DESKTOP = { width: 1440, height: 900 }
const ADMIN = { id: 9101, roles: ['system_admin'], name: 'مدير النظام' }
const FOLLOWER = { id: 8, roles: ['request_follower'], name: 'متابع الطلبات' }
const ALBIRR = { id: 5001, roles: ['entity'], entityId: 5001, name: 'أحمد بن سالم القحطاني' }
const EHSAN_REQUEST = 9108
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

const rows = (page) => page.locator('.table:not(.table--loading) tbody .table__row')
const levelTiers = (page) =>
  rows(page).evaluateAll((items) =>
    items.map((row) => row.querySelector('.level-badge')?.getAttribute('data-tier')),
  )

test.beforeEach(async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.setViewportSize(DESKTOP)
})

test.describe('Task-68 — staff', () => {
  test('filters the entities list by level, keeps it in the URL and sorts by level', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, ADMIN, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/admin/entities', '.table__row .level-badge')
    await expect(page.locator('thead')).toContainText(
      localeOf(testInfo) === 'en' ? 'Level' : 'المستوى',
    )

    await chooseValue(page.locator('[data-testid="entity-level-filter"] [role="combobox"]'), 'gold')
    await expect(page).toHaveURL(/[?&]level=gold/)
    await expect(rows(page)).toHaveCount(1)
    await expect(rows(page).first()).toContainText(localeOf(testInfo) === 'en' ? 'Ehsan' : 'إحسان')
    await expect(rows(page).first().locator('.level-badge')).toHaveAttribute('data-tier', 'gold')
    await expect(rows(page).first().locator('.level-badge__chip')).toBeVisible()
    await expect(page.locator('.stats-card__value').first()).toHaveText('1')
    await capture(page, 'staff-list-filter-gold', testInfo)

    await page.reload()
    await page.addStyleTag({ content: DEV_OVERLAYS })
    await expect(rows(page)).toHaveCount(1)
    await expect(rows(page).first().locator('.level-badge')).toHaveAttribute('data-tier', 'gold')

    await chooseValue(page.locator('[data-testid="entity-level-filter"] [role="combobox"]'), '')
    await expect(page).not.toHaveURL(/level=/)
    await chooseValue(page.locator('[data-testid="entity-sort"] [role="combobox"]'), '-level')
    await expect
      .poll(async () => (await levelTiers(page)).slice(0, 4))
      .toEqual(['diamond', 'gold', 'silver', 'silver'])
    await capture(page, 'staff-list-sort-level', testInfo)

    await chooseValue(page.locator('[data-testid="entity-sort"] [role="combobox"]'), 'level')
    await expect.poll(async () => (await levelTiers(page)).at(-1)).not.toBe('none')
    await expect
      .poll(async () => ['none', 'progress'].includes((await levelTiers(page))[0]))
      .toBe(true)
  })

  test('opens the filtered list from the entities-by-level dashboard widget', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, ADMIN, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/dashboard', '[data-widget="entities_by_level"]')
    const widget = page.locator('[data-widget="entities_by_level"]')
    await expect(widget.locator('.widget-levels__row')).toHaveCount(7)
    await expect(widget.locator('.widget-levels__row').first()).toHaveAttribute(
      'data-level',
      'none',
    )
    await expect(widget.locator('.widget-levels__row').last()).toHaveAttribute(
      'data-level',
      'diamond',
    )
    await expect(widget.locator('[data-level="in_progress"] a')).toHaveCount(0)
    await expect(widget.locator('[data-level="gold"] .widget-levels__value')).toHaveText('1')
    const track = await widget.locator('[data-level="gold"] .widget-levels__track').boundingBox()
    const fill = await widget.locator('[data-level="gold"] .widget-levels__fill').boundingBox()
    expect(fill.width).toBeLessThan(track.width)
    const startGap = {
      ar: track.x + track.width - (fill.x + fill.width),
      en: fill.x - track.x,
    }[localeOf(testInfo)]
    expect(Math.abs(startGap)).toBeLessThan(1)
    await settleFonts(page)
    await widget.screenshot({
      path: `${SHOTS}/dashboard-entities-by-level-${suffixOf(testInfo)}.png`,
    })
    await widget.locator('[data-level="diamond"] a').click()
    await expect(page).toHaveURL(/\/admin\/entities\?level=diamond/)
    await expect(rows(page)).toHaveCount(1)
    await expect(rows(page).first().locator('.level-badge')).toHaveAttribute('data-tier', 'diamond')
  })

  test('starts every existing bar and criteria fill at the inline-start edge', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, ADMIN, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/dashboard', '[data-widget="lowest_criteria"] .widget-criteria__fill')
    const gaps = await page.locator('.widget-bars__track, .widget-criteria__track').evaluateAll(
      (tracks, rtl) => {
        return tracks.map((track) => {
          const box = track.getBoundingClientRect()
          const fill = track.querySelector('rect').getBoundingClientRect()
          const startGap = rtl ? box.right - fill.right : fill.left - box.left
          return { startGap, partial: fill.width < box.width - 1 }
        })
      },
      localeOf(testInfo) === 'ar',
    )
    expect(gaps.length).toBeGreaterThan(4)
    expect(gaps.some((gap) => gap.partial)).toBe(true)
    expect(gaps.every((gap) => Math.abs(gap.startGap) < 1)).toBe(true)
    for (const key of ['entities_by_region', 'lowest_criteria']) {
      await settleFonts(page)
      await page.locator(`[data-widget="${key}"]`).screenshot({
        path: `${SHOTS}/dashboard-${key}-${suffixOf(testInfo)}.png`,
      })
    }
  })

  test('shows the level badge in the request queue and on the request header', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, FOLLOWER, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/admin/requests', '.request-queue__entity .level-badge')
    const tiers = await page
      .locator('.request-queue__entity .level-badge')
      .evaluateAll((items) => items.map((item) => item.getAttribute('data-tier')))
    expect(tiers).toContain('gold')
    expect(tiers).toContain('none')
    await capture(page, 'request-queue-levels', testInfo)
    await open(page, `/admin/requests/${EHSAN_REQUEST}`, '[data-testid="request-entity"]')
    await expect(page.locator('[data-testid="request-entity"] .level-badge')).toHaveAttribute(
      'data-tier',
      'gold',
    )
    await capture(page, 'request-detail-level', testInfo, { fullPage: false })
  })

  test('marks a re-assessment on the staff entity ribbon', async ({ page }, testInfo) => {
    await seedSignedIn(page, ADMIN, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/admin/entities/6002', '[data-testid="level-ribbon"]')
    const ribbon = page.locator('[data-testid="level-ribbon"]')
    await expect(ribbon).toHaveAttribute('data-tier', 'gold')
    await expect(ribbon.locator('[data-testid="level-reassessment"]')).toHaveText(
      localeOf(testInfo) === 'en' ? 'Re-assessment in progress' : 'قيد إعادة التقييم',
    )
    await capture(page, 'staff-ribbon-reassessment', testInfo, { fullPage: false })
  })
})

test.describe('Task-68 — entity home and tools', () => {
  test('shows the uploaded evidence KPI and «آخر تحديث» from the server', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, ALBIRR, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/dashboard', '[data-kpi="evidence"]')
    const tile = page.locator('[data-kpi="evidence"]')
    await expect(tile.locator('.kpi-tile__label')).toHaveText(
      localeOf(testInfo) === 'en' ? 'Uploaded evidence' : 'الشواهد المرفوعة',
    )
    await expect(tile.locator('.kpi-tile__value')).toHaveText(/^\d+$/)
    await expect(page.locator('[data-testid="portal-updated"]')).toBeVisible()
    await capture(page, 'home-evidence-kpi', testInfo)
  })

  test('shows the certificate number and downloads the feedback report PDF from the library', async ({
    page,
  }, testInfo) => {
    const locale = localeOf(testInfo)
    await seedSignedIn(page, ALBIRR, { locale, theme: 'light' })
    await open(page, '/tools/board-reports', '[data-testid="executive-summary"]')
    await expect(page.locator('[data-testid="executive-certificate-number"] bdi')).toHaveText(
      'CRT-2026-000001',
    )
    await expect(page.locator('[data-testid="executive-logo"] svg')).toBeVisible()
    const library = page.locator('[data-testid="report-library"]')
    await expect(library.locator('[data-testid="library-certificate-number"]').first()).toHaveText(
      'CRT-2026-000001',
    )
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      library.locator('[data-testid="library-feedback-pdf"]').first().click(),
    ])
    expect(download.suggestedFilename()).toBe(`feedback-ACC-2026-000001-${locale}.pdf`)
    await capture(page, 'board-reports-task69', testInfo)
  })
})
