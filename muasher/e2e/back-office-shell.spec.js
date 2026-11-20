import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { closeSuccess, decisionAction } from './support/dialogs'
import { AA_NON_TEXT, AA_TEXT, contrastOf } from './support/contrast'

const IGNORED_CONSOLE = /Failed to load resource|50\d|Service Unavailable/i
const SHOTS = 'e2e/__screenshots__/shell'
const SIDEBAR_LOGO = /moashir-logo-on-light/
const DARK_LOGO = /moashir-logo-on-dark/
const SHELL_COLORS = {
  sidebar: 'rgb(255, 255, 255)',
  content: 'rgb(248, 248, 248)',
  primary: 'rgb(152, 216, 211)',
  secondary: 'rgb(10, 35, 37)',
}
const TTS_COLORS = { primary: 'rgb(212, 8, 140)', secondary: 'rgb(74, 42, 125)' }

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

for (const { state, prepare } of SIDEBAR_STATES) {
  test(`TTS shell: light content, white sidebar, primary active item even for a dark-mode user — ${state}`, async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await seedSession(page, {
      roles: ['request_follower', 'content_officer'],
      locale: isEn(testInfo) ? 'en' : 'ar',
      theme: 'dark',
    })
    await page.goto('/admin/requests')
    await clickUntil(page, '.error-state__retry .btn', 'table')
    await expect(page.locator('html')).toHaveAttribute('dir', isEn(testInfo) ? 'ltr' : 'rtl')
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')
    await expect(page.locator('html')).toHaveAttribute('data-shell', 'tts')

    const active = page.locator('.sidebar__item.is-active')
    await expect(active).toHaveCount(1)
    await expect(active).toHaveAttribute('aria-current', 'page')
    await expect(active).toHaveAttribute('href', '/admin/requests?path=accredited')

    await prepare(page)
    await settleAnimations(page)

    const shell = await page.evaluate(() => {
      const style = (selector) => getComputedStyle(document.querySelector(selector))
      const sidebar = document.querySelector('.sidebar').getBoundingClientRect()
      const main = document.querySelector('.app-layout__content').getBoundingClientRect()
      const header = document.querySelector('.navbar--app').getBoundingClientRect()
      return {
        itemBg: style('.sidebar__item.is-active').backgroundColor,
        sidebarBg: style('.sidebar').backgroundColor,
        contentBg: style('.app-layout').backgroundColor,
        headerBg: style('.navbar--app').backgroundColor,
        sidebarAtStart:
          document.documentElement.dir === 'rtl'
            ? sidebar.left > main.left
            : sidebar.left < main.left,
        headerAbove: header.bottom <= sidebar.top && header.bottom <= main.top,
      }
    })
    expect(shell).toEqual({
      itemBg: SHELL_COLORS.primary,
      sidebarBg: SHELL_COLORS.sidebar,
      contentBg: SHELL_COLORS.content,
      headerBg: SHELL_COLORS.secondary,
      sidebarAtStart: true,
      headerAbove: true,
    })
    await expect(page.locator('.sidebar .identity-card__logo')).toHaveAttribute('src', SIDEBAR_LOGO)
    const preview = page.locator('.sidebar [data-testid="sidebar-preview-platform"]')
    await expect(preview).toBeVisible()
    await expect(preview).toHaveAttribute('href', '/')
    await expect(preview).toHaveAttribute('target', '_blank')
    await expect(page.locator('.navbar--app img')).toHaveCount(0)

    await page.screenshot({ path: `${SHOTS}/shell-tts-${state}-${suffix(testInfo)}.png` })
    await expectSidebarAtAa(page, state)
    expect(errors).toEqual([])
  })
}

test('the top bar controls stay readable on hover', async ({ page }, testInfo) => {
  await seedSession(page, {
    roles: ['request_follower', 'content_officer'],
    locale: isEn(testInfo) ? 'en' : 'ar',
  })
  await page.goto('/admin/requests')
  await expect(page.locator('html')).toHaveAttribute('data-shell', 'tts')

  for (const control of ['.navbar__locale', '.navbar__settings']) {
    await page.locator(control).hover()
    expect(await contrastOf(page, `${control}:hover`)).toBeGreaterThanOrEqual(AA_TEXT)
  }
})

