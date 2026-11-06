import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'

const SHOTS = '../agents/tasks/reports/Task-65'
const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const NAMAA = { id: 7601, roles: ['entity'], entityId: 6001, name: 'نورة بنت فهد العتيبي' }
const RAWABI = { id: 7603, roles: ['entity'], entityId: 6003, name: 'سارة عبدالله' }
const ADMIN = { id: 9101, roles: ['system_admin'], name: 'مدير النظام' }
const ACHIEVED = '.modal:has(.level-achieved__body)'
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'

mkdirSync(SHOTS, { recursive: true })

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

async function open(page, path, ready) {
  await page.goto(path)
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator(ready).first()).toBeVisible({ timeout: 15000 })
}

async function capture(page, name, testInfo, { fullPage = true } = {}) {
  await settleFonts(page)
  const suffix = localeOf(testInfo) === 'en' ? 'en-ltr' : 'ar-rtl'
  await page.screenshot({ path: `${SHOTS}/${name}-${suffix}.png`, fullPage })
}

async function closeAchieved(page) {
  await expect(page.locator('.account-menu__level .level-badge')).toHaveAttribute(
    'data-tier',
    'silver',
  )
  const later = page.locator(`${ACHIEVED} .btn--ghost`)
  if (await later.isVisible().catch(() => false)) await later.click()
}

test.describe('direction B — entity home tiers', () => {
  test.beforeEach(async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(DESKTOP)
  })

  test('shows the silver level from the latest issued certificate with re-assessment', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, NAMAA, {
      locale: localeOf(testInfo),
      theme: 'light',
      showLevelAchieved: true,
    })
    await open(page, '/dashboard', '.level-hero')
    const dialog = page.locator(`${ACHIEVED} [role="dialog"]`)
    await expect(dialog).toBeVisible()
    await expect(page.locator(`${ACHIEVED} [data-autofocus]`)).toBeFocused()
    await capture(page, 'level-achieved', testInfo, { fullPage: false })
    await page.keyboard.press('Escape')
    await expect(dialog).toHaveCount(0)
    const hero = page.locator('.level-hero')
    await expect(hero).toHaveAttribute('data-tier', 'silver')
    await expect(hero.locator('.level-emblem__seal')).toBeVisible()
    await expect(hero.locator('[data-testid="level-reassessment"]')).toBeVisible()
    await expect(page.locator('.account-menu__level .level-badge')).toHaveAttribute(
      'data-tier',
      'silver',
    )
    await expect(page.locator('[data-testid="level-mini-card"] .level-emblem')).toHaveAttribute(
      'data-tier',
      'silver',
    )
    await capture(page, 'home-silver', testInfo)
    await page.reload()
    await expect(page.locator('.level-hero')).toBeVisible()
    await expect(page.locator(`${ACHIEVED} [role="dialog"]`)).toHaveCount(0)
  })

  test('shows the neutral placeholder and the start action without a certificate', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, RAWABI, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/dashboard', '.level-hero')
    const hero = page.locator('.level-hero')
    await expect(hero.locator('.level-emblem--placeholder')).toBeVisible()
    await expect(hero.locator('.level-emblem__seal')).toHaveCount(0)
    await expect(page.locator(`${ACHIEVED} [role="dialog"]`)).toHaveCount(0)
    await capture(page, 'home-no-level', testInfo)
  })

  test('renders the home in dark mode and on mobile with the tab bar', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, NAMAA, { locale: localeOf(testInfo), theme: 'dark' })
    await open(page, '/dashboard', '.level-hero')
    await closeAchieved(page)
    await capture(page, 'home-dark', testInfo)
    await page.setViewportSize(MOBILE)
    await expect(page.locator('.mobile-tab-bar')).toBeVisible()
    await expect(page.locator('[data-testid="portal-side-nav"]')).toBeHidden()
    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    )
    expect(overflow).toBeLessThanOrEqual(1)
    await capture(page, 'home-mobile-dark', testInfo)
  })
})

async function visibleBoxes(locator) {
  const boxes = []
  for (const element of await locator.all()) {
    if (await element.isVisible()) boxes.push(await element.boundingBox())
  }
  return boxes
}

function overlaps(boxes) {
  return boxes.some((box, index) =>
    boxes.some(
      (other, otherIndex) =>
        otherIndex > index &&
        box.x < other.x + other.width &&
        other.x < box.x + box.width &&
        box.y < other.y + other.height &&
        other.y < box.y + box.height,
    ),
  )
}

