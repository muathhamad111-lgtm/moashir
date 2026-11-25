import { test, expect } from '@playwright/test'
import { seedScenario, seedSignedIn } from './support/session'

// M1, the back office as an app on phones: below md (768 px) a bottom tab bar per role and a
// «المزيد» screen replace the sidebar, and the top bar shows the logo or back and the screen title.
// Desktop keeps the sidebar.
const PHONE = { width: 390, height: 844 }
const SMALL_PHONE = { width: 320, height: 640 }
const TABLET = { width: 768, height: 1024 }
const DESKTOP = { width: 1280, height: 800 }
const LOAD_TIMEOUT = 15_000

const TAB_BAR = '.mobile-tab-bar--app'
const tabIds = (page) =>
  page.locator(`${TAB_BAR} [data-tab]`).evaluateAll((tabs) => tabs.map((tab) => tab.dataset.tab))

// Mock users (fixtures of src/mocks/fixtures/users.js); the Super Admin follows the e2e id 900.
const ROLES = [
  {
    key: 'system admin',
    user: { id: 1, roles: ['system_admin'] },
    tabs: ['admin-entities', 'admin-users', 'settings'],
  },
  {
    key: 'Super Admin',
    user: { id: 900, roles: [], superAdmin: true },
    tabs: ['admin-entities', 'admin-users', 'settings'],
  },
  { key: 'criteria officer', user: { id: 3, roles: ['criteria_officer'] }, tabs: ['settings'] },
  {
    key: 'request follower',
    user: { id: 8, roles: ['request_follower'] },
    tabs: ['requests', 'bag-requests', 'admin-entities'],
  },
  {
    key: 'filtering officer',
    user: { id: 13, roles: ['filtering_officer'] },
    tabs: ['award-filtering', 'award-cycles', 'admin-entities'],
  },
  {
    key: 'award secretary',
    user: { id: 18, roles: ['award_secretary'] },
    tabs: ['award-secretary', 'award-results', 'award-cycles'],
  },
  {
    key: 'assessor',
    user: { id: 10, roles: ['assessor'] },
    tabs: ['requests-assigned', 'bag-evaluator'],
  },
  {
    key: 'committee member',
    user: { id: 27, roles: ['assessor'] },
    scenario: 'committee',
    tabs: ['requests-assigned', 'award-committee', 'bag-evaluator'],
  },
  {
    key: 'content officer',
    user: { id: 20, roles: ['content_officer'] },
    tabs: ['admin-contact-messages', 'settings'],
  },
  {
    key: 'finance officer',
    user: { id: 22, roles: ['finance_officer'] },
    tabs: ['finance', 'bag-requests', 'admin-entities'],
  },
]
const FOLLOWER = ROLES[3].user
const ENTITY = { id: 7601, roles: ['entity'], entityId: 6001 }

const DRAWER_TITLE = { ar: 'القائمة', en: 'Menu' }

