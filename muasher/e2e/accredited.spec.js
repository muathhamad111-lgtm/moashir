import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'
import { guardMoyasar } from './support/moyasarGuard'

const IGNORED_CONSOLE = /Failed to load resource|40\d|50\d/i
const PDF = Buffer.from('%PDF-1.4\n% Accredited evidence\n%%EOF\n')
const EICAR = Buffer.from(
  '%PDF-1.4\nX5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*\n%%EOF\n',
)

const ENTITIES = {
  albirr: {
    id: 5001,
    roles: ['entity'],
    entityId: 5001,
    name: { ar: 'أحمد بن سالم القحطاني', en: 'Ahmed Salem Al-Qahtani' },
  },
  namaa: {
    id: 6001,
    roles: ['entity'],
    entityId: 6001,
    name: { ar: 'سارة بنت علي الحربي', en: 'Sara Ali Al-Harbi' },
  },
  ataa: {
    id: 6004,
    roles: ['entity'],
    entityId: 6004,
    name: { ar: 'عمر بن خالد الشهري', en: 'Omar Khalid Al-Shehri' },
  },
}

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function signIn(page, testInfo, entity) {
  return seedSignedIn(page, entity, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

function trackConsole(page) {
  const errors = []
  page.on('console', (message) => {
    if (['error', 'warning'].includes(message.type()) && !IGNORED_CONSOLE.test(message.text()))
      errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))
  return errors
}

async function openMain(page, index) {
  await page.locator('.station-row__button').nth(index).click()
}

function pdf(name, content = PDF) {
  return { name, mimeType: 'application/pdf', buffer: content }
}

test('a guest sees the public price before VAT, the VAT and the total with a sign-in action', async ({
  page,
}, testInfo) => {
  await seedPreferences(page, { locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/assessment/accredited')
  await expect(page.locator('.accredited-price .pricing-breakdown__row')).toHaveCount(3)
  await expect(page.locator('.accredited-price .pricing-breakdown__row--total')).toContainText(
    '1,150.00',
  )
  await expect(page.locator('.accredited-price__action .btn--primary')).toBeVisible()
  await expect(page.locator('.accredited-current')).toHaveCount(0)
})

test('the entity with a draft request is sent to its form, and cannot pay twice', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await signIn(page, testInfo, ENTITIES.namaa)
  await page.goto('/assessment/accredited')
  await expect(page).toHaveURL(/\/assessment\/accredited\/form\/9101$/)
  await expect(page.locator('.request-card')).toContainText('ACC-2026-000004')

  await page.goto('/assessment/accredited/pay')
  await page.locator('.checkout__start .btn--primary').click()
  await expect(page.locator('.payment-notice--danger')).toBeVisible()
  await expect(page.locator('[data-fake-moyasar="form"]')).toHaveCount(0)
  await page.locator('.payment-notice--danger .payment-notice__link').click()
  await expect(page).toHaveURL(/\/assessment\/accredited\/form\/9101$/)
  expect(errors).toEqual([])
})

test('the entity completes the form: CV, evidence with scan states, refused file replaced, then submits', async ({
  page,
}, testInfo) => {
  test.setTimeout(60000)
  const errors = trackConsole(page)
  await signIn(page, testInfo, ENTITIES.namaa)
  await page.goto('/assessment/accredited/form/9101')
  const completeness = page.locator('.accredited-completeness')
  await expect(completeness.locator('.accredited-completeness__item')).toHaveCount(3)
  await page.screenshot({
    path: `test-results/accredited-form-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await openMain(page, 3)
  const governance = page.locator('#accredited-sub-402')
  await expect(governance.locator('.evidence-file--refused')).toBeVisible()
  await governance
    .locator('.evidence-file--refused .evidence-upload__input')
    .setInputFiles(pdf('audited-statements-2025-final.pdf'))
  await expect(governance.locator('.evidence-file--refused')).toHaveCount(0)
  await expect(completeness.locator('.accredited-completeness__item')).toHaveCount(2)

  await openMain(page, 7)
  const impact = page.locator('#accredited-sub-802')
  await impact
    .locator('textarea')
    .fill('تقيس الجهة أثر برامجها سنويًا وفق منهجية العائد الاجتماعي على الاستثمار.')
  await impact
    .locator('.evidence-slots__slot')
    .first()
    .locator('.evidence-upload__input')
    .setInputFiles(pdf('impact-report-2026.pdf'))
  await expect(impact.locator('.scan-status').first()).toContainText(
    isEn(testInfo) ? 'Being scanned' : 'قيد الفحص',
  )
  await expect(impact.locator('.scan-status').first()).toContainText(
    isEn(testInfo) ? 'Clean' : 'سليم',
    { timeout: 15000 },
  )
  await impact
    .locator('.evidence-slots__slot')
    .last()
    .locator('.evidence-upload__input')
    .setInputFiles(pdf('impact-raw-data.pdf', EICAR))
  await expect(impact.locator('.evidence-file--refused')).toBeVisible({ timeout: 15000 })
  await page.screenshot({
    path: `test-results/accredited-evidence-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await impact.locator('.evidence-file--refused .btn--ghost').last().click()
  await page.locator('.modal .btn--danger').click()
  await expect(impact.locator('.evidence-file--refused')).toHaveCount(0)

  await page.locator('.accredited-actions .btn--secondary').click()
  await expect(completeness).toHaveClass(/accredited-completeness--done/)
  await page.locator('.accredited-actions .btn--primary').click()
  await page.locator('.modal .btn--primary').click()
  await expect(page).toHaveURL(/\/paths\/accredited\/9101\?tab=request$/)
  await expect(page.locator('.request-page__badges')).toContainText(
    isEn(testInfo) ? 'Submitted' : 'مُرسل',
  )
  expect(errors).toEqual([])
})

test('a completion request limits edits to the listed items and shows the deadline and the expiry outcome', async ({
  page,
}, testInfo) => {
  await signIn(page, testInfo, ENTITIES.ataa)
  await page.goto('/assessment/accredited/form/9106')
  const notice = page.locator('.accredited-notices .completion-card')
  await expect(notice).toContainText('M2.2')
  await expect(notice).toContainText(
    isEn(testInfo)
      ? 'The review continues with the data available'
      : 'تستمر المراجعة بالبيانات المتاحة',
  )
  await expect(notice.locator('.date-stamp__hijri')).toBeVisible()
  await page.screenshot({
    path: `test-results/accredited-completion-entity-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await expect(page.locator('#accredited-sub-101 textarea')).toBeDisabled()
  await openMain(page, 1)
  await page
    .locator('#accredited-sub-202 textarea')
    .fill('نتابع مؤشرات الخطة الاستراتيجية ربع سنويًا، وتحقق 82 في المئة من مستهدفات العام الماضي.')
  await openMain(page, 3)
  await page
    .locator('#accredited-sub-402 .evidence-slots__slot')
    .first()
    .locator('.evidence-upload__input')
    .setInputFiles(pdf('audited-statements-2025.pdf'))
  await expect(page.locator('#accredited-sub-402 .scan-status').first()).toBeVisible()
  await page.locator('.accredited-actions .btn--primary').click()
  await page.locator('.modal .btn--primary').click()
  await expect(page).toHaveURL(/\/paths\/accredited\/9106\?tab=request$/)
  await expect(page.locator('.request-page__badges')).toContainText(
    isEn(testInfo) ? 'Under review' : 'قيد المراجعة',
  )
})

test('the result shows totals and feedback per sub-criterion without the assessor identity', async ({
  page,
}, testInfo) => {
  await signIn(page, testInfo, ENTITIES.albirr)
  await page.goto('/assessment/accredited/result/9102')
  await expect(page).toHaveURL(/\/paths\/accredited\/9102\?tab=result$/)
  await expect(page.locator('.accredited-result-summary__value')).toContainText('%')
  await page.goto('/assessment/accredited/result/9102/feedback')
  await expect(page).toHaveURL(/\/paths\/accredited\/9102\?tab=feedback$/)
  await expect(page.locator('.accredited-feedback__item').first()).toBeVisible()
  await expect(page.locator('.accredited-feedback__field--strengths').first()).toBeVisible()
  await expect(page.locator('main')).not.toContainText(/سلطان|Sultan/)
  await page.screenshot({
    path: `test-results/accredited-result-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await signIn(page, testInfo, ENTITIES.namaa)
  await page.goto('/assessment/accredited/result/9101')
  await expect(page.locator('[data-testid="request-tab-request"]')).toBeVisible()
  await expect(page.locator('[role="tab"]')).toHaveCount(1)
})

test('a paid accredited order opens a draft request for the entity', async ({ page }) => {
  const leaks = await guardMoyasar(page)
  await seedSignedIn(page, ENTITIES.albirr, { locale: 'en' })
  await page.goto('/assessment/accredited/pay')
  await page.locator('.checkout__start .btn--primary').click()
  await page.locator('#fake-moyasar-sandbox-outcome').selectOption('paid')
  await page.locator('.fake-moyasar__pay').click()
  await expect(page.locator('.payment-outcome--paid')).toBeVisible()
  await page.goto('/assessment/accredited')
  await expect(page).toHaveURL(/\/assessment\/accredited\/form\/\d+$/)
  await expect(page.locator('.request-card')).toContainText(/ACC-2026-0000\d\d/)
  await expect(page.locator('.request-card')).toContainText('Draft')
  expect(leaks).toEqual([])
})

test('feedback report and certificates remain reachable for the entity', async ({
  page,
}, testInfo) => {
  await signIn(page, testInfo, ENTITIES.albirr)
  await page.goto('/reports/feedback/9102')
  await expect(page.locator('.feedback-report__sub').first()).toBeVisible()
  await page.goto('/certificates')
  await expect(page.locator('.certificate-card').first()).toBeVisible()
})
