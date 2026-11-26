import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

const SHOTS = '../agents/tasks/reports/registration-fields'
const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }

const SYSTEM_ADMIN = {
  id: 2,
  roles: ['system_admin'],
  name: { ar: 'منيرة بنت خالد العتيبي', en: 'Munira Al-Otaibi' },
}
const EHSAN = {
  id: 6002,
  roles: ['entity'],
  entityId: 6002,
  name: { ar: 'عبدالرحمن بن خالد الشهري', en: 'Abdulrahman Al-Shehri' },
}
const ALBIRR = {
  id: 5001,
  roles: ['entity'],
  entityId: 5001,
  name: { ar: 'أحمد بن سالم القحطاني', en: 'Ahmed Salem Al-Qahtani' },
}

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function text(testInfo, en, ar) {
  return localeOf(testInfo) === 'en' ? en : ar
}

function shotName(testInfo, name) {
  return `${SHOTS}/${name}-${localeOf(testInfo) === 'en' ? 'en-ltr' : 'ar-rtl'}.png`
}

test('staff open the entity documents card and preview the licence certificate inline', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, SYSTEM_ADMIN, { locale: localeOf(testInfo) })
  await page.setViewportSize(DESKTOP)
  await page.goto('/admin/entities/5001')
  const card = page.locator('.entity-documents-card')
  await expect(card).toContainText(text(testInfo, 'Entity documents', 'وثائق الجهة'))
  await expect(card.locator('[data-document="vatCertificate"]')).toContainText(
    text(testInfo, 'Not attached', 'غير مرفقة'),
  )
  await card
    .locator('[data-document="licenseCertificate"]')
    .getByRole('button', { name: text(testInfo, 'Preview', 'معاينة') })
    .click()
  const viewer = page.locator('.modal__panel .document-viewer')
  await expect(viewer).toBeVisible()
  await expect(viewer.locator('iframe.document-viewer__pdf')).toHaveAttribute('src', /^blob:/)
  await page.screenshot({ path: shotName(testInfo, 'staff-document-preview') })
  await page.keyboard.press('Escape')
  await expect(viewer).toHaveCount(0)

  await page.setViewportSize(MOBILE)
  await card.scrollIntoViewIfNeeded()
  await card.screenshot({ path: shotName(testInfo, 'staff-documents-card-390') })
})

test('an existing entity without documents sees «وثائق ناقصة» and the upload buttons', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, EHSAN, { locale: localeOf(testInfo) })
  await page.setViewportSize(DESKTOP)
  await page.goto('/entity/profile')
  const notice = page.getByTestId('documents-notice')
  await expect(notice).toContainText(text(testInfo, 'Missing documents', 'وثائق ناقصة'))
  await expect(notice).toContainText(text(testInfo, 'Licence certificate', 'شهادة الترخيص'))
  const documents = page.getByTestId('own-entity-documents')
  await expect(
    documents.getByRole('button', { name: text(testInfo, 'Upload document', 'رفع الوثيقة') }),
  ).toHaveCount(2)
  await page.screenshot({ path: shotName(testInfo, 'entity-missing-documents'), fullPage: true })
})

test('an entity with both documents sees no notice', async ({ page }, testInfo) => {
  await seedSignedIn(page, ALBIRR, { locale: localeOf(testInfo) })
  await page.goto('/entity/profile')
  await expect(page.getByTestId('own-entity-documents')).toBeVisible()
  await expect(page.getByTestId('documents-notice')).toHaveCount(0)
})

test('staff who change the authorized person attach a new authorization letter in the same save', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, SYSTEM_ADMIN, { locale: localeOf(testInfo) })
  await page.setViewportSize(DESKTOP)
  await page.goto('/admin/entities/5001')
  await page
    .getByRole('button', { name: text(testInfo, 'Edit on behalf', 'تعديل نيابة عن الجهة') })
    .click()
  const dialog = page.locator('.modal__panel')
  const letterField = dialog.getByTestId('staff-authorization-letter')
  await expect(letterField).toHaveCount(0)

  await dialog.locator('[name="authorizedPersonName"]').fill('ريم بنت خالد الحربي')
  await expect(letterField).toBeVisible()
  await dialog.locator('[name="reason"]').fill('خطاب مجلس الإدارة بتعيين مفوّض جديد للجهة.')
  const save = dialog.getByRole('button', { name: text(testInfo, 'Save changes', 'حفظ التغييرات') })
  await save.click()
  await expect(letterField).toContainText(
    text(testInfo, 'Upload the document as a PDF.', 'ارفع الوثيقة بصيغة PDF.'),
  )

  await letterField.locator('input[type="file"]').setInputFiles({
    name: 'letter-reem.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from('%PDF-1.4\n%%EOF\n'),
  })
  await expect(letterField).toContainText('letter-reem.pdf')
  await page.setViewportSize(MOBILE)
  await letterField.scrollIntoViewIfNeeded()
  await page.screenshot({ path: shotName(testInfo, 'staff-edit-letter-390') })
  await save.click()
  await expect(dialog).toHaveCount(0)
})