test.describe('direction B — mobile 390 charts', () => {
  test('keeps the track labels and the bar axis apart and gives the bars a full row', async ({
    page,
  }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(MOBILE)
    await seedSignedIn(page, NAMAA, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/dashboard', '.ranked-bars__row')

    const trackLabels = page.locator('.level-track__name, .level-track__threshold')
    const trackBoxes = await visibleBoxes(trackLabels)
    expect(trackBoxes.length).toBeGreaterThanOrEqual(4)
    expect(overlaps(trackBoxes)).toBe(false)

    const axisBoxes = await visibleBoxes(page.locator('.ranked-bars__axis-label'))
    expect(axisBoxes).toHaveLength(3)
    expect(overlaps(axisBoxes)).toBe(false)

    const bar = await page.locator('.ranked-bars__bar').first().boundingBox()
    const list = await page.locator('.ranked-bars__list').boundingBox()
    expect(bar.width).toBeGreaterThan(list.width * 0.9)

    await expect(page.locator('.level-hero .level-ring__figure')).toContainText('74.5')
    await expect(page.locator('.radar-chart__caption')).toHaveText(
      localeOf(testInfo) === 'en' ? 'Current result' : 'النتيجة الحالية',
    )
    await expect(page.locator('.b-card__legend-item--previous')).toHaveCount(0)
    await capture(page, 'home-mobile-light', testInfo)
  })
})

test.describe('direction B — side navigation', () => {
  test('collapses, remembers the choice and opens a tool teaser', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(DESKTOP)
    await seedSignedIn(page, NAMAA, { locale: localeOf(testInfo) })
    await open(page, '/dashboard', '[data-testid="portal-side-nav"]')
    await closeAchieved(page)
    const collapse = page.locator('[data-testid="portal-nav-collapse"]')
    await expect(collapse).toHaveAttribute('aria-expanded', 'true')
    await collapse.focus()
    await page.keyboard.press('Enter')
    await expect(page.locator('.portal-layout')).toHaveClass(/portal-layout--nav-collapsed/)
    await capture(page, 'nav-collapsed', testInfo, { fullPage: false })
    await page.reload()
    await expect(page.locator('.portal-layout')).toHaveClass(/portal-layout--nav-collapsed/)
    await page.locator('[data-testid="portal-nav-collapse"]').click()
    await expect(page.locator('.portal-layout')).not.toHaveClass(/portal-layout--nav-collapsed/)

    await page.locator('[data-testid="portal-tools-toggle"]').click()
    const tools = page.locator('.side-nav__list--tools .side-nav-item')
    await expect(tools).toHaveCount(8)
    await expect(tools.nth(1).locator('.coming-soon-chip')).toHaveCount(0)
    await tools.nth(3).click()
    await expect(page).toHaveURL(/\/tools\/evidence-library$/)
    await expect(page.locator('[data-testid="tool-teaser"]')).toBeVisible()
    await expect(page.locator('.side-nav__list--tools .side-nav-item.is-active')).toHaveCount(1)
    const box = await tools.nth(0).boundingBox()
    expect(box.height).toBeGreaterThanOrEqual(24)
    await capture(page, 'tool-teaser', testInfo)
  })

  test('lists the tools in More on mobile', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(MOBILE)
    await seedSignedIn(page, NAMAA, { locale: localeOf(testInfo) })
    await open(page, '/more', '[data-testid="more-tools"]')
    await closeAchieved(page)
    await expect(page.locator('[data-testid="more-tools"] .portal-menu-entry')).toHaveCount(8)
    await capture(page, 'more-mobile', testInfo)
  })
})

test.describe('direction B — staff', () => {
  test('shows the level ribbon and history on the entity record', async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(DESKTOP)
    await seedSignedIn(page, ADMIN, { locale: localeOf(testInfo), theme: 'light' })
    await open(page, '/admin/entities/6001', '[data-testid="level-ribbon"]')
    await expect(page.locator('[data-testid="level-ribbon"]')).toHaveAttribute(
      'data-tier',
      'silver',
    )
    await expect(page.locator('[data-testid="level-history"] .level-history__item')).toHaveCount(2)
    await capture(page, 'staff-entity-record', testInfo)
    await open(page, '/dashboard', '.sidebar')
    await capture(page, 'staff-sidebar', testInfo, { fullPage: false })
  })
})
