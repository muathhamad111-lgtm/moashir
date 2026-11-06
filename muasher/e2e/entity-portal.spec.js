/* eslint-disable playwright/no-conditional-in-test */
import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'

const SHOTS = 'e2e/__screenshots__/37a'
const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  mobile: { width: 390, height: 844 },
}
const NAMAA = {
  id: 7601,
  roles: ['entity'],
  entityId: 6001,
  name: 'نورة بنت فهد العتيبي',
}
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'

mkdirSync(SHOTS, { recursive: true })

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function suffixOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en-ltr' : 'ar-rtl'
}

function signIn(page, testInfo, theme = 'light') {
  return seedSignedIn(page, NAMAA, { locale: localeOf(testInfo), theme })
}

async function open(page, path, ready) {
  await page.goto(path)
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator(ready).first()).toBeVisible({ timeout: 10000 })
}

async function capture(page, name, { fullPage = true } = {}) {
  await settleFonts(page)
  await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage })
}

async function noHorizontalOverflow(page) {
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  )
  expect(overflow).toBeLessThanOrEqual(1)
}

test.describe('entity portal shell', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await signIn(page, testInfo)
  })

  test('uses the B side navigation with the slim top bar and marks the item', async ({ page }) => {
    await open(page, '/dashboard', '.journey-hero__title')
    await expect(page.locator('.sidebar')).toHaveCount(0)
    const nav = page.locator('[data-testid="portal-side-nav"]')
    await expect(nav.locator('.side-nav__list').first().locator('.side-nav-item')).toHaveCount(8)
    await expect(nav.locator('.side-nav-item.is-active')).toHaveAttribute('href', '/dashboard')
    await expect(
      nav.locator('.side-nav-item[href="/messages"] .side-nav-item__count'),
    ).toContainText('1')
    await expect(page.locator('.portal-top-bar__area')).toBeVisible()
    await nav.locator('.side-nav-item[href="/payments"]').click()
    await expect(page).toHaveURL(/\/payments$/)
    await expect(nav.locator('.side-nav-item.is-active')).toHaveAttribute('href', '/payments')
  })

  test('shows the attention strip with context and expands it inline', async ({ page }) => {
    await open(page, '/dashboard', '[data-testid="attention-strip"]')
    const strip = page.locator('[data-testid="attention-strip"]')
    await expect(strip.locator('.attention-strip__list--desktop .attention-item')).toHaveCount(3)
    await expect(strip).toContainText('ACC-2026-000004')
    await expect(strip).toContainText('25%')
    await strip.locator('.attention-item').filter({ hasText: 'ACC-2026-000004' }).first().click()
    await expect(page).toHaveURL(/\/assessment\/accredited\/form\/9101$/)
    await expect(page.locator('[data-testid="attention-strip"]')).toHaveCount(0)
  })

  test('opens the account menu as a popover, moves focus inside and closes with Escape', async ({
    page,
  }) => {
    await open(page, '/dashboard', '.account-menu__chip')
    const chip = page.locator('.account-menu__chip')
    await chip.click()
    const panel = page.locator('.account-menu__panel')
    await expect(panel).toBeVisible()
    await expect(panel.locator('.portal-menu-entry')).toHaveCount(6)
    await expect(panel.locator(':focus')).toHaveCount(1)
    await page.keyboard.press('Escape')
    await expect(panel).toHaveCount(0)
    await expect(chip).toBeFocused()
    await chip.click()
    await panel.locator('.portal-menu-entry[href="/help"]').click()
    await expect(page).toHaveURL(/\/help$/)
    await expect(page.locator('.path-guide-card').first()).toBeVisible()
  })

  test('the bell opens the notification centre', async ({ page }) => {
    await open(page, '/dashboard', '.portal-bell')
    await expect(page.locator('.portal-bell__dot')).toBeVisible()
    await page.locator('.portal-bell').click()
    await expect(page).toHaveURL(/\/notifications$/)
    await expect(page.locator('.notifications-center__title')).toBeVisible()
  })

  test('sends /assessment/accredited to the open request form', async ({ page }) => {
    await page.goto('/assessment/accredited')
    await expect(page).toHaveURL(/\/assessment\/accredited\/form\/9101$/)
    await expect(page.locator('.mobile-tab-bar')).toHaveCount(0)
  })
})

test.describe('journey home', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await signIn(page, testInfo)
  })

  test('derives the stations and the next step from the real contracts', async ({
    page,
  }, testInfo) => {
    await open(page, '/dashboard', '[data-testid="next-step"]')
    await expect(page.locator('.journey-station')).toHaveCount(4)
    await expect(page.locator('.journey-station--current')).toHaveCount(1)
    await expect(page.locator('.journey-path__badge')).toHaveText(
      localeOf(testInfo) === 'en' ? 'Station 3 of 4' : 'المحطة 3 من 4',
    )
    const next = page.locator('[data-testid="next-step"]')
    await expect(next.locator('[role="progressbar"]')).toBeVisible()
    await expect(next.locator('.next-step__action--primary')).toHaveAttribute(
      'href',
      '/assessment/accredited/form/9101',
    )
    await next.locator('.next-step__action--ghost').click()
    await expect(page.locator('.station-guide__step')).toHaveCount(4)
    await page.keyboard.press('Escape')
    await expect(page.locator('.station-guide')).toHaveCount(0)
  })

  test('opens a notification from the glance card and marks it read', async ({ page }) => {
    await open(page, '/dashboard', '.glance-notifications .notification-item')
    const first = page.locator('.glance-notifications .notification-item').first()
    await expect(first).toHaveClass(/is-unread/)
    await first.click()
    await expect(page).toHaveURL(/\/messages\/[0-9a-f-]{36}$/)
  })
})

