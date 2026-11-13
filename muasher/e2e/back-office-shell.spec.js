import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { closeSuccess, decisionAction } from './support/dialogs'
import { AA_NON_TEXT, AA_TEXT, contrastOf } from './support/contrast'

const IGNORED_CONSOLE = /Failed to load resource|50\d|Service Unavailable/i
const SHOTS = 'e2e/__screenshots__/shell'
const REPORT_SHOTS = '../agents/tasks/reports/Task-70'
const SIDEBAR_LOGO = /moashir-logo-on-dark/
const SIDEBAR_BY_THEME = {
  dark: { background: 'rgb(6, 24, 25)' },
  light: { background: 'rgb(10, 35, 37)' },
}

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffix(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function seedSession(page, { roles, locale, theme = 'dark' }) {
  return seedSignedIn(
    page,
    { id: 7001, roles, name: { ar: 'نورة بنت سعد', en: 'Noura Saad' } },
    { locale, theme },
  )
}

function trackConsole(page) {
  const errors = []
  page.on('console', (message) => {
    if (message.type() === 'error' && !IGNORED_CONSOLE.test(message.text()))
      errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))
  return errors
}

async function clickUntil(page, trigger, target) {
  await expect(async () => {
    if (!(await page.locator(target).first().isVisible())) {
      const retry = page.locator(trigger)
      if (await retry.first().isVisible()) await retry.first().click()
    }
    await expect(page.locator(target).first()).toBeVisible({ timeout: 1000 })
  }).toPass({ timeout: 15000 })
}

async function keepExpanded(page) {
  await expect(page.locator('.sidebar')).not.toHaveClass(/sidebar--collapsed/)
}

async function collapseSidebar(page) {
  await page.locator('.navbar__toggle').click()
  await expect(page.locator('.sidebar')).toHaveClass(/sidebar--collapsed/)
  await expect(page.locator('.sidebar__label').first()).toBeHidden()
}

async function focusByKeyboard(page, selector) {
  await page.keyboard.press('Shift')
  await page.locator(selector).first().focus()
}

async function expectSidebarAtAa(page, state) {
  const idle = '.sidebar__item:not(.is-active):visible'
  expect(await contrastOf(page, idle)).toBeGreaterThanOrEqual(AA_TEXT)
  expect(await contrastOf(page, '.sidebar__item.is-active')).toBeGreaterThanOrEqual(AA_TEXT)
  expect(await contrastOf(page, '.sidebar__item.is-active .sidebar__icon')).toBeGreaterThanOrEqual(
    AA_NON_TEXT,
  )
  expect(
    await contrastOf(page, '.sidebar__item--advanced:not(.is-active) .sidebar__icon'),
  ).toBeGreaterThanOrEqual(AA_NON_TEXT)
  if (state === 'expanded')
    expect(await contrastOf(page, '.sidebar__caption')).toBeGreaterThanOrEqual(AA_TEXT)

  await page.locator(idle).first().hover()
  expect(await contrastOf(page, `${idle}:hover`)).toBeGreaterThanOrEqual(AA_TEXT)

  await focusByKeyboard(page, idle)
  expect(
    await contrastOf(page, `${idle}:focus-visible`, { property: 'outlineColor' }),
  ).toBeGreaterThanOrEqual(AA_NON_TEXT)
  await focusByKeyboard(page, '.sidebar__item.is-active')
  expect(
    await contrastOf(page, '.sidebar__item.is-active:focus-visible', {
      property: 'outlineColor',
    }),
  ).toBeGreaterThanOrEqual(AA_NON_TEXT)
  await page.locator('.sidebar__item.is-active').blur()
  await page.mouse.move(0, 0)
}

async function settleAnimations(page) {
  await page.waitForFunction(() =>
    document.getAnimations().every((animation) => animation.playState !== 'running'),
  )
}

const SIDEBAR_STATES = [
  { state: 'expanded', prepare: keepExpanded },
  { state: 'collapsed', prepare: collapseSidebar },
]

for (const theme of ['dark', 'light']) {
  for (const { state, prepare } of SIDEBAR_STATES) {
    test(`always-dark sidebar with the curved active tab — ${theme} ${state}`, async ({
      page,
    }, testInfo) => {
      const errors = trackConsole(page)
      await seedSession(page, {
        roles: ['request_follower', 'content_officer'],
        locale: isEn(testInfo) ? 'en' : 'ar',
        theme,
      })
      await page.goto('/admin/requests')
      await clickUntil(page, '.error-state__retry .btn', 'table')
      await expect(page.locator('html')).toHaveAttribute('dir', isEn(testInfo) ? 'ltr' : 'rtl')
      await expect(page.locator('html')).toHaveAttribute('data-theme', theme)

      const active = page.locator('.sidebar__item.is-active')
      await expect(active).toHaveCount(1)
      await expect(active).toHaveAttribute('aria-current', 'page')
      await expect(active).toHaveAttribute('href', '/admin/requests?path=accredited')

      await prepare(page)
      await settleAnimations(page)

      const geometry = await page.evaluate(() => {
        const item = document.querySelector('.sidebar__item.is-active')
        const sidebar = document.querySelector('.sidebar')
        const main = document.querySelector('.app-layout__main')
        const before = getComputedStyle(item, '::before')
        const after = getComputedStyle(item, '::after')
        const itemBox = item.getBoundingClientRect()
        const sidebarBox = sidebar.getBoundingClientRect()
        const rtl = document.documentElement.dir === 'rtl'
        return {
          itemBg: getComputedStyle(item).backgroundColor,
          contentBg: getComputedStyle(main).backgroundColor,
          sidebarBg: getComputedStyle(sidebar).backgroundColor,
          sidebarImage: getComputedStyle(sidebar).backgroundImage,
          beforeImage: before.backgroundImage,
          afterImage: after.backgroundImage,
          beforeContent: before.content,
          flush: rtl
            ? Math.abs(itemBox.left - sidebarBox.left) < 1
            : Math.abs(itemBox.right - sidebarBox.right) < 1,
          overflow: [
            getComputedStyle(item).overflow,
            getComputedStyle(item.closest('.sidebar__menu')).overflow,
            getComputedStyle(sidebar).overflow,
          ],
        }
      })
      expect(geometry.itemBg).toBe(geometry.contentBg)
      expect(geometry.sidebarBg).not.toBe(geometry.contentBg)
      expect(geometry.sidebarBg).toBe(SIDEBAR_BY_THEME[theme].background)
      expect(geometry.sidebarImage).toContain('linear-gradient')
      await expect(page.locator('.sidebar__logo')).toHaveAttribute('src', SIDEBAR_LOGO)
      expect(geometry.beforeContent).toBe('""')
      expect(geometry.beforeImage).toContain('radial-gradient')
      expect(geometry.afterImage).toContain('radial-gradient')
      expect(geometry.beforeImage).toContain(isEn(testInfo) ? 'at 0% 0' : 'at 100% 0')
      expect(geometry.flush).toBe(true)
      expect(geometry.overflow).not.toContain('hidden')

      await page.screenshot({ path: `${SHOTS}/shell-${theme}-${state}-${suffix(testInfo)}.png` })
      await page.screenshot({
        path: `${REPORT_SHOTS}/sidebar-${theme}-${state}-${suffix(testInfo)}.png`,
      })
      await expectSidebarAtAa(page, state)
      expect(errors).toEqual([])
    })
  }
}

test('unified workspace: module groups, no role switcher, profile lists roles and permissions', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedSession(page, {
    roles: ['request_follower', 'content_officer'],
    locale: isEn(testInfo) ? 'en' : 'ar',
  })
  await page.goto('/dashboard')
  await expect(page.locator('.sidebar__caption')).toHaveCount(6)
  await expect(page.getByRole('combobox', { name: /role|دور/i })).toHaveCount(0)

  await page.locator('.navbar__avatar').click()
  await page.getByRole('menuitem').first().click()
  await expect(page).toHaveURL(/\/account$/)
  await expect(page.locator('.account-page__role-list .badge')).toHaveCount(2)
  await expect(page.locator('.permission-module').first()).toBeVisible()
  await expect(page.locator('.account-page input, .account-page select')).toHaveCount(0)
  await page.screenshot({ path: `${SHOTS}/account-${suffix(testInfo)}.png`, fullPage: true })
  expect(errors).toEqual([])
})