function locale(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

async function open(page, testInfo, { user, scenario }, path, viewport = PHONE) {
  await page.setViewportSize(viewport)
  if (scenario) await seedScenario(page, scenario)
  await seedSignedIn(page, user, { locale: locale(testInfo) })
  await page.goto(path)
  await expect(page.locator('main').first()).toBeVisible({ timeout: LOAD_TIMEOUT })
}

// The drawer opens at the reading start, so the free backdrop is at the other edge.
function freeBackdropX(testInfo) {
  return locale(testInfo) === 'ar' ? 5 : PHONE.width - 5
}

function pageOverflow(page) {
  return page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
}

test.describe('Back office on a phone: tab bar and «المزيد»', () => {
  for (const role of ROLES) {
    test(`the ${role.key} gets home, the role's tabs and «المزيد», with no side rail`, async ({
      page,
    }, testInfo) => {
      await open(page, testInfo, role, '/dashboard')
      await expect(page.locator(TAB_BAR)).toBeVisible()
      await expect(page.locator('.sidebar')).toBeHidden()
      await expect.poll(() => tabIds(page)).toEqual(['dashboard', ...role.tabs, 'more'])
      await expect(page.locator(`${TAB_BAR} [data-tab="dashboard"]`)).toHaveAttribute(
        'aria-current',
        'page',
      )
      expect(await pageOverflow(page)).toBe(0)
    })
  }

  for (const role of ROLES) {
    test(`the ${role.key}'s shell fits a 320 px phone`, async ({ page }, testInfo) => {
      await open(page, testInfo, role, '/dashboard', SMALL_PHONE)
      await expect(page.locator(TAB_BAR)).toBeVisible()
      expect(await pageOverflow(page)).toBe(0)
    })
  }

  test('«المزيد» lists the remaining destinations, the account entries and sign out', async ({
    page,
  }, testInfo) => {
    await open(page, testInfo, { user: FOLLOWER }, '/dashboard')
    await page.locator(`${TAB_BAR} [data-tab="more"]`).click()
    await expect(page).toHaveURL(/\/menu$/)
    await expect(page.locator('[data-testid="app-more"]')).toBeVisible()
    await expect(page.locator(`${TAB_BAR} [data-tab="more"]`)).toHaveAttribute(
      'aria-current',
      'page',
    )
    const navIds = await page
      .locator('[data-nav]')
      .evaluateAll((entries) => entries.map((entry) => entry.dataset.nav))
    expect(navIds).not.toContain('requests')
    expect(navIds).not.toContain('admin-users')
    await expect(page.locator('[data-testid="app-more-logout"]')).toBeVisible()
    expect(await pageOverflow(page)).toBe(0)
  })

  test('a detail screen keeps its tab lit and goes back to it, staying in the platform', async ({
    page,
  }, testInfo) => {
    // A deep link: no in-app history, so back opens the owning tab instead of leaving.
    await open(page, testInfo, { user: FOLLOWER }, '/admin/requests/9105')
    await expect(page.locator(`${TAB_BAR} [data-tab="requests"]`)).toHaveAttribute(
      'aria-current',
      'page',
    )
    const title = page.locator('.navbar__phone-title')
    await expect(title).not.toBeEmpty()
    await expect(title).toHaveAttribute('aria-hidden', 'true')
    await page.locator('[data-testid="phone-back"]').click()
    await expect(page).toHaveURL(/\/admin\/requests(\?.*)?$/)
    await expect(page.locator('.navbar__phone-logo')).toBeVisible()
  })

  test('back steps to the previous screen of the platform', async ({ page }, testInfo) => {
    await open(page, testInfo, { user: FOLLOWER }, '/menu')
    await page.goto('/notifications')
    await page.locator('[data-testid="phone-back"]').click()
    await expect(page).toHaveURL(/\/menu$/)
  })

  test('the update banner and the page end clear the tab bar', async ({ page }, testInfo) => {
    await open(page, testInfo, { user: FOLLOWER }, '/dashboard')
    const room = await page.evaluate(() => {
      const bar = document.querySelector('.mobile-tab-bar--app').getBoundingClientRect()
      const app = getComputedStyle(document.querySelector('.app-layout'))
      return { bar: bar.height, padding: parseFloat(app.paddingBlockEnd) }
    })
    expect(room.padding).toBeGreaterThanOrEqual(room.bar)
  })
})

test.describe('«المزيد» belongs to its audience', () => {
  test('a staff account never sees the entity «المزيد»', async ({ page }, testInfo) => {
    await open(page, testInfo, { user: FOLLOWER }, '/more')
    await expect(page.locator('.portal-more:not(.app-more)')).toHaveCount(0)
  })

  test('an entity account opening /menu lands on its own «المزيد»', async ({ page }, testInfo) => {
    await open(page, testInfo, { user: ENTITY }, '/menu')
    await expect(page).toHaveURL(/\/more$/)
    await expect(page.locator('[data-testid="app-more"]')).toHaveCount(0)
    await expect(page.locator('.portal-more')).toBeVisible()
  })

  test('the entity portal fits a 320 px phone with its own tab bar', async ({ page }, testInfo) => {
    await open(page, testInfo, { user: ENTITY }, '/dashboard', SMALL_PHONE)
    await expect(page.locator('.mobile-tab-bar:not(.mobile-tab-bar--app)')).toBeVisible()
    expect(await pageOverflow(page)).toBe(0)
  })
})

test.describe('Tablet and desktop keep the sidebar', () => {
  for (const viewport of [TABLET, DESKTOP]) {
    test(`at ${viewport.width} px the sidebar shows and the phone bars do not`, async ({
      page,
    }, testInfo) => {
      await open(page, testInfo, { user: FOLLOWER }, '/dashboard', viewport)
      await expect(page.locator('.sidebar')).toBeVisible()
      await expect(page.locator('.navbar__toggle')).toBeVisible()
      await expect(page.locator(TAB_BAR)).toBeHidden()
      await expect(page.locator('[data-testid="phone-top-bar"]')).toBeHidden()
      expect(await pageOverflow(page)).toBe(0)
    })
  }
})

const SYSADMIN = ROLES[0].user

test.describe('Phone page actions, navigation drawer and filters', () => {
  test('several page actions sit behind one button that opens a bottom sheet', async ({
    page,
  }, testInfo) => {
    await open(page, testInfo, { user: SYSADMIN }, '/admin/entities/6001')
    const toggle = page.locator('[data-testid="page-actions-toggle"]')
    await expect(toggle).toBeVisible()
    const box = await toggle.boundingBox()
    expect(box.height).toBeGreaterThanOrEqual(44)
    await toggle.click()
    const sheet = page.locator('.modal--sheet')
    await expect(sheet).toBeVisible()
    await expect(sheet.locator('.modal__handle')).toBeVisible()
    await expect(
      sheet.locator('[data-testid="page-actions-sheet"] .btn--danger').first(),
    ).toBeVisible()
    for (const action of await sheet.locator('[data-testid="page-actions-sheet"] .btn').all()) {
      expect((await action.boundingBox()).height).toBeGreaterThanOrEqual(44)
    }
    await page.keyboard.press('Escape')
    await expect(sheet).toHaveCount(0)
  })

  test('the actions stay in a row on a desktop', async ({ page }, testInfo) => {
    await open(page, testInfo, { user: SYSADMIN }, '/admin/entities/6001', DESKTOP)
    await expect(page.locator('[data-testid="page-actions-toggle"]')).toHaveCount(0)
    await expect(page.locator('.entity-record__actions .btn--danger').first()).toBeVisible()
  })

  test('the drawer lists exactly the sections of the desktop sidebar and closes on navigation', async ({
    page,
  }, testInfo) => {
    const hrefs = (scope) =>
      page
        .locator(`${scope} a.sidebar__item`)
        .evaluateAll((links) => links.map((link) => link.getAttribute('href')).sort())
    await open(page, testInfo, { user: SYSADMIN }, '/dashboard', DESKTOP)
    await page
      .locator('.sidebar__caption[aria-expanded="false"]')
      .evaluateAll((captions) => captions.forEach((caption) => caption.click()))
    const desktop = await hrefs('.app-layout__body > .sidebar')

    await page.setViewportSize(PHONE)
    await page.locator('[data-testid="nav-drawer-toggle"]').click()
    const drawer = page.locator('.modal--drawer-start')
    await expect(drawer).toBeVisible()
    await drawer
      .locator('.sidebar__caption[aria-expanded="false"]')
      .evaluateAll((captions) => captions.forEach((caption) => caption.click()))
    expect(await hrefs('.modal--drawer-start')).toEqual(desktop)

    await drawer.locator('a.sidebar__item[href="/admin/settings/users"]').click()
    await expect(page).toHaveURL(/\/admin\/settings\/users/)
    await expect(drawer).toHaveCount(0)
  })

  test('the drawer closes on Esc and on the backdrop', async ({ page }, testInfo) => {
    await open(page, testInfo, { user: FOLLOWER }, '/dashboard')
    const toggle = page.locator('[data-testid="nav-drawer-toggle"]')
    await toggle.click()
    await expect(page.locator('.modal--drawer-start')).toBeVisible()
    await expect(page.locator('.modal--drawer-start .modal__title')).toHaveText(
      DRAWER_TITLE[locale(testInfo)],
    )
    await expect(page.locator('.modal--drawer-start .identity-card')).toHaveCount(0)
    await page.keyboard.press('Escape')
    await expect(page.locator('.modal--drawer-start')).toHaveCount(0)
    await toggle.click()
    await page
      .locator('.modal--drawer-start .modal__backdrop')
      .click({ position: { x: freeBackdropX(testInfo), y: 400 } })
    await expect(page.locator('.modal--drawer-start')).toHaveCount(0)
  })

  test('the entity portal has the same drawer with its own sections', async ({
    page,
  }, testInfo) => {
    await open(page, testInfo, { user: ENTITY }, '/dashboard')
    await page.locator('[data-testid="nav-drawer-toggle"]').click()
    await expect(page.locator('.modal--drawer-start .side-nav')).toBeVisible()
    await expect(page.locator('.modal--drawer-start .identity-card')).toHaveCount(0)
    expect(await pageOverflow(page)).toBe(0)
  })

  test('a list filter opens as a bottom sheet behind one «فلترة» button', async ({
    page,
  }, testInfo) => {
    await open(page, testInfo, { user: ENTITY }, '/payments')
    await page.locator('.filter-dialog__toggle').first().click()
    await expect(page.locator('.modal--sheet .filter-dialog__fields')).toBeVisible()
    await expect(page.locator('.modal--sheet .modal__footer .btn').last()).toBeInViewport()
  })

  test('a page filter form is never inline on a phone, and stays inline on a desktop', async ({
    page,
  }, testInfo) => {
    await open(page, testInfo, { user: SYSADMIN }, '/admin/audit-log')
    await expect(page.locator('main .audit-log__filters')).toHaveCount(0)
    await page.locator('[data-testid="phone-filters-toggle"]').click()
    await expect(page.locator('.modal--sheet .audit-log__filters')).toBeVisible()

    await open(page, testInfo, { user: SYSADMIN }, '/admin/audit-log', DESKTOP)
    await expect(page.locator('main .audit-log__filters')).toBeVisible()
    await expect(page.locator('[data-testid="phone-filters-toggle"]')).toHaveCount(0)
  })
})
