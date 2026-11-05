import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedLocale, seedSignedIn } from './support/session'

const SHOTS = 'e2e/__screenshots__/cms'

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffix(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function localeOf(testInfo) {
  return isEn(testInfo) ? 'en' : 'ar'
}

const EDITED_TITLE = { ar: 'قِس تميّز جهتك اليوم.', en: 'Measure your excellence today.' }

async function signInAs(page, roles, testInfo, extra = {}) {
  await seedSignedIn(page, { roles, name: 'Content Officer', ...extra }, { locale: localeOf(testInfo) })
}

async function openEditor(page) {
  await page.goto('/admin/settings/homepage')
  await expect(page.locator('.cms-section-row').first()).toBeVisible({ timeout: 15000 })
}

test('a Content Officer edits a home section in the draft, publishes it and the public home shows it', async ({ page }, testInfo) => {
  await signInAs(page, ['content_officer'], testInfo)
  await openEditor(page)
  await page.screenshot({ path: `${SHOTS}/editor-${suffix(testInfo)}.png`, fullPage: true })

  const hero = page.locator('.sortable-list__row').filter({ hasText: /hero/ }).first()
  await hero.locator('.cms-section-row__actions .btn--secondary').click()
  const dialog = page.locator('.modal').last()
  await dialog.locator('input[name="title.ar"]').fill(EDITED_TITLE.ar)
  await dialog.locator('input[name="title.en"]').fill(EDITED_TITLE.en)
  await page.screenshot({ path: `${SHOTS}/section-form-${suffix(testInfo)}.png`, fullPage: true })
  await dialog.locator('.modal__footer .btn--primary').click()
  await expect(dialog).toBeHidden()
  await expect(page.locator('.publication-control .badge')).toHaveClass(/badge--info/)

  await page.locator('.publication-control .btn--primary').click()
  await page.locator('.modal .btn--primary').last().click()
  await expect(page.locator('.publication-control .badge')).toHaveClass(/badge--success/)

  await page.locator('.publication-control__view').click()
  await expect(page).toHaveURL(/\/$/)
  await expect(page.locator('.home-hero__title')).toContainText(EDITED_TITLE[localeOf(testInfo)], { timeout: 15000 })
})

test('sections reorder by drag and drop and by the arrow buttons', async ({ page }, testInfo) => {
  await signInAs(page, ['content_officer'], testInfo)
  await openEditor(page)
  const names = page.locator('.cms-section-row__key')
  const before = await names.allTextContents()
  await page.locator('.sortable-list__row').nth(2).dragTo(page.locator('.sortable-list__row').nth(0))
  await expect(names.first()).toHaveText(before[2])
  await page.locator('.sortable-list__row').nth(0).locator('.sortable-list__moves button').nth(1).click()
  await expect(names.nth(1)).toHaveText(before[2])
  await expect(page.locator('.publication-control .badge')).toHaveClass(/badge--info/)
})

test('a section item is added with an uploaded image and hidden from the draft', async ({ page }, testInfo) => {
  await signInAs(page, ['content_officer'], testInfo)
  await openEditor(page)
  const journey = page.locator('.sortable-list__row').filter({ hasText: 'entity-journey' })
  await journey.locator('.cms-section-row__actions .btn--ghost').first().click()
  const items = page.locator('.modal').last()
  await expect(items.locator('.cms-items__row')).toHaveCount(6)
  await items.locator('.cms-items__footer .btn--primary').click()
  const form = page.locator('.modal').last()
  await form.locator('input[name="title.ar"]').fill('الاستدامة')
  await form.locator('input[type="file"]').setInputFiles({
    name: 'stage.png',
    mimeType: 'image/png',
    buffer: Buffer.from('89504e470d0a1a0a0000000d4948445200000001000000010806000000', 'hex'),
  })
  await expect(form.locator('.content-image-field__image')).toHaveAttribute('src', /\/api\/v1\/content\/files\//)
  await form.locator('.modal__footer .btn--primary').click()
  await expect(items.locator('.cms-items__row')).toHaveCount(7)
  await items.locator('.cms-items__row').last().locator('.toggle-switch__track').click()
  await expect(items.locator('.cms-items__row').last().locator('input[type="checkbox"]')).not.toBeChecked()
  await page.screenshot({ path: `${SHOTS}/items-${suffix(testInfo)}.png`, fullPage: true })
})

test('site settings refuse an unsafe link with the server field error', async ({ page }, testInfo) => {
  await signInAs(page, ['content_officer'], testInfo)
  await openEditor(page)
  await page.locator('.tabs__tab').last().click()
  await expect(page.locator('.site-settings__form')).toBeVisible()
  await page.locator('input[name="quickUrl-0"]').fill('javascript:alert(1)')
  await page.locator('.site-settings__footer .btn--primary').click()
  await expect(page.locator('.site-settings__form .field__error, .site-settings__form [role="alert"]').first()).toBeVisible()
  await page.screenshot({ path: `${SHOTS}/site-settings-${suffix(testInfo)}.png`, fullPage: true })
})

test('KPI catalog filters on the server and the detail page shows the value history', async ({ page }, testInfo) => {
  await seedLocale(page, localeOf(testInfo))
  await page.goto('/kpis')
  await expect(page.locator('.kpi-card').first()).toBeVisible({ timeout: 15000 })
  await page.locator('.kpis-page__tabs .tabs__tab').nth(3).click()
  await expect(page.locator('.kpis-page__tabs .tabs__tab').nth(3)).toHaveAttribute('aria-selected', 'true')
  await page.locator('.toolbar__search input').fill('جمع')
  await expect(page.locator('.kpi-card').first()).toBeVisible()
  await page.locator('.toolbar__search input').fill('')
  await page.locator('.kpis-page__tabs .tabs__tab').first().click()
  await page.locator('.kpi-card').first().click()
  await expect(page).toHaveURL(/\/kpis\/[a-z0-9-]+$/)
  await expect(page.locator('.kpi-history__row').first()).toBeVisible({ timeout: 15000 })
  await page.screenshot({ path: `${SHOTS}/kpi-detail-${suffix(testInfo)}.png`, fullPage: true })
})

test('KPI administration: create, record a value and reorder everything by drag and drop', async ({ page }, testInfo) => {
  await signInAs(page, ['content_officer'], testInfo)
  await page.goto('/admin/kpis')
  await expect(page.locator('.table:not(.table--loading) tbody .table__row').first()).toBeVisible({ timeout: 15000 })

  await page.locator('.toolbar__actions .btn--primary').click()
  const form = page.locator('.modal').last()
  await form.locator('input[name="name.ar"]').fill('عدد الشراكات المجتمعية')
  await form.locator('input[name="name.en"]').fill('Community partnerships')
  await form.locator('input[name="value"]').fill('42')
  await page.screenshot({ path: `${SHOTS}/kpi-form-${suffix(testInfo)}.png`, fullPage: true })
  await form.locator('.modal__footer .btn--primary').click()
  await expect(form).toBeHidden()
  await expect(page.locator('.stats-card__value').first()).toHaveText('32')

  await page.locator('.table .actions-dropdown > button').first().click()
  await page.locator('[role="menuitem"]').nth(1).click()
  const values = page.locator('.modal').last()
  await values.locator('input[name="value"]').fill('488')
  await values.locator('input[name="measuredOn"]').fill('2026-07-15')
  await values.locator('.kpi-values__form .btn--primary').click()
  await expect(values.locator('.kpi-history__row').first()).toContainText('488')
  await values.locator('.modal__close').click()

  await page.locator('.toolbar__actions .btn--secondary').click()
  const reorder = page.locator('.modal').last()
  await expect(reorder.locator('.sortable-list__row')).toHaveCount(32)
  const firstName = await reorder.locator('.kpi-reorder__name').first().textContent()
  await reorder.locator('.sortable-list__row').nth(0).dragTo(reorder.locator('.sortable-list__row').nth(3))
  await expect(reorder.locator('.kpi-reorder__name').nth(3)).toHaveText(firstName)
  await reorder.locator('.kpi-reorder__footer .btn--primary').click()
  await expect(reorder).toBeHidden()
  await expect(page.locator('.toast--success').last()).toBeVisible()
})

test('the Super Admin gives a reason before the identity changes', async ({ page }, testInfo) => {
  await signInAs(page, [], testInfo, { superAdmin: true, name: 'Super Admin' })
  await page.goto('/admin/branding')
  await expect(page.locator('.accent-swatch')).toHaveCount(9)
  await page.locator('.accent-swatch').nth(2).click()
  await page.locator('.branding-page__actions .btn--primary').click()
  const dialog = page.locator('.modal').last()
  await expect(dialog.locator('textarea')).toBeVisible()
  await dialog.locator('textarea').fill('Aligning the back office with the approved identity guide.')
  await page.screenshot({ path: `${SHOTS}/branding-reason-${suffix(testInfo)}.png`, fullPage: true })
  await dialog.locator('.modal__footer .btn--primary').click()
  await expect(page.locator('html')).toHaveAttribute('data-accent', 'teal')
})

test('public pages render the published CMS content in both directions', async ({ page }, testInfo) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await seedLocale(page, localeOf(testInfo))
  for (const [path, selector, name] of [
    ['/', '.home-hero__title', 'home'],
    ['/kpis', '.kpi-card', 'kpis'],
    ['/lenses', '.lens-card', 'lenses'],
    ['/assessment/accredited', '.accredited-intro__benefit', 'accredited-intro'],
    ['/assessment/advanced', '.advanced-content__item', 'advanced-intro'],
  ]) {
    await page.goto(path)
    await expect(page.locator(selector).first()).toBeVisible({ timeout: 15000 })
    await page.screenshot({ path: `${SHOTS}/public-${name}-${suffix(testInfo)}.png`, fullPage: true })
  }
})
