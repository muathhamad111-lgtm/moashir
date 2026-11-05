import { mkdirSync, readdirSync, readFileSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'
import { openOption } from './support/dropdown'

const SHOTS = 'e2e/__screenshots__/icons'
const ICON_DIR = 'src/assets/icons/tts'
const PENDING = JSON.parse(readFileSync(`${ICON_DIR}/pending.json`, 'utf8'))
const SVG_NAMES = readdirSync(ICON_DIR)
  .filter((file) => file.endsWith('.svg'))
  .map((file) => file.slice(0, -'.svg'.length))
const PICKABLE = [...new Set([...SVG_NAMES, ...Object.keys(PENDING.glyph)])].sort()
const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  mobile: { width: 390, height: 844 },
}
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const ROWS = '.table:not(.table--loading) tbody .table__row'
const USERS = {
  entity: { id: 7601, roles: ['entity'], entityId: 6001, name: 'نورة بنت فهد العتيبي' },
  follower: { id: 8, roles: ['request_follower', 'filtering_officer'] },
  systemAdmin: { id: 1, roles: ['system_admin'] },
  contentOfficer: { roles: ['content_officer'], name: 'Content Officer' },
}
const SCREENS = [
  { key: 'home', path: '/', ready: '.home-hero__title', header: '.public-navbar' },
  {
    key: 'portal-home',
    path: '/dashboard',
    ready: '.journey-hero__title',
    header: '.portal-top-bar',
    user: USERS.entity,
  },
  {
    key: 'staff-queue',
    path: '/admin/requests',
    ready: ROWS,
    header: '.navbar--app',
    user: USERS.follower,
  },
  {
    key: 'settings',
    path: '/admin/settings/certificates',
    ready: 'input[name="levelThresholds.bronze"]',
    header: '.navbar--app',
    user: USERS.systemAdmin,
  },
]
const FONT_AWESOME_HOST = /fontawesome/i

mkdirSync(SHOTS, { recursive: true })

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function prepare(page, testInfo, user, theme) {
  const preferences = { locale: isEn(testInfo) ? 'en' : 'ar', theme }
  return user ? seedSignedIn(page, user, preferences) : seedPreferences(page, preferences)
}

async function open(page, screen) {
  await page.goto(screen.path)
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator(screen.ready).first()).toBeVisible({ timeout: 15000 })
}

function trackIconRequests(page) {
  const requests = []
  page.on('request', (request) => {
    if (FONT_AWESOME_HOST.test(request.url())) requests.push(request.url())
  })
  return requests
}

function fontAwesomeLeftovers(page) {
  return page.evaluate(() => ({
    classes: [...document.querySelectorAll('[class]')].filter((element) =>
      [...element.classList].some((name) => name.startsWith('fa-')),
    ).length,
    iconElements: document.querySelectorAll('i[class], i[aria-hidden]').length,
  }))
}

function drawnIcons(locator) {
  return locator.evaluateAll((icons) =>
    icons.map((icon) => {
      const box = icon.getBoundingClientRect()
      return {
        name: icon.dataset.icon,
        width: box.width,
        height: box.height,
        path: icon.querySelector('path')?.getAttribute('d')?.length ?? 0,
      }
    }),
  )
}

async function expectDrawn(icons) {
  for (const icon of await drawnIcons(icons)) {
    expect(icon.width, icon.name).toBeGreaterThan(0)
    expect(icon.height, icon.name).toBeGreaterThan(0)
    expect(icon.path, icon.name).toBeGreaterThan(0)
  }
}

test.describe('TTS icons', () => {
  test('every registry icon renders as an inline SVG in the icon picker', async ({
    page,
  }, testInfo) => {
    await prepare(page, testInfo, USERS.contentOfficer)
    await page.goto('/admin/settings/homepage')
    await expect(page.locator('.cms-section-row').first()).toBeVisible({ timeout: 15000 })
    const hero = page.locator('.sortable-list__row').filter({ hasText: /hero/ }).first()
    await hero.locator('.cms-section-row__actions .btn--secondary').click()
    const dialog = page.locator('.modal').last()
    await dialog.locator('.icon-field .dropdown-combobox__input').click()
    const options = page.locator('[role="listbox"] [role="option"]')
    await expect(options).toHaveCount(PICKABLE.length + 1)
    const icons = options.locator('svg.dropdown-option__icon')
    const drawn = await drawnIcons(icons)
    expect(drawn.map((icon) => icon.name).sort()).toEqual(PICKABLE)
    await expectDrawn(icons)
    await openOption(page, 'award').click()
    await expect(dialog.locator('.icon-field__preview svg')).toHaveAttribute('data-icon', 'award')
  })

  test('directional icons are mirrored in Arabic only, other icons never', async ({
    page,
  }, testInfo) => {
    await page.setViewportSize(VIEWPORTS.desktop)
    await prepare(page, testInfo, USERS.follower)
    await open(page, SCREENS[2])
    const chevron = page.locator('.sidebar__chevron svg[data-icon="chevron-right"]').first()
    await expect(chevron).toHaveCSS('scale', isEn(testInfo) ? 'none' : '-1 1')
    await expect(page.locator('.navbar svg[data-icon="bell"]').first()).toHaveCSS('scale', 'none')
    await expect(page.locator('.navbar svg[data-icon="menu"]').first()).toHaveCSS('scale', 'none')
  })

  for (const screen of SCREENS) {
    for (const theme of ['light', 'dark']) {
      test(`${screen.key} (${theme}) draws its header icons without Font Awesome`, async ({
        page,
      }, testInfo) => {
        const requests = trackIconRequests(page)
        await prepare(page, testInfo, screen.user, theme)
        for (const [device, viewport] of Object.entries(VIEWPORTS)) {
          await page.setViewportSize(viewport)
          await open(page, screen)
          const headerIcons = page.locator(`${screen.header} svg.app-icon:visible`)
          await expect(headerIcons.first()).toBeVisible()
          await expectDrawn(headerIcons)
          expect(await fontAwesomeLeftovers(page)).toEqual({ classes: 0, iconElements: 0 })
          await settleFonts(page)
          await page.screenshot({
            path: `${SHOTS}/${screen.key}-${theme}-${device}-${suffixOf(testInfo)}.png`,
            fullPage: true,
          })
        }
        expect(requests).toEqual([])
      })
    }
  }
})