test('unified workspace: module groups, no role switcher, profile lists roles without a permissions list', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedSession(page, {
    roles: ['request_follower', 'content_officer'],
    locale: isEn(testInfo) ? 'en' : 'ar',
  })
  await page.goto('/dashboard')
  await expect(page.locator('.sidebar__caption').first()).toBeVisible()
  await expect(
    page.locator('.sidebar__caption', {
      hasText: /Content|المحتوى والمعايير|System administration|إدارة النظام/,
    }),
  ).toHaveCount(0)
  await expect(page.locator('.sidebar__menu--headless .sidebar__item').first()).toBeVisible()
  await expect(page.getByRole('combobox', { name: /role|دور/i })).toHaveCount(0)

  await page.locator('.navbar__avatar').click()
  await page.getByRole('menuitem').first().click()
  await expect(page).toHaveURL(/\/account$/)
  await expect(page.locator('.account-page__role-list .badge')).toHaveCount(2)
  await expect(page.locator('.permission-module')).toHaveCount(0)
  await expect(page.locator('#account-permissions-title')).toHaveCount(0)
  await expect(page.locator('.account-page__grid input, .account-page__grid select')).toHaveCount(0)
  await expect(page.getByTestId('change-password-form')).toBeVisible()
  await page.screenshot({ path: `${SHOTS}/account-${suffix(testInfo)}.png`, fullPage: true })
  expect(errors).toEqual([])
})

test('the pending tasks of every held role show as counts on the sidebar, with no Action Center', async ({
  page,
}, testInfo) => {
  await seedSession(page, {
    roles: ['request_follower', 'content_officer'],
    locale: isEn(testInfo) ? 'en' : 'ar',
  })
  await page.goto('/dashboard')
  await expect(page.locator('[data-board="request_follower"]')).toBeVisible()
  await expect(page.locator('.dashboard-board-nav__link')).toHaveCount(2)
  await expect(page.locator('.action-center')).toHaveCount(0)
  await expect(page.locator('.sidebar__count').first()).toBeVisible()
  await page.screenshot({
    path: `${SHOTS}/dashboard-unified-${suffix(testInfo)}.png`,
    fullPage: true,
  })
})

test('branding: System Administrator sets the primary and secondary colours, the back office applies them, the public pages do not', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedSession(page, { roles: ['system_admin'], locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/admin/branding?tab=visual')
  const primary = page.locator('.brand-color-field[data-role="primary"] input[type="text"]')
  const secondary = page.locator('.brand-color-field[data-role="secondary"] input[type="text"]')
  await expect(primary).toHaveValue('#98d8d3')
  await expect(secondary).toHaveValue('#0a2325')
  await expect(page.locator('.navbar--app')).toHaveCSS('background-color', SHELL_COLORS.secondary)

  await primary.fill('#777777')
  await expect(page.locator('.brand-contrast--blocked')).toBeVisible()
  await expect(page.locator('.branding-page__actions .btn--primary')).toBeDisabled()

  await primary.fill('#d4088c')
  await secondary.fill('#4a2a7d')
  await expect(page.locator('.brand-contrast--blocked')).toHaveCount(0)
  await expect(page.locator('.navbar--app')).toHaveCSS('background-color', SHELL_COLORS.secondary)
  await expect(page.locator('.logo-variant')).toHaveCount(3)
  await expect(page.locator('.logo-variant').first()).toHaveAttribute('data-surface', 'dark')

  await page.locator('.branding-page__actions .btn--primary').click()
  await expect(page.locator('.toast').first()).toBeVisible()
  await expect(page.locator('.navbar--app')).toHaveCSS('background-color', TTS_COLORS.secondary)
  await page.screenshot({ path: `${SHOTS}/branding-${suffix(testInfo)}.png`, fullPage: true })

  await page.locator('.sidebar__item').first().click()
  await expect(page.locator('.sidebar__item.is-active')).toHaveCSS(
    'background-color',
    TTS_COLORS.primary,
  )
  await expect(page.locator('.sidebar__item.is-active')).toHaveCSS('color', 'rgb(255, 255, 255)')
  await page.screenshot({ path: `${SHOTS}/dashboard-tts-colors-${suffix(testInfo)}.png` })

  await page.locator('.navbar__avatar').click()
  await page.locator('[role="menuitem"].is-danger').click()
  await expect(page).toHaveURL(/\/auth\/login/)
  await expect(page.locator('html')).not.toHaveAttribute('data-shell', /.+/)
  expect(
    await page.evaluate(() =>
      getComputedStyle(document.documentElement).getPropertyValue('--brand-primary').trim(),
    ),
  ).toBe('')
  expect(errors).toEqual([])
})

test('branding: the site logo, made for a light background, drives the sidebar and the global reset returns it to the brand default', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedSession(page, {
    roles: ['system_admin'],
    locale: isEn(testInfo) ? 'en' : 'ar',
    theme: 'dark',
  })
  await page.goto('/admin/branding?tab=visual')
  await expect(page.locator('.sidebar .identity-card__logo')).toHaveAttribute('src', SIDEBAR_LOGO)
  await expect(page.locator('.logo-variant[data-surface="light"]')).toHaveCount(0)
  await expect(
    page.locator('.logo-variant[data-surface="dark"] .logo-variant__image'),
  ).toHaveAttribute('src', DARK_LOGO)
  const siteField = page.locator('.logo-variant[data-surface="site"]')
  await siteField.locator('input[type="file"]').setInputFiles({
    name: 'moashir-on-light.png',
    mimeType: 'image/png',
    buffer: Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
      'base64',
    ),
  })
  await expect(siteField.locator('.logo-variant__image')).toHaveAttribute('src', /^blob:/)
  await expect(siteField.locator('.logo-variant__warning')).toBeVisible()
  await page.locator('.branding-page__actions .btn--primary').click()
  await expect(page.locator('.toast').first()).toBeVisible()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')
  await expect(page.locator('.sidebar .identity-card__logo')).toHaveAttribute(
    'src',
    /^\/api\/v1\/content\/files\/[0-9a-f-]{36}$/,
  )
  await expect(page.locator('.logo-variant button:not(.file-upload__dropzone)')).toHaveCount(0)
  await page.screenshot({
    path: `${SHOTS}/branding-logo-sidebar-${suffix(testInfo)}.png`,
    fullPage: true,
  })

  await page.locator('.branding-page__actions .btn--ghost').click()
  await decisionAction(page).click()
  await expect(page.locator('.sidebar .identity-card__logo')).toHaveAttribute('src', SIDEBAR_LOGO)
  expect(errors).toEqual([])
})

