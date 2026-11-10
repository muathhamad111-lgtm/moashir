import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { successDialog } from './support/dialogs'

const PDF = Buffer.from('%PDF-1.4\n% Accredited evidence\n%%EOF\n')
const UNDER_REVIEW = '3f0c9a52-6d1e-4b7a-9c21-5a1e00000022'

const USERS = {
  admin: { id: 2, roles: ['system_admin'] },
  finance: { id: 22, roles: ['finance_officer'] },
  entity: { id: 6001, roles: ['entity'], entityId: 6001 },
}

const COPY = {
  ar: {
    submitTitle: 'إرسال الطلب للمراجعة؟',
    send: 'إرسال',
    cancel: 'إلغاء',
    submitted: 'أُرسل طلبك للمراجعة',
    ok: 'حسناً',
    approveTitle: 'اعتماد التحويل؟',
    approve: 'اعتماد',
    approved: 'اعتُمد التحويل',
    deleteTitle: 'حذف الحساب البنكي؟',
    delete: 'حذف',
    deleteMenu: 'حذف الحساب',
    clean: 'سليم',
  },
  en: {
    submitTitle: 'Send the request for review?',
    send: 'Send',
    cancel: 'Cancel',
    submitted: 'Your request was sent for review',
    ok: 'OK',
    approveTitle: 'Approve the transfer?',
    approve: 'Approve',
    approved: 'The transfer was approved',
    deleteTitle: 'Delete the bank account?',
    delete: 'Delete',
    deleteMenu: 'Delete account',
    clean: 'Clean',
  },
}

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function signIn(page, testInfo, user) {
  return seedSignedIn(page, user, { locale: localeOf(testInfo) })
}

const alertDialog = (page) => page.getByRole('alertdialog')

function pdf(name) {
  return { name, mimeType: 'application/pdf', buffer: PDF }
}

async function expectConcise(dialog, { title, line, buttons }) {
  await expect(dialog).toBeVisible()
  await expect(dialog.locator('.modal__title')).toHaveText(title)
  await expect(dialog.locator('.modal__message')).toHaveText(line)
  await expect(dialog.locator('.modal__body li, .modal__body ul')).toHaveCount(0)
  await expect(dialog.locator('.modal__footer .btn')).toHaveText(buttons)
  await expect(dialog.locator('.modal__icon')).toHaveAttribute('aria-hidden', 'true')
}

async function completeAccreditedForm(page, copy) {
  await page.goto('/assessment/accredited/form/9101')
  await page.locator('.station-row__button').nth(3).click()
  await page
    .locator('#accredited-sub-402 .evidence-file--refused .evidence-upload__input')
    .setInputFiles(pdf('audited-statements-2025-final.pdf'))
  await page.locator('.station-row__button').nth(7).click()
  const impact = page.locator('#accredited-sub-802')
  await impact
    .locator('textarea')
    .fill('تقيس الجهة أثر برامجها سنويًا وفق منهجية العائد الاجتماعي على الاستثمار.')
  await impact
    .locator('.evidence-slots__slot')
    .first()
    .locator('.evidence-upload__input')
    .setInputFiles(pdf('impact-report-2026.pdf'))
  await expect(impact.locator('.scan-status').first()).toContainText(copy.clean, {
    timeout: 20000,
  })
  await page.locator('.accredited-actions .btn--secondary').click()
  await expect(page.locator('.accredited-completeness')).toHaveClass(
    /accredited-completeness--done/,
  )
}

test('submitting a request asks one short question, then confirms the success', async ({
  page,
}, testInfo) => {
  test.setTimeout(90000)
  const copy = COPY[localeOf(testInfo)]
  await signIn(page, testInfo, USERS.entity)
  await completeAccreditedForm(page, copy)
  await expect(page.locator('.accredited-actions__hint')).toBeVisible()

  await page.locator('.accredited-actions .btn--primary').click()
  const dialog = alertDialog(page)
  await expectConcise(dialog, {
    title: copy.submitTitle,
    line: 'ACC-2026-000004',
    buttons: [copy.send, copy.cancel],
  })
  await expect(dialog.locator('.modal__footer .btn').first()).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)

  await page.locator('.accredited-actions .btn--primary').click()
  await alertDialog(page).locator('.modal__footer .btn--primary').click()
  await expect(page).toHaveURL(/\/assessment\/accredited$/)
  const done = successDialog(page)
  await expect(done.locator('.modal__title')).toHaveText(copy.submitted)
  await expect(done.locator('.modal__message')).toHaveText('ACC-2026-000004')
  await expect(done.locator('.modal__footer .btn')).toHaveText([copy.ok])
  await done.locator('.modal__footer .btn').click()
  await expect(successDialog(page)).toHaveCount(0)
})

test('approving a transfer asks one short question with the reference and confirms the success', async ({
  page,
}, testInfo) => {
  const copy = COPY[localeOf(testInfo)]
  await signIn(page, testInfo, USERS.finance)
  await page.goto(`/finance/transfers/${UNDER_REVIEW}`)
  await expect(page.locator('.transfer-review__decision-hint')).toHaveCount(2)
  await page.locator('.transfer-review__decisions .btn--primary').click()
  const dialog = alertDialog(page)
  await expectConcise(dialog, {
    title: copy.approveTitle,
    line: 'PAY-2026-000022',
    buttons: [copy.approve, copy.cancel],
  })
  await dialog.locator('.modal__footer .btn--primary').click()
  const done = successDialog(page)
  await expect(done.locator('.modal__title')).toHaveText(copy.approved)
  await expect(done.locator('.modal__message')).toHaveText('PAY-2026-000022')
  await page.keyboard.press('Escape')
  await expect(successDialog(page)).toHaveCount(0)
  await expect(page.locator('.transfer-decision-line')).toBeVisible()
  await expect(page.locator('.transfer-review__decisions')).toHaveCount(0)
})

test('deleting a bank account is a danger question with the safe action focused', async ({
  page,
}, testInfo) => {
  const copy = COPY[localeOf(testInfo)]
  await signIn(page, testInfo, USERS.admin)
  await page.goto('/admin/settings/payment')
  await page.locator('.bank-accounts__table .dropdown-menu > button').first().click()
  await page.getByRole('menuitem', { name: copy.deleteMenu }).click()
  const dialog = alertDialog(page)
  await expect(dialog.locator('.modal__title')).toHaveText(copy.deleteTitle)
  await expect(dialog.locator('.modal__message')).toContainText('7519')
  await expect(dialog.locator('.modal__footer .btn')).toHaveText([copy.delete, copy.cancel])
  await expect(dialog.locator('.modal__footer .btn').first()).toHaveClass(/btn--danger/)
  await expect(dialog.locator('.modal__icon--danger')).toBeVisible()
  await expect(dialog.locator('.modal__footer .btn--secondary')).toBeFocused()
  await dialog.locator('.modal__footer .btn--secondary').click()
  await expect(alertDialog(page)).toHaveCount(0)
  await expect(page.locator('.bank-accounts__table tbody tr')).toHaveCount(1)
})
