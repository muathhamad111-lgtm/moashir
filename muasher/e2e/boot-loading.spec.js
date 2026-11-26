import { test, expect } from '@playwright/test'
import { seedPreferences, seedScenario, seedSignedIn } from './support/session'

// A reload must never flash the public chrome while the session is checked: the official
// waiting page stands in until the route resolves. The slow-session mock scenario makes the
// session check outlast the waiting page's short delay, as on the production server.

// The slow session adds its own wait on top of the dev server's first load.
const BOOTED = { timeout: 20_000 }

function locale(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

// Records, from the very first paint, whether the public chrome or the waiting page appeared.
function watchBoot(page) {
  return page.addInitScript(() => {
    const seen = { publicChrome: false, waitingMark: false }
    window.__bootSeen = seen
    new MutationObserver(() => {
      if (document.querySelector('.public-layout, .navbar--public')) seen.publicChrome = true
      if (document.querySelector('.app-loading--screen .brand-loader')) seen.waitingMark = true
    }).observe(document, { childList: true, subtree: true })
  })
}

async function reloadSlowly(page, path) {
  await seedScenario(page, 'slow-session')
  await watchBoot(page)
  await page.goto(path)
}

async function expectCleanBoot(page) {
  const seen = await page.evaluate(() => window.__bootSeen)
  expect(seen.publicChrome).toBe(false)
  expect(seen.waitingMark).toBe(true)
  await expect(page.locator('.app-loading--screen')).toHaveCount(0)
}

test('reloading a staff page shows the waiting page, then the shell, never the public chrome', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, { id: 5101, roles: ['system_admin'] }, { locale: locale(testInfo) })
  await reloadSlowly(page, '/dashboard')

  await expect(page.locator('[data-testid="executive-overview"]')).toBeVisible(BOOTED)
  await expect(page.locator('.sidebar')).toBeVisible()
  await expectCleanBoot(page)
})

test('reloading an entity portal page shows the waiting page, never the public chrome', async ({
  page,
}, testInfo) => {
  await seedSignedIn(
    page,
    { id: 5102, roles: ['entity'], entityId: 5001 },
    { locale: locale(testInfo) },
  )
  await reloadSlowly(page, '/dashboard')

  await expect(page.locator('.portal-layout .journey-home')).toBeVisible(BOOTED)
  await expectCleanBoot(page)
})

test('a signed-out reload of a staff page goes to sign-in without the public chrome', async ({
  page,
}, testInfo) => {
  await seedPreferences(page, { locale: locale(testInfo) })
  await reloadSlowly(page, '/dashboard')

  await expect(page).toHaveURL(/\/auth\/login/, BOOTED)
  await expect(page.locator('.auth-layout')).toBeVisible()
  await expectCleanBoot(page)
})