test('the visual identity tab is hidden from the content officer', async ({ page }, testInfo) => {
  await seedSession(page, {
    roles: ['content_officer'],
    locale: isEn(testInfo) ? 'en' : 'ar',
  })
  await page.goto('/admin/branding?tab=visual')
  await expect(page.locator('.tabs__tab')).toHaveCount(2)
  await expect(page.locator('.brand-color-field')).toHaveCount(0)
  await expect(page.locator('#identity-general-heading')).toBeVisible()
})

test('brand identity is hidden from and forbidden to staff without either permission', async ({
  page,
}, testInfo) => {
  await seedSession(page, { roles: ['finance_officer'], locale: isEn(testInfo) ? 'en' : 'ar' })
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
  await expect(page.locator('.settings-hub__card[href="/admin/audit-log"]')).toBeVisible()
  await expect(page.locator('.sidebar__item.is-active')).toHaveCount(0)
  await page.screenshot({
    path: `${SHOTS}/settings-hub-light-${suffix(testInfo)}.png`,
    fullPage: true,
  })
})

async function formAsideOf(page) {
  return page.evaluate(() => {
    const section = document.querySelector('.site-settings__group')
    const title = section.querySelector('legend').getBoundingClientRect()
    const field = section.querySelector('input').getBoundingClientRect()
    const rtl = document.documentElement.dir === 'rtl'
    return {
      beside: rtl ? title.left >= field.right : title.right <= field.left,
      above: title.bottom <= field.top,
      overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
    }
  })
}

test('form pages put each section title beside its fields on a wide screen and above them on a phone', async ({
  page,
}, testInfo) => {
  await seedSession(page, {
    roles: ['system_admin'],
    locale: isEn(testInfo) ? 'en' : 'ar',
    theme: 'light',
  })
  await page.setViewportSize({ width: 1440, height: 900 })
  await page.goto('/admin/branding')
  await expect(page.locator('.site-settings__group').first()).toBeVisible()
  expect(await formAsideOf(page)).toMatchObject({ beside: true, overflow: false })
  await page.screenshot({ path: `${SHOTS}/form-aside-wide-${suffix(testInfo)}.png` })

  await page.setViewportSize({ width: 390, height: 844 })
  await expect
    .poll(() => formAsideOf(page))
    .toEqual({ beside: false, above: true, overflow: false })
})

test('the System Administrator header keeps every control on one row at a narrow tablet width', async ({
  page,
}, testInfo) => {
  await page.setViewportSize({ width: 466, height: 900 })
  await seedSession(page, { roles: ['system_admin'], locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/admin/settings')
  const navbar = page.locator('header.navbar--app')
  await expect(navbar.locator('.navbar__avatar')).toBeVisible()

  const rowTops = await navbar.evaluate((header) =>
    [...header.children].map((child) => Math.round(child.getBoundingClientRect().top)),
  )
  expect(new Set(rowTops).size).toBe(1)
  expect(await navbar.evaluate((header) => header.scrollWidth <= header.clientWidth)).toBe(true)
})
