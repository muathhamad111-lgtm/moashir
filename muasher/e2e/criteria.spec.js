import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

const OFFICER = {
  id: 7101,
  roles: ['criteria_officer'],
  name: { ar: 'خالد العمري', en: 'Khalid Al-Omari' },
}
const SYSTEM_ADMIN = {
  id: 7102,
  roles: ['system_admin'],
  name: { ar: 'نورة الدوسري', en: 'Noura Al-Dosari' },
}

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function seed(page, testInfo, user = OFFICER) {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

const rows = (page) => page.locator('.table:not(.table--loading) tbody .table__row')

async function openCriteria(page) {
  await page.goto('/admin/settings/criteria')
  await expect(page.locator('.criteria-version')).toBeVisible()
  await expect(rows(page)).toHaveCount(9)
}

async function rowAction(page, rowText, actionName) {
  const row = rows(page).filter({ hasText: rowText }).first()
  await row.locator('.dropdown-menu > button').click()
  await page.getByRole('menuitem', { name: actionName }).click()
}

async function startDraft(page) {
  await page.getByRole('button', { name: /create a draft copy|إنشاء نسخة مسودة/ }).click()
  await expect(page.locator('.criteria-version--info')).toBeVisible()
  await expect(rows(page)).toHaveCount(9)
}

async function openMain(page, code) {
  await rows(page).filter({ hasText: code }).first().locator('.criteria-page__link').click()
  await expect(page.locator('.criteria-page__breadcrumb')).toBeVisible()
  await expect(page.locator('.table__row').first()).toBeVisible()
}

async function useTallViewport(page) {
  await page.setViewportSize({ width: 1280, height: 1500 })
}

async function backToVersion(page) {
  await page.locator('.criteria-page__crumb').first().click()
  await expect(rows(page)).toHaveCount(9)
}

test('the published version is read-only and offers a draft copy and text corrections', async ({
  page,
}, testInfo) => {
  await seed(page, testInfo)
  await openCriteria(page)
  await expect(page.locator('.criteria-version--success')).toContainText(/1/)
  await expect(
    page.getByRole('button', { name: /Add main criterion|إضافة معيار رئيسي/ }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('button', { name: /create a draft copy|إنشاء نسخة مسودة/ }),
  ).toBeVisible()
  await rows(page).first().locator('.dropdown-menu > button').click()
  await expect(page.getByRole('menuitem', { name: /Correct text|تصحيح النص/ })).toBeVisible()
  await expect(page.getByRole('menuitem', { name: /^\s*(Edit|تعديل)\s*$/ })).toHaveCount(0)
})

test('editing published criteria: clone, blocked publish with links to fix, then publish with a scope', async ({
  page,
}, testInfo) => {
  test.setTimeout(60000)
  await useTallViewport(page)
  await seed(page, testInfo)
  await openCriteria(page)
  await startDraft(page)

  await openMain(page, 'M1')
  await expect(page.locator('.weight-editor')).toBeVisible()
  await expect(
    page.getByRole('button', { name: /Add sub-criterion|إضافة معيار فرعي/ }),
  ).toBeDisabled()
  await expect(page.locator('.criteria-page__hint')).toBeVisible()
  await rowAction(page, 'M1.3', /Deactivate|إيقاف/)
  await expect(page.locator('.weight-editor__status--warning')).toBeVisible()
  await expect(
    page.getByRole('button', { name: /Add sub-criterion|إضافة معيار فرعي/ }),
  ).toBeEnabled()

  await backToVersion(page)
  await page.getByRole('button', { name: /Check consistency|التحقق من الاتساق/ }).click()
  await expect(page.locator('.criteria-version-panel .criteria-problems')).toContainText('M1')

  await page.getByRole('button', { name: /^\s*(Publish|نشر)\s*$/ }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.locator('.scope-choice__option')).toHaveCount(3)
  await dialog.locator('.scope-choice__option').first().click()
  await dialog.locator('button[type="submit"]').click()
  await expect(dialog.locator('.criteria-problems')).toContainText('M1')
  await page.screenshot({
    path: `test-results/criteria-publish-blocked-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await dialog.locator('.criteria-problems__fix').first().click()
  await expect(page.locator('.weight-editor')).toBeVisible()
  await rowAction(page, 'M1.3', /Activate|تفعيل/)
  await expect(page.locator('.weight-editor__status--success')).toBeVisible()

  await backToVersion(page)
  await page.getByRole('button', { name: /^\s*(Publish|نشر)\s*$/ }).click()
  await dialog.locator('.scope-choice__option').nth(2).click()
  await dialog
    .locator('textarea[name="publishReason"]')
    .fill(
      isEn(testInfo) ? 'Annual review of the criteria weights.' : 'مراجعة سنوية لأوزان المعايير.',
    )
  await dialog.locator('button[type="submit"]').click()
  await expect(page.locator('.toast--success').last()).toContainText(/2/)
  await expect(page.locator('.criteria-version--success')).toContainText(/2/)
})

test('capture the criteria editor and the publish dialog with the three scopes', async ({
  page,
}, testInfo) => {
  await useTallViewport(page)
  await seed(page, testInfo)
  await openCriteria(page)
  await startDraft(page)
  await page.screenshot({
    path: `test-results/criteria-index-draft-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await page.getByRole('button', { name: /^\s*(Publish|نشر)\s*$/ }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.locator('.scope-choice__description')).toHaveCount(3)
  await expect(dialog.locator('.scope-choice__closed-note')).toBeVisible()
  await dialog.locator('.scope-choice__option').first().click()
  await page.screenshot({
    path: `test-results/criteria-publish-dialog-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await dialog.getByRole('button', { name: /Cancel|إلغاء/ }).click()

  await openMain(page, 'M1')
  await page.screenshot({
    path: `test-results/criteria-editor-main-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await page.locator('.table__row .criteria-page__link').first().click()
  await expect(page.locator('.weight-budget')).toBeVisible()
  await page.screenshot({
    path: `test-results/criteria-editor-sub-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
})

test('switching a guiding-points sub-criterion to direct evaluation warns with the point count', async ({
  page,
}, testInfo) => {
  await seed(page, testInfo)
  await openCriteria(page)
  await startDraft(page)
  await openMain(page, 'M1')
  await rowAction(page, 'M1.1', /Switch to direct evaluation|التحويل إلى التقييم المباشر/)
  const confirm = page.getByRole('dialog')
  await expect(confirm).toContainText('3')
  await page.screenshot({ path: `test-results/criteria-switch-warning-${suffixOf(testInfo)}.png` })
  await confirm.getByRole('button', { name: /Cancel|إلغاء/ }).click()
  await expect(rows(page).filter({ hasText: 'M1.1' })).toContainText(/3/)
})

test('a text correction on the published version changes the name in place', async ({
  page,
}, testInfo) => {
  await useTallViewport(page)
  await seed(page, testInfo)
  await openCriteria(page)
  await rowAction(page, 'M2', /Correct text|تصحيح النص/)
  const dialog = page.getByRole('dialog')
  await expect(dialog.locator('.criteria-form__note--emphasis')).toBeVisible()
  await dialog.locator('input[name="correctionPrimaryAr"]').fill('التخطيط الاستراتيجي المؤسسي')
  await dialog.locator('input[name="correctionPrimaryEn"]').fill('Institutional Strategic Planning')
  await page.screenshot({
    path: `test-results/criteria-text-correction-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await dialog.locator('button[type="submit"]').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await expect(rows(page).filter({ hasText: 'M2' })).toContainText(
    isEn(testInfo) ? 'Institutional Strategic Planning' : 'التخطيط الاستراتيجي المؤسسي',
  )
  await expect(page.locator('.criteria-version--success')).toBeVisible()
})

test('the System Administrator reads the criteria without any tree or text editing', async ({
  page,
}, testInfo) => {
  await seed(page, testInfo, SYSTEM_ADMIN)
  await openCriteria(page)
  await expect(
    page.getByRole('button', { name: /create a draft copy|إنشاء نسخة مسودة/ }),
  ).toHaveCount(0)
  await rows(page).first().locator('.dropdown-menu > button').click()
  await expect(page.getByRole('menuitem')).toHaveCount(1)
})
