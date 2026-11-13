import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'
import { namedSelect } from './support/dropdown'

const SHOTS = 'e2e/__screenshots__/dropdowns'
const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  mobile: { width: 390, height: 844 },
}
const THEMES = ['light', 'dark']
const SYSTEM_ADMIN = {
  id: 2,
  roles: ['system_admin'],
  name: { ar: 'منيرة بنت خالد العتيبي', en: 'Munira Al-Otaibi' },
}
const SUPER_ADMIN = {
  id: 1,
  roles: [],
  superAdmin: true,
  name: { ar: 'مدير النظام الرئيسي', en: 'Super Admin' },
}
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const GAP = 4
const TOLERANCE = 1

mkdirSync(SHOTS, { recursive: true })

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixOf(testInfo, theme, viewport) {
  return `${theme}-${viewport}-${isEn(testInfo) ? 'en-ltr' : 'ar-rtl'}`
}

async function openAs(page, testInfo, { user = SYSTEM_ADMIN, theme, viewport, route }) {
  await page.setViewportSize(VIEWPORTS[viewport])
  await seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar', theme })
  await page.goto(route)
  await page.addStyleTag({ content: DEV_OVERLAYS })
}

async function capture(page, testInfo, name, theme, viewport) {
  await settleFonts(page)
  await page.screenshot({
    path: `${SHOTS}/${name}-${suffixOf(testInfo, theme, viewport)}.png`,
    animations: 'disabled',
  })
}

function popup(page) {
  return page.locator('.dropdown-popup')
}

function sheet(page) {
  return page.locator('.dropdown-sheet')
}

async function expectTopLayer(surface) {
  expect(await surface.evaluate((element) => element.matches(':popover-open'))).toBe(true)
}

async function geometry(trigger, surface) {
  const anchor = await trigger.boundingBox()
  const box = await surface.boundingBox()
  const viewport = trigger.page().viewportSize()
  return { anchor, box, viewport }
}

async function expectInView(surface) {
  const { box, viewport } = await geometry(surface, surface)
  expect(box.x).toBeGreaterThanOrEqual(0)
  expect(box.y).toBeGreaterThanOrEqual(0)
  expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + TOLERANCE)
  expect(box.y + box.height).toBeLessThanOrEqual(viewport.height + TOLERANCE)
}

async function expectAnchoredField(trigger, surface, testInfo) {
  await expectTopLayer(surface)
  const { anchor, box } = await geometry(trigger, surface)
  expect(Math.abs(box.width - anchor.width)).toBeLessThanOrEqual(TOLERANCE)
  const startEdge = isEn(testInfo)
    ? Math.abs(box.x - anchor.x)
    : Math.abs(box.x + box.width - (anchor.x + anchor.width))
  expect(startEdge).toBeLessThanOrEqual(TOLERANCE)
  const below = box.y - (anchor.y + anchor.height)
  const above = anchor.y - (box.y + box.height)
  expect([below, above].some((gap) => Math.abs(gap - GAP) <= TOLERANCE)).toBe(true)
  await expectInView(surface)
}

async function expectNotCovered(page, items) {
  const count = await items.count()
  for (let index = 0; index < count; index += 1) {
    const item = items.nth(index)
    const box = await item.boundingBox()
    const hit = await page.evaluate(
      ({ x, y }) =>
        document.elementFromPoint(x, y)?.closest('[role="option"], [role="menuitem"]')?.id ??
        'covered',
      { x: box.x + box.width / 2, y: box.y + box.height / 2 },
    )
    expect(hit).not.toBe('covered')
  }
}

async function expectBottomSheet(page) {
  const surface = sheet(page)
  await expectTopLayer(surface)
  await expect(surface).toHaveAttribute('role', 'dialog')
  await expect(surface).toHaveAttribute('aria-modal', 'true')
  const panel = await surface.locator('.dropdown-sheet__panel').boundingBox()
  const viewport = page.viewportSize()
  expect(Math.abs(panel.width - viewport.width)).toBeLessThanOrEqual(TOLERANCE)
  expect(Math.abs(panel.y + panel.height - viewport.height)).toBeLessThanOrEqual(TOLERANCE)
}

