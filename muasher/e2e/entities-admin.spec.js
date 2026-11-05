import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { clearMockFault, forceMockFault } from './support/mockFaults'
import { chooseNamed } from './support/dropdown'

const SYSTEM_ADMIN = { id: 2, roles: ['system_admin'], name: { ar: 'منيرة بنت خالد العتيبي', en: 'Munira Al-Otaibi' } }
const SUPER_ADMIN = { id: 1, roles: [], superAdmin: true, name: { ar: 'مدير النظام الرئيسي', en: 'Super Admin' } }
const FOLLOWER = { id: 9, roles: ['request_follower'], name: 'Laura Bennett' }
const FINANCE = { id: 31, roles: ['finance_officer'], name: { ar: 'سعاد الشمري', en: 'Suad Al-Shammari' } }
const REASON = 'Formal request received from the board of the entity.'
const ERASURE_REASON = 'Approved erasure request of the entity recorded by the data protection officer.'

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffix(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function seed(page, testInfo, user = SYSTEM_ADMIN) {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

const rows = (page) => page.locator('.table:not(.table--loading) tbody .table__row')
const dialog = (page) => page.locator('.modal__panel')

function useTallViewport(page) {
  return page.setViewportSize({ width: 1280, height: 1500 })
}

function checkbox(scope, name) {
  return scope.locator(`label.checkbox:has([name="${name}"])`)
}

async function rowAction(page, rowText, actionName) {
  await rows(page).filter({ hasText: rowText }).first().locator('.dropdown-menu > button').click()
  await page.getByRole('menuitem', { name: actionName }).click()
}

test('the System Administrator lists, filters and exports entities', async ({ page }, testInfo) => {
  await seed(page, testInfo)
  await page.goto('/admin/entities')
  await expect(rows(page)).toHaveCount(10)
  await expect(page.locator('.stats-card__value').first()).toHaveText('10')
  await page.screenshot({ path: `test-results/entities-admin-list-${suffix(testInfo)}.png`, fullPage: true })

  await page.locator('.toolbar__search input').fill(isEn(testInfo) ? 'Ehsan' : 'احسان')
  await expect(rows(page)).toHaveCount(1)
  await page.locator('.toolbar__search input').fill('')
  await expect(rows(page)).toHaveCount(10)

  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: /Export CSV|تصدير CSV/ }).click()
  expect((await download).suggestedFilename()).toMatch(/^entities-\d{8}-\d{6}\.csv$/)
})

test('deactivation needs a reason and reactivation follows the separate permission', async ({ page }, testInfo) => {
  await seed(page, testInfo)
  await page.goto('/admin/entities')
  await rowAction(page, isEn(testInfo) ? 'Athar' : 'أثر', /^\s*(Deactivate|إيقاف الحساب)\s*$/)
  await dialog(page).locator('[name="reason"]').fill('short')
  await dialog(page).locator('button[type="submit"]').click()
  await expect(dialog(page).locator('.field__error')).toBeVisible()
  await dialog(page).locator('[name="reason"]').fill(REASON)
  await dialog(page).locator('button[type="submit"]').click()
  await expect(page.locator('.toast--success')).toBeVisible()
  await expect(rows(page).filter({ hasText: isEn(testInfo) ? 'Athar' : 'أثر' }).locator('.badge--danger')).toBeVisible()
})

