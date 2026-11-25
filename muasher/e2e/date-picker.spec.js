import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'
import { pinMockClock } from './support/clock'

const SHOTS = 'e2e/__screenshots__/date-picker'
const VIEWPORTS = {
  desktop: { width: 1440, height: 900 },
  phone: { width: 390, height: 844 },
}
const DEV_OVERLAYS =
  '#__vue-devtools-container__, #vue-inspector-container { display: none !important; }'
const TOLERANCE = 1
const TOUCH_TARGET = 44
const VIEWPORT_MARGIN = 8
const CONTENT_OFFICER = { roles: ['content_officer'], name: 'Content Officer' }

mkdirSync(SHOTS, { recursive: true })

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function localeOf(testInfo) {
  return isEn(testInfo) ? 'en' : 'ar'
}

function text(testInfo, en, ar) {
  return isEn(testInfo) ? en : ar
}

async function capture(page, testInfo, name) {
  await settleFonts(page)
  await page.screenshot({
    path: `${SHOTS}/${name}-${isEn(testInfo) ? 'en-ltr' : 'ar-rtl'}.png`,
    animations: 'disabled',
  })
}

function dateField(scope, name) {
  return scope.locator(`.datepicker:has([name="${name}"])`)
}

async function expectSegments(field, values) {
  await expect
    .poll(() =>
      field
        .locator('.datepicker__segment')
        .evaluateAll((inputs) => inputs.map((input) => input.value)),
    )
    .toEqual(values)
}

function calendar(page) {
  return page.locator('.date-calendar')
}

function focusedDay(page) {
  return calendar(page).locator('.date-calendar__day:focus')
}

async function openRegister(page, testInfo, viewport) {
  await page.setViewportSize(VIEWPORTS[viewport])
  await pinMockClock(page)
  await seedPreferences(page, { locale: localeOf(testInfo) })
  await page.goto('/auth/register')
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(dateField(page, 'foundedOn')).toBeVisible({ timeout: 10000 })
}

async function openKpiValues(page, testInfo) {
  await page.setViewportSize(VIEWPORTS.desktop)
  await pinMockClock(page)
  await seedSignedIn(page, CONTENT_OFFICER, { locale: localeOf(testInfo) })
  await page.goto('/admin/kpis')
  await page.addStyleTag({ content: DEV_OVERLAYS })
  await expect(page.locator('.table:not(.table--loading) tbody .table__row').first()).toBeVisible({
    timeout: 15000,
  })
  await page.locator('.table .dropdown-menu > button').first().click()
  await page.locator('[role="menuitem"]').nth(1).click()
  return page.locator('.modal').last()
}

async function expectBeside(popup, field) {
  const box = await popup.boundingBox()
  const anchor = await field.boundingBox()
  const viewport = popup.page().viewportSize()
  expect(box.y >= anchor.y + anchor.height || box.y + box.height <= anchor.y).toBe(true)
  expect(box.x).toBeGreaterThanOrEqual(0)
  expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + TOLERANCE)
  expect(box.y + box.height).toBeLessThanOrEqual(viewport.height + TOLERANCE)
}

function calendarAlignment(field) {
  return field.evaluate((control, margin) => {
    const viewportWidth = document.documentElement.clientWidth
    const isRtl = getComputedStyle(control).direction === 'rtl'
    const inline = (rect) =>
      isRtl
        ? { start: viewportWidth - rect.right, end: viewportWidth - rect.left }
        : { start: rect.left, end: rect.right }
    const border = inline(control.getBoundingClientRect())
    const panel = inline(
      document.querySelector('.dropdown-popup--calendar').getBoundingClientRect(),
    )
    const fitsAtStart = border.start + (panel.end - panel.start) <= viewportWidth - margin
    return fitsAtStart
      ? { edge: 'start', offset: Math.abs(panel.start - border.start) }
      : { edge: 'end', offset: Math.abs(panel.end - border.end) }
  }, VIEWPORT_MARGIN)
}

async function expectAlignedToFieldEdge(field, edge) {
  const alignment = await calendarAlignment(field)
  expect(alignment.edge).toBe(edge)
  expect(alignment.offset).toBeLessThanOrEqual(TOLERANCE)
}

async function expectNextAtInlineEnd(page, testInfo) {
  const previous = await calendar(page).locator('.date-calendar__nav--previous').boundingBox()
  const next = await calendar(page).locator('.date-calendar__nav--next').boundingBox()
  const towardsEnd = isEn(testInfo) ? next.x - previous.x : previous.x - next.x
  expect(towardsEnd).toBeGreaterThan(0)
}