test('Action Center aggregates the tasks of every held role', async ({ page }, testInfo) => {
  await seedSession(page, {
    roles: ['request_follower', 'content_officer'],
    locale: isEn(testInfo) ? 'en' : 'ar',
  })
  await page.goto('/dashboard')
  await expect(page.locator('[data-board="request_follower"]')).toBeVisible()
  await expect(page.locator('.dashboard-board-nav__link')).toHaveCount(2)
  await expect(page.locator('[data-action="assign_requests"]')).toBeVisible()
  await expect(page.locator('[data-action="publish_content_changes"]')).toBeVisible()
  await page.screenshot({
    path: `${SHOTS}/dashboard-unified-${suffix(testInfo)}.png`,
    fullPage: true,
  })
})

test('branding: System Administrator picks an accent, the back office applies it, the public pages do not', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedSession(page, { roles: ['system_admin'], locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/admin/branding')
  await expect(page.locator('.accent-swatch')).toHaveCount(9)
  await expect(page.locator('html')).toHaveAttribute('data-accent', 'brand_default')

  await page.locator('.accent-swatch').nth(6).click()
  await expect(page.locator('.branding-preview__frame').first()).toHaveAttribute(
    'data-accent',
    'amber',
  )
  await expect(page.locator('html')).toHaveAttribute('data-accent', 'brand_default')
  await expect(page.locator('.contrast-check .badge--success')).toHaveCount(4)

  await expect(page.locator('.logo-variant')).toHaveCount(1)
  await expect(page.locator('.logo-variant')).toHaveAttribute('data-surface', 'dark')
  for (const mode of ['dark', 'light']) {
    const frame = page.locator(`.branding-preview__frame[data-theme="${mode}"]`)
    await expect(frame.locator('.branding-preview__logo')).toHaveAttribute('src', SIDEBAR_LOGO)
    const sidebar = await frame
      .locator('.branding-preview__sidebar')
      .evaluate((element) => getComputedStyle(element).backgroundColor)
    expect(sidebar).toBe(SIDEBAR_BY_THEME[mode].background)
  }

  await page.locator('.branding-page__actions .btn--primary').click()
  await expect(page.locator('html')).toHaveAttribute('data-accent', 'amber')
  await expect(page.locator('.toast').first()).toBeVisible()
  await page.screenshot({ path: `${SHOTS}/branding-${suffix(testInfo)}.png`, fullPage: true })

  await page.locator('.navbar__avatar').click()
  await page.locator('[role="menuitem"].is-danger').click()
  await expect(page).toHaveURL(/\/auth\/login/)
  await expect(page.locator('html')).not.toHaveAttribute('data-accent', /.+/)
  expect(errors).toEqual([])
})

test('branding: the dark-background logo drives the sidebar in light mode, previewed, and returned to the brand default by the global reset only', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedSession(page, {
    roles: ['system_admin'],
    locale: isEn(testInfo) ? 'en' : 'ar',
    theme: 'light',
  })
  await page.goto('/admin/branding')
  await expect(page.locator('.sidebar__logo')).toHaveAttribute('src', SIDEBAR_LOGO)
  await expect(page.locator('.logo-variant[data-surface="light"]')).toHaveCount(0)
  const darkField = page.locator('.logo-variant[data-surface="dark"]')
  await darkField.locator('input[type="file"]').setInputFiles({
    name: 'moashir-on-dark.svg',
    mimeType: 'image/svg+xml',
    buffer: Buffer.from(
      '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="48"><rect width="160" height="48" rx="8" fill="#f5f8f8"/></svg>',
    ),
  })
  await expect(darkField.locator('.logo-variant__image')).toHaveAttribute('src', /^blob:/)
  await expect(page.locator('.branding-preview__logo')).toHaveCount(2)
  for (const logo of await page.locator('.branding-preview__logo').all())
    await expect(logo).toHaveAttribute('src', /^blob:/)
  await page.locator('.branding-page__actions .btn--primary').click()
  await expect(page.locator('.toast').first()).toBeVisible()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')
  await expect(page.locator('.sidebar__logo')).toHaveAttribute(
    'src',
    /^\/api\/v1\/content\/files\/[0-9a-f-]{36}$/,
  )
  await expect(darkField.locator('.badge--info')).toBeVisible()
  await expect(page.locator('.logo-variant button:not(.file-upload__dropzone)')).toHaveCount(0)
  await page.screenshot({
    path: `${SHOTS}/branding-logo-light-mode-${suffix(testInfo)}.png`,
    fullPage: true,
  })
  await page.screenshot({
    path: `${REPORT_SHOTS}/branding-logo-light-mode-${suffix(testInfo)}.png`,
    fullPage: true,
  })

  await page.locator('.branding-page__actions .btn--ghost').click()
  await decisionAction(page).click()
  await expect(page.locator('.sidebar__logo')).toHaveAttribute('src', SIDEBAR_LOGO)
  await expect(darkField.locator('.badge--info')).toHaveCount(0)
  await expect(darkField.locator('.logo-variant__image')).toHaveAttribute('src', SIDEBAR_LOGO)
  expect(errors).toEqual([])
})

