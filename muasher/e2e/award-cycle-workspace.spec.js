import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

const PHONE = { width: 390, height: 844 }
const DESKTOP = { width: 1280, height: 800 }
const LOAD_TIMEOUT = 15_000
const SYSTEM_ADMIN = { id: 1, roles: ['system_admin'] }
const RUNNING_CYCLE = 31
const CLOSED_CYCLE = 30

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function say(testInfo, en, ar) {
  return isEn(testInfo) ? en : ar
}

async function shot(page, name, testInfo) {
  const suffix = isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
  await page.screenshot({ path: `test-results/${name}-${suffix}.png`, fullPage: true })
}

async function openCycle(page, testInfo, viewport, cycleId) {
  await page.setViewportSize(viewport)
  await seedSignedIn(page, SYSTEM_ADMIN, { locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto(`/admin/award/cycles/${cycleId}`)
  await expect(page.locator('.cycle-overview')).toBeVisible({ timeout: LOAD_TIMEOUT })
}

function pageOverflow(page) {
  return page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
}

function tab(page, key) {
  return page.locator(`.cycle-tabs__tab[data-tab="${key}"]`)
}

async function expectActiveTab(page, key) {
  await expect(page.locator('.cycle-tabs__tab.is-active')).toHaveCount(1)
  await expect(tab(page, key)).toHaveClass(/is-active/)
  await expect(tab(page, key)).toHaveAttribute('aria-current', 'page')
}

async function expectSharedHeader(page, title) {
  await expect(page.locator('h1')).toHaveCount(1)
  await expect(page.locator('h1')).toHaveText(title)
  await expect(page.locator('.cycle-setup__header .cycle-actions__buttons')).toBeVisible()
  await expect(page.locator('.cycles-page .cycle-editor__back')).toHaveCount(0)
}

test.describe('Award cycle page', () => {
  test('switches between the stages under one shared header', async ({ page }, testInfo) => {
    await openCycle(page, testInfo, DESKTOP, RUNNING_CYCLE)
    const title = (await page.locator('h1').textContent()).trim()
    await expect(page.locator('.cycle-tabs__tab')).toHaveCount(3)
    await expect(page.locator('nav.cycle-tabs')).toHaveAttribute(
      'aria-label',
      say(testInfo, 'Cycle sections', 'أقسام الدورة'),
    )
    await expect(tab(page, 'details')).toHaveText(say(testInfo, 'Details', 'التفاصيل'))
    await expectActiveTab(page, 'details')
    await expectSharedHeader(page, title)
    await expect(page.locator('.cycle-overview__fact')).toHaveCount(6)
    await shot(page, 'award-cycle-workspace-details', testInfo)

    await tab(page, 'filtering').click()
    await expect(page).toHaveURL(new RegExp(`/admin/award/cycles/${RUNNING_CYCLE}/filtering$`))
    await expectActiveTab(page, 'filtering')
    await expect(page.locator('.cycles-page h2.cycles-page__title')).toHaveText(
      say(testInfo, 'Filtering queue', 'قائمة التصفية'),
    )
    await expect(
      page.locator('.table:not(.table--loading) tbody .table__row').first(),
    ).toBeVisible()
    await expect(page.locator('.cycle-overview')).toHaveCount(0)
    await expectSharedHeader(page, title)
    await shot(page, 'award-cycle-workspace-filtering', testInfo)

    await tab(page, 'committee').click()
    await expect(page).toHaveURL(new RegExp(`/admin/award/cycles/${RUNNING_CYCLE}/committee$`))
    await expectActiveTab(page, 'committee')
    await expect(page.locator('.award-page-header.is-embedded h2')).toBeVisible()
    await expectSharedHeader(page, title)

    await tab(page, 'details').click()
    await expect(page).toHaveURL(new RegExp(`/admin/award/cycles/${RUNNING_CYCLE}$`))
    await expectActiveTab(page, 'details')
    await expect(page.locator('.cycle-overview')).toBeVisible()
  })

  test('keeps the results tab active on the national board', async ({ page }, testInfo) => {
    await openCycle(page, testInfo, DESKTOP, CLOSED_CYCLE)
    await expect(page.locator('.cycle-tabs__tab')).toHaveCount(5)
    await page.goto(`/admin/award/cycles/${CLOSED_CYCLE}/results/national`)
    await expect(page.locator('.cycle-tabs')).toBeVisible({ timeout: LOAD_TIMEOUT })
    await expectActiveTab(page, 'results')
    await expect(page.locator('h1')).toHaveCount(1)
  })

  test('scrolls the tabs sideways on a 390 px phone without moving the page', async ({
    page,
  }, testInfo) => {
    await openCycle(page, testInfo, PHONE, CLOSED_CYCLE)
    const tabs = page.locator('nav.cycle-tabs')
    await expect(page.locator('.cycle-tabs__tab')).toHaveCount(5)
    expect(await pageOverflow(page)).toBe(0)
    expect(await tabs.evaluate((nav) => nav.scrollWidth > nav.clientWidth)).toBe(true)
    expect(await tabs.evaluate((nav) => getComputedStyle(nav).overflowX)).toBe('auto')
    await tab(page, 'results').scrollIntoViewIfNeeded()
    expect(await tabs.evaluate((nav) => Math.abs(nav.scrollLeft))).toBeGreaterThan(0)
    expect(await pageOverflow(page)).toBe(0)
    await tab(page, 'results').click()
    await expect(page).toHaveURL(new RegExp(`/admin/award/cycles/${CLOSED_CYCLE}/results$`))
    await expectActiveTab(page, 'results')
    expect(await pageOverflow(page)).toBe(0)
    await shot(page, 'award-cycle-workspace-phone', testInfo)
  })
})
