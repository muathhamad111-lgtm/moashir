import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

// One width rule for every signed-in page: `<main>` fills the column beside the sidebar, and the
// page (and any full-bleed band inside it) spans that column, sidebar expanded or collapsed.
const ADMIN = {
  id: 7001,
  roles: ['system_admin'],
  superAdmin: true,
  name: { ar: 'نورة', en: 'Noura' },
}
const ENTITY = { id: 6001, roles: ['entity'], entityId: 6001, name: { ar: 'نماء', en: 'Namaa' } }
const ADMIN_PAGES = [
  '/dashboard',
  '/admin/requests',
  '/admin/settings',
  '/admin/settings/users',
  '/account',
]
const ENTITY_PAGES = ['/dashboard', '/entity/profile', '/paths/self', '/help', '/notifications']
const BANDS =
  '.b-band__inner, .profile-header__inner, .portal-area__header--band .portal-area__hero'

function locale(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

async function openPage(page, path) {
  await page.goto(path)
  await expect(page.locator('#app-main, #portal-main')).toHaveAttribute('aria-busy', 'false')
  await expect(page.locator('main > :not(.account-completion-banner)').first()).toBeVisible()
}

function spanOf(page) {
  return page.evaluate((bands) => {
    const main = document.querySelector('#app-main, #portal-main')
    const box = main.getBoundingClientRect()
    const root = [...main.children].find(
      (el) =>
        !el.matches('.account-completion-banner, .app-loading-screen') &&
        el.getClientRects().length,
    )
    const gapsOf = (el) => {
      const rect = el.getBoundingClientRect()
      return [Math.round(rect.left - box.left), Math.round(box.right - rect.right)]
    }
    const bandGaps = [...main.querySelectorAll(bands)].map((band) => {
      const parent = band.parentElement.getBoundingClientRect()
      const rect = band.getBoundingClientRect()
      return [Math.round(rect.left - parent.left), Math.round(parent.right - rect.right)]
    })
    return { main: Math.round(box.width), root: gapsOf(root), bands: bandGaps }
  }, BANDS)
}

async function expectFullSpan(page) {
  const span = await spanOf(page)
  expect(span.root).toEqual([0, 0])
  span.bands.forEach((gaps) => expect(gaps).toEqual([0, 0]))
  return span.main
}

test('back-office pages fill the content column, and collapsing widens every one the same', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, ADMIN, { locale: locale(testInfo) })
  await page.setViewportSize({ width: 1440, height: 900 })
  for (const path of ADMIN_PAGES) {
    await test.step(path, async () => {
      await openPage(page, path)
      const expanded = await expectFullSpan(page)
      await page.locator('.navbar__toggle').click()
      await expect(page.locator('.sidebar--collapsed')).toBeVisible()
      await expect.poll(async () => (await spanOf(page)).main).toBeGreaterThan(expanded)
      await expectFullSpan(page)
    })
  }
})

test('entity portal pages and their bands fill the content column on a wide screen', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, ENTITY, { locale: locale(testInfo) })
  await page.setViewportSize({ width: 1920, height: 1000 })
  const widths = []
  for (const path of ENTITY_PAGES) {
    await test.step(path, async () => {
      await openPage(page, path)
      widths.push(await expectFullSpan(page))
    })
  }
  expect(new Set(widths).size).toBe(1)
})