test('the founding date opens the in-app calendar next to the field and picks a day from the keyboard', async ({
  page,
}, testInfo) => {
  await openRegister(page, testInfo, 'desktop')
  const field = dateField(page, 'foundedOn')
  const button = field.locator('.datepicker__calendar')

  await button.click()
  const dialog = page.getByRole('dialog', { name: text(testInfo, /calendar$/, /^تقويم/) })
  await expect(dialog).toBeVisible()
  await expect(field).toHaveClass(/is-open/)
  await expect(button).toHaveAttribute('aria-expanded', 'true')
  expect(
    await page
      .locator('.dropdown-popup--calendar')
      .evaluate((popup) => popup.matches(':popover-open')),
  ).toBe(true)
  await expect(calendar(page).locator('.date-calendar__title')).toHaveText(
    text(testInfo, 'November 2026', 'نوفمبر 2026'),
  )
  await expect(calendar(page).locator('th').first()).toHaveText(text(testInfo, 'Sun', 'الأحد'))
  await expect(focusedDay(page)).toHaveAttribute('data-date', '2026-11-05')
  await expect(calendar(page).locator('[aria-current="date"]')).toHaveAttribute(
    'data-date',
    '2026-11-05',
  )
  await expect(calendar(page).locator('[data-date="2026-11-06"]')).toBeDisabled()
  await expect(calendar(page).locator('.date-calendar__nav--next')).toBeDisabled()
  await expectBeside(page.locator('.dropdown-popup--calendar'), field)
  // The licence certificate upload (registration fields, 2026-11-25) now precedes the founding date, which moved it to
  // the start column of the grid: the calendar opens aligned to the field's start edge.
  await expectAlignedToFieldEdge(field, 'start')
  await expectNextAtInlineEnd(page, testInfo)
  await capture(page, testInfo, 'register-popover')

  await page.keyboard.press('ArrowUp')
  await expect(focusedDay(page)).toHaveAttribute('data-date', '2026-10-29')
  await page.keyboard.press('ArrowLeft')
  await expect(focusedDay(page)).toHaveAttribute(
    'data-date',
    text(testInfo, '2026-10-28', '2026-10-30'),
  )
  await page.keyboard.press('PageUp')
  await expect(calendar(page).locator('.date-calendar__title')).toHaveText(
    text(testInfo, 'September 2026', 'سبتمبر 2026'),
  )
  await page.keyboard.press('Home')
  await expect(focusedDay(page)).toHaveAttribute('data-date', '2026-09-27')
  await page.keyboard.press('Enter')

  await expect(calendar(page)).toHaveCount(0)
  await expect(button).toBeFocused()
  await expectSegments(field, ['27', '09', '2026'])
})

test('the calendar keeps the focus inside, and closes on Esc and on a click outside', async ({
  page,
}, testInfo) => {
  await openRegister(page, testInfo, 'desktop')
  const field = dateField(page, 'foundedOn')
  const button = field.locator('.datepicker__calendar')

  await button.click()
  await expect(focusedDay(page)).toBeVisible()
  await page.keyboard.press('Tab')
  await expect(calendar(page).locator('.date-calendar__nav--previous')).toBeFocused()
  await page.keyboard.press('Shift+Tab')
  await expect(focusedDay(page)).toHaveAttribute('data-date', '2026-11-05')
  await page.keyboard.press('Escape')
  await expect(calendar(page)).toHaveCount(0)
  await expect(button).toBeFocused()
  await expectSegments(field, ['', '', ''])

  await button.click()
  await expect(calendar(page)).toBeVisible()
  await field.locator('[name="foundedOn"]').click()
  await expect(calendar(page)).toHaveCount(0)

  await field.locator('[name="foundedOn"]').fill('2016-03-21')
  await expectSegments(field, ['21', '03', '2016'])
  await button.click()
  await expect(focusedDay(page)).toHaveAttribute('data-date', '2016-03-21')
  await expect(calendar(page).locator('[data-date="2016-03-21"]').locator('..')).toHaveAttribute(
    'aria-selected',
    'true',
  )
})

test('on a phone the calendar opens as a bottom sheet and a tap picks the day', async ({
  page,
}, testInfo) => {
  await openRegister(page, testInfo, 'phone')
  const field = dateField(page, 'foundedOn')

  await field.locator('.datepicker__calendar').click()
  const sheet = page.locator('.dropdown-sheet')
  await expect(sheet.locator('.date-calendar')).toBeVisible()
  await expect(page.locator('.dropdown-popup')).toHaveCount(0)
  const panel = await sheet.locator('.dropdown-sheet__panel').boundingBox()
  expect(Math.abs(panel.y + panel.height - VIEWPORTS.phone.height)).toBeLessThanOrEqual(TOLERANCE)
  expect(panel.width).toBeGreaterThanOrEqual(VIEWPORTS.phone.width - TOLERANCE)
  const day = await sheet.locator('[data-date="2026-11-03"]').boundingBox()
  expect(day.height).toBeGreaterThanOrEqual(TOUCH_TARGET - TOLERANCE)
  await capture(page, testInfo, 'register-sheet')

  await sheet.locator('[data-date="2026-11-03"]').click()
  await expect(sheet).toHaveCount(0)
  await expectSegments(field, ['03', '11', '2026'])
})

test('the KPI value date is picked from the calendar inside the dialog without closing it', async ({
  page,
}, testInfo) => {
  const values = await openKpiValues(page, testInfo)
  const field = dateField(values, 'measuredOn')
  const button = field.locator('.datepicker__calendar')

  await button.click()
  await expect(calendar(page)).toBeVisible()
  await expectBeside(page.locator('.dropdown-popup--calendar'), field)
  await expectAlignedToFieldEdge(field, 'start')
  await page.keyboard.press('Escape')
  await expect(calendar(page)).toHaveCount(0)
  await expect(values).toBeVisible()
  await expect(button).toBeFocused()

  await values.locator('input[name="value"]').fill('488')
  await button.click()
  await page.keyboard.press('ArrowUp')
  await capture(page, testInfo, 'kpi-values-popover')
  await page.keyboard.press('Enter')
  await expectSegments(field, ['29', '10', '2026'])
  await values.locator('.kpi-values__form .btn--primary').click()
  await expect(values.locator('.kpi-history__row').first()).toContainText('488')
})
