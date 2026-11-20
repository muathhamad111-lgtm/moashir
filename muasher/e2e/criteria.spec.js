import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { closeSuccess, successDialog } from './support/dialogs'

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

const CRITERIA = {
  leadership: { ar: 'القيادة الإدارية', en: 'Administrative Leadership' },
  direction: { ar: 'التوجه الاستراتيجي والرؤية', en: 'Strategic Direction and Vision' },
  excellence: {
    ar: 'ثقافة التميز والقيادة بالقدوة',
    en: 'Excellence Culture and Leading by Example',
  },
  planning: { ar: 'التخطيط الاستراتيجي', en: 'Strategic Planning' },
  governance: { ar: 'تميز الحوكمة', en: 'Governance Excellence' },
  disclosure: { ar: 'الإفصاح والشفافية', en: 'Disclosure and Transparency' },
  achieved: { ar: 'متحقق / غير متحقق', en: 'Achieved / not achieved' },
  relative: { ar: 'نسبي (من 0 إلى 100%)', en: 'Relative (0 to 100%)' },
  indicatorScoring: { ar: 'نوع تقييم المؤشرات', en: 'Indicator scoring type' },
}

function nameOf(testInfo, key) {
  return CRITERIA[key][isEn(testInfo) ? 'en' : 'ar']
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function seed(page, testInfo, user = OFFICER) {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

const mains = (page) => page.locator('.criteria-strip__step')
const barTitle = (page) => page.locator('.criteria-bar__title')
const sheet = (page) => page.locator('.modal--drawer')
const sheetTitle = (page) => sheet(page).locator('.modal__title')
// Card and row buttons name their item: «Edit — Planning».
const OPEN_NODE = /^\s*(Edit|تعديل|Details|التفاصيل)(\s*—.*)?$/

async function openCriteria(page) {
  await page.goto('/admin/settings/criteria')
  await expect(page.locator('.criteria-bar')).toBeVisible()
  await expect(mains(page)).toHaveCount(9)
}

async function startDraft(page) {
  await page.getByRole('button', { name: /Start editing|بدء التعديل/ }).click()
  await expect(barTitle(page)).toContainText(/Unpublished changes|تعديلات غير منشورة/)
}

async function showMain(page, name) {
  await mains(page).filter({ hasText: name }).first().click()
  await expect(page.locator('.criteria-board__title')).toHaveText(name)
}

// Opens the editor sheet of a criterion shown on the board (main header, sub-criterion card or indicator row).
async function openNode(page, name) {
  const holder = page
    .locator('.criteria-board__head, .criteria-card__head, .criteria-point', { hasText: name })
    .first()
  await holder.getByRole('button', { name: OPEN_NODE }).first().click()
  await expect(sheetTitle(page)).toHaveText(name)
}

async function closeSheet(page) {
  await sheet(page).locator('.modal__close').click()
  await expect(sheet(page)).toHaveCount(0)
}

async function openTab(page, name) {
  await sheet(page).getByRole('tab', { name }).click()
}

async function nodeAction(page, name) {
  await sheet(page).locator('.criteria-sheet__top button[aria-haspopup="menu"]').click()
  await page.getByRole('menuitem', { name }).click()
}

function pointsOf(page, name) {
  return page.locator('.criteria-card', { hasText: name }).first().locator('.criteria-point')
}

async function useTallViewport(page) {
  await page.setViewportSize({ width: 1280, height: 1500 })
}

async function reviewToApply(page) {
  await page.getByRole('button', { name: /Review and publish|مراجعة ونشر/ }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.locator('.criteria-review__ready')).toBeVisible()
  await dialog.getByRole('button', { name: /^\s*(Next|التالي)\s*$/ }).click()
  await dialog.getByRole('button', { name: /^\s*(Next|التالي)\s*$/ }).click()
  await expect(dialog.locator('.scope-choice__option')).toHaveCount(3)
  return dialog
}

test('the published version is read-only, starts editing in one click and offers text corrections', async ({
  page,
}, testInfo) => {
  await seed(page, testInfo)
  await openCriteria(page)
  await expect(barTitle(page)).toContainText(/1/)
  await expect(page.locator('.criteria-workspace__view-note')).toBeVisible()
  await expect(
    page.getByRole('button', { name: /Add main criterion|إضافة معيار رئيسي/ }),
  ).toHaveCount(0)
  await expect(page.getByRole('button', { name: /Start editing|بدء التعديل/ })).toBeVisible()
  await openNode(page, nameOf(testInfo, 'leadership'))
  await expect(sheet(page).locator('form')).toHaveCount(0)
  await expect(sheet(page).locator('.criteria-summary')).toContainText(
    nameOf(testInfo, 'leadership'),
  )
  await expect(sheet(page).getByRole('button', { name: /Correct text|تصحيح النص/ })).toBeVisible()
  await expect(sheet(page).locator('button[aria-haspopup="menu"]')).toHaveCount(0)
})

test('editing: a blocking note links to its criterion, the fix clears it, then review and publish with a scope', async ({
  page,
}, testInfo) => {
  test.setTimeout(60000)
  await useTallViewport(page)
  await seed(page, testInfo)
  await openCriteria(page)
  await startDraft(page)
  await expect(page.locator('.criteria-bar__meta--success')).toBeVisible()

  await expect(page.locator('.criteria-board__title')).toHaveText(nameOf(testInfo, 'leadership'))
  await expect(page.locator('.criteria-split')).toBeVisible()
  await expect(
    page
      .getByRole('button', {
        name: /Split the weight equally and add|توزيع الوزن بالتساوي وإضافة/,
      })
      .first(),
  ).toBeVisible()
  await openNode(page, nameOf(testInfo, 'leadership'))
  await openTab(page, /Weight|الوزن/)
  await expect(sheet(page).locator('.weight-editor')).toBeVisible()
  await closeSheet(page)

  await openNode(page, nameOf(testInfo, 'excellence'))
  await nodeAction(page, /Deactivate|إيقاف/)
  await expect(page.locator('.criteria-bar__meta--danger')).toBeVisible()
  await closeSheet(page)
  await expect(page.locator('.criteria-strip__step.is-attention')).toHaveCount(1)
  await expect(page.locator('.criteria-board__alert')).toBeVisible()

  await page.getByRole('button', { name: /Review and publish|مراجعة ونشر/ }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.locator('.criteria-problems')).toContainText(nameOf(testInfo, 'leadership'))
  await expect(dialog.locator('.criteria-problems')).not.toContainText(/M\d/)
  await expect(dialog.getByRole('button', { name: /^\s*(Next|التالي)\s*$/ })).toHaveCount(0)
  await page.screenshot({
    path: `test-results/criteria-review-blocked-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await dialog.locator('.criteria-problems__fix').first().click()
  await expect(sheetTitle(page)).toHaveText(nameOf(testInfo, 'leadership'))
  await closeSheet(page)

  await openNode(page, nameOf(testInfo, 'excellence'))
  await nodeAction(page, /Activate|تفعيل/)
  await expect(page.locator('.criteria-bar__meta--success')).toBeVisible()
  await closeSheet(page)

  const apply = await reviewToApply(page)
  await apply.locator('.scope-choice__option').nth(2).click()
  await apply
    .locator('textarea[name="publishReason"]')
    .fill(
      isEn(testInfo) ? 'Annual review of the criteria weights.' : 'مراجعة سنوية لأوزان المعايير.',
    )
  await apply.locator('button[type="submit"]').click()
  await expect(successDialog(page).locator('.modal__title')).toContainText(/2/)
  await closeSuccess(page)
  await expect(barTitle(page)).toContainText(/Published criteria|المعايير المنشورة/)
  await expect(barTitle(page)).toContainText(/2/)
})

test('capture the criteria workspace and the review dialog', async ({ page }, testInfo) => {
  await useTallViewport(page)
  await seed(page, testInfo)
  await openCriteria(page)
  await startDraft(page)
  await page.screenshot({
    path: `test-results/criteria-workspace-draft-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await openNode(page, nameOf(testInfo, 'leadership'))
  await page.screenshot({
    path: `test-results/criteria-workspace-main-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await closeSheet(page)
  await openNode(page, nameOf(testInfo, 'direction'))
  await openTab(page, /Evaluation|التقييم/)
  await expect(sheet(page).locator('.criteria-evaluation__option.is-current')).toBeVisible()
  await page.screenshot({
    path: `test-results/criteria-workspace-sub-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await closeSheet(page)
  const apply = await reviewToApply(page)
  await expect(apply.locator('.scope-choice__closed-note')).toBeVisible()
  await apply.locator('.scope-choice__option').first().click()
  await page.screenshot({
    path: `test-results/criteria-review-apply-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
})

test('switching a sub-criterion with indicators to direct evaluation warns with the indicator count', async ({
  page,
}, testInfo) => {
  await seed(page, testInfo)
  await openCriteria(page)
  await startDraft(page)
  await openNode(page, nameOf(testInfo, 'direction'))
  await openTab(page, /Evaluation|التقييم/)
  await page
    .getByRole('button', { name: /Switch to direct evaluation|التحويل إلى التقييم المباشر/ })
    .click()
  const confirm = page.getByRole('alertdialog')
  await expect(confirm).toContainText('3')
  await page.screenshot({ path: `test-results/criteria-switch-warning-${suffixOf(testInfo)}.png` })
  await confirm.getByRole('button', { name: /Cancel|إلغاء/ }).click()
  await closeSheet(page)
  await expect(pointsOf(page, nameOf(testInfo, 'direction'))).toHaveCount(3)
})

test('a text correction on the published version changes the name in place', async ({
  page,
}, testInfo) => {
  await useTallViewport(page)
  await seed(page, testInfo)
  await openCriteria(page)
  await showMain(page, nameOf(testInfo, 'planning'))
  await openNode(page, nameOf(testInfo, 'planning'))
  await sheet(page)
    .getByRole('button', { name: /Correct text|تصحيح النص/ })
    .click()
  const correction = page.locator('.modal', {
    has: page.locator('input[name="correctionPrimaryAr"]'),
  })
  await expect(correction.locator('.criteria-form__note--emphasis')).toBeVisible()
  await correction.locator('input[name="correctionPrimaryAr"]').fill('التخطيط الاستراتيجي المؤسسي')
  await correction
    .locator('input[name="correctionPrimaryEn"]')
    .fill('Institutional Strategic Planning')
  await page.screenshot({
    path: `test-results/criteria-text-correction-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await correction.locator('button[type="submit"]').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await expect(
    mains(page).filter({
      hasText: isEn(testInfo) ? 'Institutional Strategic Planning' : 'التخطيط الاستراتيجي المؤسسي',
    }),
  ).toHaveCount(1)
  await expect(barTitle(page)).toContainText(/Published criteria|المعايير المنشورة/)
})

test('the System Administrator opens a draft and edits the criteria like the Criteria Officer', async ({
  page,
}, testInfo) => {
  await seed(page, testInfo, SYSTEM_ADMIN)
  await openCriteria(page)
  await startDraft(page)
  await expect(
    page.getByRole('button', { name: /Add main criterion|إضافة معيار رئيسي/ }),
  ).toBeEnabled()
  await expect(page.getByRole('button', { name: OPEN_NODE }).first()).toHaveText(/Edit|تعديل/)
})

test('an old link to a main criterion opens it in the workspace', async ({ page }, testInfo) => {
  await seed(page, testInfo)
  await openCriteria(page)
  await openNode(page, nameOf(testInfo, 'leadership'))
  const selected = new URL(page.url()).searchParams.get('node')
  await page.goto(`/admin/settings/criteria/${selected.replace('main-', '')}`)
  await expect(page).toHaveURL(new RegExp(`node=${selected}`))
  await expect(sheetTitle(page)).toHaveText(nameOf(testInfo, 'leadership'))
  await expect(page.locator('.criteria-board__title')).toHaveText(nameOf(testInfo, 'leadership'))
})

test('scoring types: a sub-criterion gives one type to all its indicators and an indicator changes its own', async ({
  page,
}, testInfo) => {
  await useTallViewport(page)
  await seed(page, testInfo)
  await openCriteria(page)
  await startDraft(page)
  await openNode(page, nameOf(testInfo, 'direction'))
  await openTab(page, /Evaluation|التقييم/)
  const card = sheet(page).locator('.card', { hasText: nameOf(testInfo, 'indicatorScoring') })
  await card.getByLabel(nameOf(testInfo, 'achieved')).check()
  await card.getByRole('button', { name: /Apply to all indicators|تطبيق على كل المؤشرات/ }).click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await closeSheet(page)
  const indicators = pointsOf(page, nameOf(testInfo, 'direction'))
  await expect(indicators.filter({ hasText: nameOf(testInfo, 'achieved') })).toHaveCount(3)

  await indicators.first().getByRole('button', { name: OPEN_NODE }).click()
  await openTab(page, /Evaluation|التقييم/)
  await sheet(page).locator('input[name="pointScoringType"][value="relative"]').check()
  await sheet(page).locator('.form-panel button[type="submit"]').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await closeSheet(page)
  await expect(indicators.filter({ hasText: nameOf(testInfo, 'relative') })).toHaveCount(1)
})

test('building a draft: add a main criterion and a sub-criterion, reorder, save the weights, then discard', async ({
  page,
}, testInfo) => {
  await useTallViewport(page)
  await seed(page, testInfo)
  await openCriteria(page)
  await startDraft(page)

  await page.getByRole('button', { name: /Add main criterion|إضافة معيار رئيسي/ }).click()
  const mainDialog = page.getByRole('dialog')
  await mainDialog.locator('input[name="nameAr"]').fill('تميز الحوكمة')
  await mainDialog.locator('input[name="nameEn"]').fill('Governance Excellence')
  await mainDialog.locator('input[name="weight"]').fill('500')
  await mainDialog.getByRole('button', { name: /^\s*(Add|إضافة)\s*$/ }).click()
  const governance = nameOf(testInfo, 'governance')
  await expect(mains(page)).toHaveCount(10)
  await expect(sheetTitle(page)).toHaveText(governance)
  await closeSheet(page)
  await expect(page.locator('.criteria-board__title')).toHaveText(governance)

  await page
    .getByRole('button', { name: /^\s*(Add sub-criterion|إضافة معيار فرعي)\s*$/ })
    .first()
    .click()
  const subDialog = page.getByRole('dialog')
  await subDialog.locator('input[name="subNameAr"]').fill('الإفصاح والشفافية')
  await subDialog.locator('input[name="subNameEn"]').fill('Disclosure and Transparency')
  await subDialog.locator('input[name="subWeight"]').fill('300')
  await subDialog.getByRole('button', { name: /^\s*(Add|إضافة)\s*$/ }).click()
  await expect(sheetTitle(page)).toHaveText(nameOf(testInfo, 'disclosure'))

  await sheet(page).locator('.criteria-details__path-link').first().click()
  await expect(sheetTitle(page)).toHaveText(governance)
  await openTab(page, /Weight|الوزن/)
  const save = sheet(page).getByRole('button', { name: /Save weights|حفظ الأوزان/ })
  await sheet(page).locator('.weight-editor__input').fill('600')
  await expect(sheet(page).locator('.weight-editor__status--danger')).toBeVisible()
  await expect(save).toBeDisabled()
  await sheet(page).locator('.weight-editor__input').fill('500')
  await save.click()
  await expect(sheet(page).locator('.weight-editor__status--success')).toBeVisible()
  await expect(sheet(page).locator('.weight-editor__input')).toHaveValue('500.00')
  await closeSheet(page)

  await page
    .getByRole('button', { name: new RegExp(`(Move|نقل) ${governance} (up|إلى الأعلى)`) })
    .click()
  await expect(mains(page).nth(8)).toContainText(governance)

  await page.getByRole('button', { name: /Discard changes|إلغاء التعديلات/ }).click()
  await page
    .getByRole('alertdialog')
    .getByRole('button', { name: /^\s*(Delete|حذف)\s*$/ })
    .click()
  await expect(barTitle(page)).toContainText(/Published criteria|المعايير المنشورة/)
  await expect(mains(page)).toHaveCount(9)
})

test('required attachments: a main criterion keeps its attachment with guidance, file types and a file limit', async ({
  page,
}, testInfo) => {
  await useTallViewport(page)
  await seed(page, testInfo)
  await openCriteria(page)
  await startDraft(page)
  await openNode(page, nameOf(testInfo, 'leadership'))
  await openTab(page, /Attachments|المرفقات/)

  // The seed may already give this criterion attachments; the new one is added after them.
  const editor = sheet(page).locator('.form-panel .attachments-editor')
  const rows = editor.locator('.attachments-editor__row')
  const at = await rows.count()
  await editor.getByRole('button', { name: /Add attachment|إضافة مرفق/ }).click()
  const row = rows.nth(at)
  await row.locator('input[dir="rtl"]').fill('خطة التشغيل الموقعة')
  await row.locator('input[dir="ltr"]').first().fill('Signed operating plan')
  await row.locator('summary').click()
  await row.locator(`textarea[name="attachmentGuidanceEn${at}"]`).fill('Upload the signed plan')
  await row.locator('input[value="pdf"]').check()
  await row.locator(`input[name="attachmentMaxFiles${at}"]`).fill('2')
  await sheet(page).locator('.form-panel button[type="submit"]').click()
  const guidanceAr = row.locator(`textarea[name="attachmentGuidanceAr${at}"]`)
  await expect(guidanceAr).toHaveAttribute('aria-invalid', 'true')
  await guidanceAr.fill('ارفع الخطة الموقعة')
  const saving = page.waitForResponse(
    (response) =>
      response.request().method() === 'PUT' && /\/criteria\/mains\//.test(response.url()),
  )
  await sheet(page).locator('.form-panel button[type="submit"]').click()
  const response = await saving
  expect(response.status()).toBe(200)
  expect((await response.json()).data.attachments[at]).toMatchObject({
    allowed_types: ['pdf'],
    max_files: 2,
  })
  await closeSheet(page)
  await expect(
    page.locator('.criteria-board .criteria-chips__chip', {
      hasText: isEn(testInfo) ? 'Signed operating plan' : 'خطة التشغيل الموقعة',
    }),
  ).toHaveCount(1)

  await openNode(page, nameOf(testInfo, 'leadership'))
  await openTab(page, /Attachments|المرفقات/)
  const saved = sheet(page).locator('.form-panel .attachments-editor__row').nth(at)
  await expect(saved.locator('input[dir="ltr"]').first()).toHaveValue('Signed operating plan')
  await saved.locator('summary').click()
  await expect(saved.locator(`textarea[name="attachmentGuidanceEn${at}"]`)).toHaveValue(
    'Upload the signed plan',
  )
  await expect(saved.locator('input[value="pdf"]')).toBeChecked()
  await expect(saved.locator(`input[name="attachmentMaxFiles${at}"]`)).toHaveValue('2')
})

test('unsaved edits: closing or switching asks first, staying keeps them, discarding drops them', async ({
  page,
}, testInfo) => {
  await useTallViewport(page)
  await seed(page, testInfo)
  await openCriteria(page)
  await startDraft(page)
  await openNode(page, nameOf(testInfo, 'direction'))
  const nameEn = sheet(page).locator('input[name="subNameEn"]')
  await nameEn.fill('Strategic Direction, Vision and Values')

  const confirm = page.getByRole('alertdialog')
  const discard = /Discard the changes\?|تجاهل التعديلات؟/
  await page.keyboard.press('Escape')
  await expect(confirm).toContainText(discard)
  await page.screenshot({ path: `test-results/criteria-unsaved-${suffixOf(testInfo)}.png` })
  await confirm.getByRole('button', { name: /Cancel|إلغاء/ }).click()
  await expect(sheetTitle(page)).toHaveText(nameOf(testInfo, 'direction'))
  await expect(nameEn).toHaveValue('Strategic Direction, Vision and Values')

  await sheet(page).locator('.criteria-details__path-link').first().click()
  await expect(confirm).toContainText(discard)
  await confirm.getByRole('button', { name: /Cancel|إلغاء/ }).click()
  await expect(sheetTitle(page)).toHaveText(nameOf(testInfo, 'direction'))

  await sheet(page).locator('.modal__close').click()
  await confirm.getByRole('button', { name: /Discard changes|تجاهل التعديلات/ }).click()
  await expect(sheet(page)).toHaveCount(0)
  await openNode(page, nameOf(testInfo, 'direction'))
  await expect(sheet(page).locator('input[name="subNameEn"]')).toHaveValue(
    'Strategic Direction and Vision',
  )
  await closeSheet(page)
})