async function openFieldSelect(page, testInfo, theme, viewport) {
  await openAs(page, testInfo, {
    user: SUPER_ADMIN,
    theme,
    viewport,
    route: '/admin/settings/assessment?tab=general',
  })
  const trigger = namedSelect(page, 'committeeEntityDataVisibility')
  await trigger.scrollIntoViewIfNeeded()
  await trigger.click()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
  return trigger
}

async function openDialogSelect(page, testInfo, theme, viewport) {
  await openAs(page, testInfo, { theme, viewport, route: '/admin/settings/cities' })
  await page.getByRole('button', { name: /Add city|إضافة مدينة/ }).click()
  const dialog = page.locator('.modal__panel')
  const trigger = namedSelect(dialog, 'regionId')
  await trigger.click()
  return { dialog, trigger }
}

async function expectEscapeKeepsDialog(page, surface, dialog) {
  await page.keyboard.press('Escape')
  await expect(surface).toHaveCount(0)
  await expect(dialog).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)
}

async function openLastRowMenu(page, testInfo, theme, viewport) {
  await openAs(page, testInfo, { theme, viewport, route: '/admin/settings/users' })
  await expect(page.locator('.pagination')).toBeVisible({ timeout: 15000 })
  const lastRow = page.locator('.table:not(.table--loading) tbody .table__row').last()
  const trigger = lastRow.locator('.dropdown-menu > button')
  await trigger.scrollIntoViewIfNeeded()
  await trigger.click()
  await expect(trigger).toHaveAttribute('aria-expanded', 'true')
  return trigger
}

async function openRoleFilter(page, testInfo, theme, viewport) {
  await openAs(page, testInfo, { theme, viewport, route: '/admin/settings/users' })
  const filter = page.getByRole('combobox', {
    name: isEn(testInfo) ? 'Role' : 'الدور',
    exact: true,
  })
  await expect(filter).toBeVisible({ timeout: 15000 })
  const before = await filter.textContent()
  await filter.focus()
  await page.keyboard.press('ArrowDown')
  await page.keyboard.press('ArrowDown')
  return { filter, before }
}

async function openNotifications(page, testInfo, theme, viewport) {
  await openAs(page, testInfo, { theme, viewport, route: '/admin/settings/users' })
  const bell = page.locator('.notification-bell > button')
  await bell.click()
  await expect(page.locator('.notification-panel')).toBeVisible()
  return bell
}