test('branding is hidden from and forbidden to non-administrators', async ({ page }, testInfo) => {
  await seedSession(page, {
    roles: ['content_officer', 'finance_officer'],
    locale: isEn(testInfo) ? 'en' : 'ar',
  })
  await page.goto('/dashboard')
  await expect(page.locator('.sidebar a[href="/admin/branding"]')).toHaveCount(0)
  await page.goto('/admin/branding')
  await expect(page).toHaveURL(/\/403$/)
})

test('maintenance page blocks visitors but keeps sign-in reachable (Q-96)', async ({
  page,
}, testInfo) => {
  await seedSession(page, { roles: ['system_admin'], locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/admin/settings/homepage')
  await clickUntil(page, '.error-state__retry .btn', '.publication-control')
  await page
    .locator('.publication-control')
    .getByRole('button', { name: /^(Unpublish|إلغاء النشر)$/ })
    .click()
  await decisionAction(page).click()
  await closeSuccess(page)
  await expect(page.locator('.publication-control--off')).toBeVisible()

  await page.locator('.navbar__avatar').click()
  await page.locator('[role="menuitem"].is-danger').click()
  await expect(page).toHaveURL(/\/auth\/login/)

  await page.locator('.auth-layout__link').click()
  await expect(page).toHaveURL(/\/maintenance$/)
  await expect(page.locator('.maintenance__title')).toBeVisible()
  await page.screenshot({ path: `${SHOTS}/maintenance-${suffix(testInfo)}.png`, fullPage: true })

  await page.locator('.maintenance__actions a.btn--primary').click()
  await expect(page).toHaveURL(/\/auth\/login/)
  await expect(page.locator('main h1')).toBeVisible()
})

test('settings hub on the new shell offers the branding card to the System Administrator', async ({
  page,
}, testInfo) => {
  await seedSession(page, {
    roles: ['system_admin'],
    locale: isEn(testInfo) ? 'en' : 'ar',
    theme: 'light',
  })
  await page.goto('/admin/settings')
  await expect(page.locator('.settings-hub__card[href="/admin/branding"]')).toBeVisible()
  await expect(page.locator('.sidebar__item.is-active')).toHaveAttribute('href', '/admin/settings')
  await page.screenshot({
    path: `${SHOTS}/settings-hub-light-${suffix(testInfo)}.png`,
    fullPage: true,
  })
})
