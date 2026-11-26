import { test, expect } from '@playwright/test'
import { seedScenario, seedSignedIn } from './support/session'

// AJ-05 / AJ-22: the award staff screens fit the phone (no sideways page scroll), their tables read as
// stacked rows when the list is narrow, and the committee table is not cut off beside the side panel.
const PHONE = { width: 390, height: 844 }
const DESKTOP = { width: 1280, height: 800 }
// The lists load through the mock API; under a parallel run the first load can take a while.
const LOAD_TIMEOUT = 15_000

const RUNNING_CYCLE = 31
const ABOVE_THRESHOLD = 9204
const BELOW_THRESHOLD = 9205

const USERS = {
  secretary: { id: 18, roles: ['award_secretary'] },
  filtering: { id: 13, roles: ['filtering_officer'] },
  assessor: { id: 27, roles: ['assessor'] },
}

// Pages whose lists stack: the ready selector waits for loaded rows.
const ROWS = '.table-wrap--stacked tbody .table__row'
const TABLE_SCREENS = [
  {
    name: 'secretary review',
    user: USERS.secretary,
    scenario: 'committee',
    path: `/admin/award/cycles/${RUNNING_CYCLE}/secretary/${BELOW_THRESHOLD}`,
  },
  {
    name: 'regional results',
    user: USERS.secretary,
    scenario: 'results',
    path: `/admin/award/cycles/${RUNNING_CYCLE}/results`,
  },
  {
    name: 'committee assignments',
    user: USERS.assessor,
    scenario: 'committee',
    path: '/admin/committee/assignments',
    // At 1280 px this list keeps the wide desktop table, which already scrolls inside its card in
    // English (outside AJ-22, which is about the phone); only the page itself is checked there.
    scrollsAtDesktop: true,
  },
].map((screen) => ({ ...screen, ready: ROWS }))
const DESKTOP_FIT_SCREENS = TABLE_SCREENS.filter((screen) => !screen.scrollsAtDesktop)

// The filtering decision (AJ-05): a one-column award detail page with no list.
const FILTERING_DECISION = {
  name: 'filtering decision',
  user: USERS.filtering,
  path: `/admin/award/cycles/${RUNNING_CYCLE}/filtering/${ABOVE_THRESHOLD}`,
  ready: '.award-detail__body',
}

function locale(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

async function open(page, testInfo, screen, viewport) {
  await page.setViewportSize(viewport)
  if (screen.scenario) await seedScenario(page, screen.scenario)
  await seedSignedIn(page, screen.user, { locale: locale(testInfo) })
  await page.goto(screen.path)
  await expect(page.locator(screen.ready).first()).toBeVisible({ timeout: LOAD_TIMEOUT })
}

function pageOverflow(page) {
  return page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
}

// How far any stacked table's content runs past its own box, either way (0 when nothing is cut off
// and no inner scrollbar appears).
function tableOverflow(page) {
  return page.evaluate(() =>
    Math.max(
      0,
      ...[...document.querySelectorAll('.table-wrap--stacked')].flatMap((wrap) => [
        wrap.scrollWidth - wrap.clientWidth,
        wrap.scrollHeight - wrap.clientHeight,
      ]),
      ...[...document.querySelectorAll('.table-wrap--stacked .table__value')].map(
        (value) => value.scrollWidth - value.clientWidth,
      ),
    ),
  )
}

test.describe('Award screens on a phone and beside the side panel', () => {
  for (const screen of TABLE_SCREENS) {
    test(`${screen.name} fits a 390 px phone with stacked rows`, async ({ page }, testInfo) => {
      await open(page, testInfo, screen, PHONE)
      expect(await pageOverflow(page)).toBe(0)
      expect(await tableOverflow(page)).toBe(0)

      // Stacked: the row is a block, and its cells carry their column label.
      const firstRow = page.locator(ROWS).first()
      expect(await firstRow.evaluate((row) => getComputedStyle(row).display)).toBe('flex')
      await expect(firstRow.locator('td').nth(1)).toHaveAttribute('data-label', /.+/)
    })

    test(`${screen.name} does not scroll the page sideways at 1280 px`, async ({
      page,
    }, testInfo) => {
      await open(page, testInfo, screen, DESKTOP)
      expect(await pageOverflow(page)).toBe(0)
    })
  }

  for (const screen of DESKTOP_FIT_SCREENS) {
    test(`${screen.name} keeps the list inside its card at 1280 px`, async ({ page }, testInfo) => {
      await open(page, testInfo, screen, DESKTOP)
      expect(await tableOverflow(page)).toBe(0)
    })
  }

  for (const viewport of [PHONE, DESKTOP]) {
    test(`the filtering decision fits ${viewport.width} px`, async ({ page }, testInfo) => {
      await open(page, testInfo, FILTERING_DECISION, viewport)
      expect(await pageOverflow(page)).toBe(0)
    })
  }

  test('the secretary sees every committee evaluation action on the phone', async ({
    page,
  }, testInfo) => {
    await open(page, testInfo, TABLE_SCREENS[0], PHONE)
    const actions = page.locator('.table-wrap--stacked .table__cell--actions :is(.btn, a)')
    await expect(actions.first()).toBeVisible()
    for (const action of await actions.all()) {
      const box = await action.boundingBox()
      expect(box.x).toBeGreaterThanOrEqual(0)
      expect(box.x + box.width).toBeLessThanOrEqual(PHONE.width)
      expect(box.height).toBeGreaterThanOrEqual(44)
    }
  })
})