test('deleting an entity lists its open requests, re-shows a stale list and freezes them', async ({ page }, testInfo) => {
  await seed(page, testInfo)
  await useTallViewport(page)
  await page.goto('/admin/entities/6002')
  await expect(page.locator('.entity-record__title')).toBeVisible()
  await page.getByRole('button', { name: /^\s*(Delete|حذف)\s*$/ }).click()
  await expect(dialog(page).locator('.open-requests__row')).toHaveCount(2)
  await page.screenshot({ path: `test-results/entities-delete-dialog-${suffix(testInfo)}.png`, fullPage: true })

  await dialog(page).locator('[name="reason"]').fill(REASON)
  await dialog(page).locator('button[type="submit"]').click()
  await expect(checkbox(dialog(page), 'confirmed').locator('xpath=..').locator('.field__error')).toBeVisible()
  await checkbox(dialog(page), 'confirmed').click()
  await dialog(page).locator('button[type="submit"]').click()

  await expect(dialog(page).locator('.entity-delete__stale')).toBeVisible()
  await expect(dialog(page).locator('.open-requests__row')).toHaveCount(3)
  await expect(dialog(page).locator('[name="confirmed"]')).not.toBeChecked()
  await page.screenshot({ path: `test-results/entities-delete-stale-${suffix(testInfo)}.png`, fullPage: true })
  await checkbox(dialog(page), 'confirmed').click()
  await dialog(page).locator('button[type="submit"]').click()

  await expect(page.locator('.toast--success')).toBeVisible()
  await expect(page.locator('.entity-record__notice')).toBeVisible()
  await expect(page.locator('.entity-record__heading .frozen-badge')).toBeVisible()
  await expect(page.locator('.open-requests__row .frozen-badge')).toHaveCount(3)
  await page.screenshot({ path: `test-results/entities-frozen-record-${suffix(testInfo)}.png`, fullPage: true })

  await page.locator('.entity-record__back').click()
  await expect(page).toHaveURL(/\/admin\/entities\/deleted$/)
  await expect(rows(page)).toHaveCount(3)
  await page.screenshot({ path: `test-results/entities-deleted-${suffix(testInfo)}.png`, fullPage: true })
})

test('a deleted entity is restored from the Deleted entities section', async ({ page }, testInfo) => {
  await seed(page, testInfo)
  await page.goto('/admin/entities/deleted')
  await expect(rows(page)).toHaveCount(2)
  await expect(page.locator('.entities-admin__notice')).toBeVisible()
  await rowAction(page, isEn(testInfo) ? 'Iemar' : 'إعمار', /^\s*(Restore|استعادة)\s*$/)
  await dialog(page).locator('[name="reason"]').fill(REASON)
  await dialog(page).locator('button[type="submit"]').click()
  await expect(page.locator('.toast--success')).toBeVisible()
  await expect(rows(page)).toHaveCount(1)
})

test('only the Super Admin deletes permanently, with disclaimer, typed reference and reason', async ({ page }, testInfo) => {
  await seed(page, testInfo)
  await page.goto('/admin/entities/7003')
  await expect(page.locator('.entity-record__notice')).toBeVisible()
  await expect(page.getByRole('button', { name: /Delete permanently|حذف نهائي/ })).toHaveCount(0)

  await seed(page, testInfo, SUPER_ADMIN)
  await useTallViewport(page)
  await page.reload()
  await page.getByRole('button', { name: /Delete permanently|حذف نهائي/ }).click()
  await expect(dialog(page).locator('.permanent-delete__list li')).toHaveCount(6)
  await dialog(page).locator('[name="confirmation"]').fill('ENT-000001')
  await dialog(page).locator('[name="reason"]').fill(ERASURE_REASON)
  await dialog(page).locator('button[type="submit"]').click()
  await expect(dialog(page).locator('.field--error [name="confirmation"]')).toBeVisible()
  await checkbox(dialog(page), 'disclaimerAcknowledged').click()
  await dialog(page).locator('[name="confirmation"]').fill('ent-007003')
  await page.screenshot({ path: `test-results/entities-permanent-delete-${suffix(testInfo)}.png`, fullPage: true })
  await dialog(page).locator('button[type="submit"]').click()
  await expect(page).toHaveURL(/\/admin\/entities\/deleted$/)
  await expect(rows(page)).toHaveCount(1)
})