test.describe('notification centre', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await signIn(page, testInfo)
  })

  test('lists day groups, filters unread and marks everything read', async ({ page }) => {
    await open(page, '/notifications', '.notification-center-item')
    await expect(page.locator('[role="tablist"] [role="tab"]')).toHaveCount(2)
    expect(await page.locator('.notification-day-group').count()).toBeGreaterThanOrEqual(2)
    await expect(page.locator('.notification-center-item')).toHaveCount(6)
    await page.locator('[role="tab"]').nth(1).click()
    await expect(page).toHaveURL(/tab=unread/)
    await expect(page.locator('.notification-center-item')).toHaveCount(2)
    await page.locator('.notifications-center__hero .btn').click()
    await expect(page.locator('.empty-state')).toBeVisible()
    await expect(page.locator('.portal-bell__dot')).toHaveCount(0)
  })

  test('archives a notification', async ({ page }) => {
    await open(page, '/notifications', '.notification-center-item')
    const before = await page.locator('.notification-center-item').count()
    await page.locator('.notification-center-item__archive').first().click()
    await expect(page.locator('.notification-center-item')).toHaveCount(before - 1)
  })

  test('saves an e-mail preference and keeps mandatory types locked', async ({ page }) => {
    await open(page, '/notifications', '.notification-preferences-card')
    await page.locator('.notification-preferences-card .btn').click()
    const dialog = page.locator('.modal__panel')
    await expect(dialog.locator('.notification-preference-row').first()).toBeVisible()
    const locked = dialog.locator('.notification-preference-row', { hasText: /PAY|الدفع|Payment/ })
    await expect(locked.first().locator('input').first()).toBeDisabled()
    await dialog.locator('.notification-preference-row .checkbox:not(.is-disabled)').first().click()
    await expect(dialog.locator('.modal__footer .btn--primary')).toBeEnabled()
    await dialog.locator('.modal__footer .btn--primary').click()
    await expect(page.locator('.modal__panel')).toHaveCount(0)
    await expect(page.locator('.toast').first()).toBeVisible()
  })
})

test.describe('mobile portal', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize(VIEWPORTS.mobile)
    await signIn(page, testInfo)
  })

  test('uses the bottom tab bar on hubs and the More screen for the rest', async ({ page }) => {
    await open(page, '/dashboard', '.mobile-tab-bar')
    await expect(page.locator('[data-testid="portal-side-nav"]')).toBeHidden()
    await expect(page.locator('.attention-strip__mobile .attention-item')).toHaveCount(1)
    await expect(page.locator('.attention-strip__summary')).toBeVisible()
    await noHorizontalOverflow(page)
    await page.locator('.mobile-tab-bar__tab[href="/more"]').click()
    await expect(page).toHaveURL(/\/more$/)
    const services = page.locator('.portal-more__group').first().locator('.portal-menu-entry')
    await expect(services).toHaveCount(4)
    await expect(services.nth(3).locator('.portal-menu-entry__value')).toHaveText('25%')
    await noHorizontalOverflow(page)
  })
})

test.describe('portal screenshots', () => {
  for (const theme of ['light', 'dark']) {
    for (const [device, viewport] of Object.entries(VIEWPORTS)) {
      test(`captures the portal screens — ${theme} ${device}`, async ({ page }, testInfo) => {
        test.setTimeout(90000)
        await page.emulateMedia({ reducedMotion: 'reduce' })
        await page.setViewportSize(viewport)
        await signIn(page, testInfo, theme)
        const suffix = `${theme}-${device}-${suffixOf(testInfo)}`

        await open(page, '/dashboard', '[data-testid="next-step"]')
        await noHorizontalOverflow(page)
        await capture(page, `home-${suffix}`)

        await open(page, '/notifications', '.notification-center-item')
        await capture(page, `notifications-${suffix}`)

        if (device === 'desktop') {
          await open(page, '/dashboard', '.account-menu__chip')
          await page.locator('.account-menu__chip').click()
          await capture(page, `account-menu-${suffix}`, { fullPage: false })
          await page.keyboard.press('Escape')
        } else {
          await page.locator('[data-testid="attention-strip"] .attention-strip__toggle').click()
          await capture(page, `strip-expanded-${suffix}`, { fullPage: false })
          await open(page, '/more', '.portal-more__identity')
          await capture(page, `more-${suffix}`)
        }

        await open(page, '/help', '.path-guide-card__steps')
        await capture(page, `help-${suffix}`)
        await expect(page.locator('.portal-help__title')).toBeVisible()
      })
    }
  }
})