for (const theme of THEMES) {
  test.describe(`dropdown family — ${theme} desktop`, () => {
    const viewport = 'desktop'

    test('the award defaults field select opens attached to its field (operator report)', async ({
      page,
    }, testInfo) => {
      const trigger = await openFieldSelect(page, testInfo, theme, viewport)
      await expect(popup(page)).toBeVisible()
      await expectAnchoredField(trigger, popup(page), testInfo)
      await expect(trigger).toHaveAttribute('aria-activedescendant', /.+/)
      await expectNotCovered(page, popup(page).getByRole('option'))
      await capture(page, testInfo, 'field-award-defaults', theme, viewport)
      await page.keyboard.press('Escape')
      await expect(trigger).toHaveAttribute('aria-expanded', 'false')
      await expect(trigger).toBeFocused()
    })

    test('a field select inside a dialog is never clipped and Escape keeps the dialog open', async ({
      page,
    }, testInfo) => {
      const { dialog, trigger } = await openDialogSelect(page, testInfo, theme, viewport)
      await expect(popup(page)).toBeVisible()
      await expectAnchoredField(trigger, popup(page), testInfo)
      const last = popup(page).getByRole('option').last()
      await last.scrollIntoViewIfNeeded()
      await expectNotCovered(page, last)
      await capture(page, testInfo, 'dialog-city-region', theme, viewport)
      await expectEscapeKeepsDialog(page, popup(page), dialog)
    })

    test('the row menu of the last row of a paginated table opens fully visible', async ({
      page,
    }, testInfo) => {
      const trigger = await openLastRowMenu(page, testInfo, theme, viewport)
      const surface = popup(page)
      await expect(surface.getByRole('menu')).toBeVisible()
      await expect(surface.getByRole('menuitem').first()).toBeFocused()
      await expectTopLayer(surface)
      await expectInView(surface)
      const { anchor, box } = await geometry(trigger, surface)
      const endEdges = [
        Math.abs(box.x + box.width - (anchor.x + anchor.width)),
        Math.abs(box.x - anchor.x),
      ]
      expect(endEdges[isEn(testInfo) ? 0 : 1]).toBeLessThanOrEqual(TOLERANCE)
      await expectNotCovered(page, surface.getByRole('menuitem'))
      await capture(page, testInfo, 'table-last-row-menu', theme, viewport)
      await page.keyboard.press('Escape')
      await expect(trigger).toBeFocused()
    })

    test('a toolbar filter is labelled, shows the active option and closes on Tab without a change', async ({
      page,
    }, testInfo) => {
      const { filter, before } = await openRoleFilter(page, testInfo, theme, viewport)
      const surface = popup(page)
      const activeId = await filter.getAttribute('aria-activedescendant')
      await expect(surface.locator(`[id="${activeId}"]`)).toHaveClass(/is-active/)
      await expectInView(surface)
      await capture(page, testInfo, 'toolbar-filter', theme, viewport)
      await page.keyboard.press('Tab')
      await expect(surface).toHaveCount(0)
      await expect(filter).toHaveText(before)
    })

    test('the notification panel is a labelled dialog on screen', async ({ page }, testInfo) => {
      const bell = await openNotifications(page, testInfo, theme, viewport)
      const surface = popup(page)
      await expect(surface.getByRole('dialog')).toHaveAttribute('aria-labelledby', /.+/)
      await expectTopLayer(surface)
      await expectInView(surface)
      await capture(page, testInfo, 'notifications', theme, viewport)
      await page.keyboard.press('Escape')
      await expect(bell).toBeFocused()
    })
  })

  test.describe(`dropdown family — ${theme} mobile`, () => {
    const viewport = 'mobile'

    test('the award defaults field select opens as a bottom sheet', async ({ page }, testInfo) => {
      const trigger = await openFieldSelect(page, testInfo, theme, viewport)
      await expectBottomSheet(page)
      await expect(sheet(page).getByRole('listbox')).toBeFocused()
      await capture(page, testInfo, 'field-award-defaults', theme, viewport)
      await page.keyboard.press('Escape')
      await expect(trigger).toBeFocused()
    })

    test('a field select inside a dialog opens as a sheet and Escape keeps the dialog open', async ({
      page,
    }, testInfo) => {
      const { dialog } = await openDialogSelect(page, testInfo, theme, viewport)
      await expectBottomSheet(page)
      await expect(sheet(page).getByRole('listbox')).toBeFocused()
      await capture(page, testInfo, 'dialog-city-region', theme, viewport)
      await expectEscapeKeepsDialog(page, sheet(page), dialog)
    })

    test('the row menu of the last row opens as a sheet', async ({ page }, testInfo) => {
      const trigger = await openLastRowMenu(page, testInfo, theme, viewport)
      await expectBottomSheet(page)
      await expect(sheet(page).getByRole('menuitem').first()).toBeFocused()
      await capture(page, testInfo, 'table-last-row-menu', theme, viewport)
      await page.keyboard.press('Escape')
      await expect(trigger).toBeFocused()
    })

    test('a toolbar filter opens as a sheet with a visible active option', async ({
      page,
    }, testInfo) => {
      await openRoleFilter(page, testInfo, theme, viewport)
      await expectBottomSheet(page)
      await expect(sheet(page).locator('[role="option"].is-active')).toBeVisible()
      await capture(page, testInfo, 'toolbar-filter', theme, viewport)
    })

    test('the notification panel opens as a sheet that stays on screen', async ({
      page,
    }, testInfo) => {
      const bell = await openNotifications(page, testInfo, theme, viewport)
      await expectBottomSheet(page)
      await expectInView(sheet(page).locator('.notification-panel'))
      await capture(page, testInfo, 'notifications', theme, viewport)
      await page.keyboard.press('Escape')
      await expect(bell).toBeFocused()
    })
  })
}