test('actions follow the permissions: the follower views only, finance sees contact data only', async ({ page }, testInfo) => {
  await seed(page, testInfo, FOLLOWER)
  await page.goto('/admin/entities/5001')
  await expect(page.locator('.entity-record__title')).toBeVisible()
  await expect(page.locator('.entity-record__actions')).toHaveCount(0)
  await page.goto('/admin/entities')
  await expect(rows(page)).toHaveCount(10)
  await expect(page.getByRole('button', { name: /Export CSV|تصدير CSV/ })).toHaveCount(0)
  await expect(page.locator('.sidebar__item[href="/admin/entities/deleted"]')).toHaveCount(0)

  await seed(page, testInfo, FINANCE)
  await page.reload()
  await expect(rows(page)).toHaveCount(10)
  await expect(page.locator('.table__head')).toContainText(isEn(testInfo) ? 'Official e-mail' : 'البريد الرسمي')
  await expect(page.locator('.table__head')).not.toContainText(isEn(testInfo) ? 'Type' : 'النوع')
})

test('the approver applies a verified official e-mail change', async ({ page }, testInfo) => {
  await seed(page, testInfo)
  await page.goto('/admin/entities/email-changes')
  await expect(rows(page)).toHaveCount(1)
  await page.screenshot({ path: `test-results/entities-email-changes-${suffix(testInfo)}.png`, fullPage: true })
  await rows(page).first().getByRole('button', { name: /Approve|اعتماد/ }).click()
  await dialog(page).getByRole('button', { name: /Approve|اعتماد/ }).click()
  await expect(page.locator('.toast--success')).toBeVisible()
  await expect(page.locator('.empty-state')).toBeVisible()
})

test('a data-subject request is recorded and fulfilled', async ({ page }, testInfo) => {
  await seed(page, testInfo)
  await page.goto('/admin/privacy-requests')
  await expect(rows(page)).toHaveCount(1)
  await page.getByRole('button', { name: /New request|طلب جديد/ }).click()
  await dialog(page).locator('.dropdown-combobox__input').click()
  await expect(page.locator('[role="listbox"] .dropdown-option__badge').first()).toBeVisible()
  await dialog(page).locator('.dropdown-combobox__input').fill('ENT-006005')
  await page.getByRole('option', { name: /ENT-006005/ }).click()
  await chooseNamed(dialog(page), 'kind', 'access')
  await dialog(page).locator('[name="details"]').fill('Formal letter asking for a copy of the personal data held by the platform.')
  await dialog(page).locator('button[type="submit"]').click()
  await expect(page.locator('.toast--success')).toBeVisible()
  await expect(rows(page)).toHaveCount(2)
  await rowAction(page, 'Formal letter', /Mark fulfilled|تمت التلبية/)
  await dialog(page).locator('button[type="submit"]').click()
  await expect(rows(page)).toHaveCount(1)
})

test('cities are added under a region and deactivated instead of deleted', async ({ page }, testInfo) => {
  await seed(page, testInfo)
  await page.goto('/admin/settings/cities')
  await expect(rows(page).first()).toBeVisible()
  await page.getByRole('button', { name: /Add city|إضافة مدينة/ }).click()
  await chooseNamed(dialog(page), 'regionId', '7')
  await dialog(page).locator('[name="name.ar"]').fill('البدع')
  await dialog(page).locator('[name="name.en"]').fill('Al-Bad')
  await dialog(page).locator('button[type="submit"]').click()
  await expect(page.locator('.toast--success')).toBeVisible()
  await page.locator('.toolbar__search input').fill(isEn(testInfo) ? 'Al-Bad' : 'البدع')
  await expect(rows(page)).toHaveCount(1)
  await rowAction(page, 'al_bad', /^\s*(Deactivate|إيقاف)\s*$/)
  await expect(rows(page).first().locator('.badge--muted')).toBeVisible()
})

test('a forced list error shows the error state and the retry reloads the entities', async ({ page }, testInfo) => {
  await seed(page, testInfo)
  await forceMockFault(page, { method: 'GET', path: '/api/v1/entities', status: 503 })
  await page.goto('/admin/entities')
  await expect(page.locator('.table-wrap .error-state')).toBeVisible()
  await clearMockFault(page)
  await page.locator('.error-state__retry .btn').click()
  await expect(rows(page)).toHaveCount(10)
})
