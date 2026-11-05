import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

const IGNORED_CONSOLE = /Failed to load resource|40\d|50\d/i

const STAFF = {
  follower: { id: 8, roles: ['request_follower', 'filtering_officer'] },
  assessor: { id: 10, roles: ['assessor'] },
  systemAdmin: { id: 1, roles: ['system_admin'] },
  superAdmin: { id: 900, roles: [], superAdmin: true, name: { ar: 'مدير النظام الرئيسي', en: 'Super Admin' } },
}

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function signIn(page, testInfo, user) {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

function trackConsole(page) {
  const errors = []
  page.on('console', (message) => {
    if (['error', 'warning'].includes(message.type()) && !IGNORED_CONSOLE.test(message.text())) errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))
  return errors
}

async function fillEmptyScores(page, value) {
  const mains = page.locator('.criteria-tree__open')
  const count = await mains.count()
  for (let index = 0; index < count; index += 1) {
    await mains.nth(index).click()
    const fields = page.locator('.review-main .raw-score input')
    const total = await fields.count()
    for (let field = 0; field < total; field += 1) {
      if (!(await fields.nth(field).inputValue())) await fields.nth(field).fill(value)
    }
  }
}

const REASON = 'إعادة توزيع الطلبات بين المقيّمين لتحقيق التوازن في الأحمال.'
const OVERRIDE_REASON = 'تصحيح الدرجة بعد مراجعة اللجنة الفنية للشواهد المرفقة مع الطلب.'

test.describe('Requests follower', () => {
  test('the queue shows stats, overdue with icon and text, and filters by status', async ({ page }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, STAFF.follower)
    await page.goto('/admin/requests')
    const rows = page.locator('.table:not(.table--loading) tbody .table__row')
    await expect(rows.first()).toBeVisible()
    await expect(page.locator('.stats-card')).toHaveCount(4)
    const overdue = page.locator('.overdue-badge.badge--danger').first()
    await expect(overdue).toBeVisible()
    await expect(overdue.locator('svg.app-icon')).toHaveCount(1)
    await page.screenshot({ path: `test-results/requests-queue-${suffixOf(testInfo)}.png`, fullPage: true })
    await page.locator('.request-filters .dropdown-filter__trigger').first().click()
    await page.getByRole('option', { name: isEn(testInfo) ? 'Submitted' : 'مُرسل', exact: true }).click()
    await expect(rows).toHaveCount(1)
    await expect(rows.first()).toContainText('ACC-2026-000005')
    expect(errors).toEqual([])
  })

  test('the follower assigns a submitted request and reassigns another with a mandatory reason', async ({ page }, testInfo) => {
    await signIn(page, testInfo, STAFF.follower)
    await page.goto('/admin/requests/9105')
    await page.locator('.request-actions .btn--primary').click()
    const dialog = page.locator('.modal')
    await expect(dialog.locator('.assign-dialog__option').first()).toBeVisible()
    await page.screenshot({ path: `test-results/requests-assign-${suffixOf(testInfo)}.png`, fullPage: true })
    await dialog.locator('.assign-dialog__option').nth(1).click()
    await dialog.locator('button[type="submit"]').click()
    await expect(page.locator('.request-facts')).toContainText(isEn(testInfo) ? 'Assigned' : 'مُسند')
    await expect(page.locator('.assignment-history__item')).toHaveCount(1)

    await page.goto('/admin/requests/9108')
    await page.locator('.request-actions .btn--secondary').first().click()
    await dialog.locator('.assign-dialog__option').first().click()
    await dialog.locator('button[type="submit"]').click()
    await expect(dialog.locator('.field__error')).toBeVisible()
    await dialog.locator('textarea').fill(REASON)
    await dialog.locator('button[type="submit"]').click()
    await expect(page.locator('.assignment-history__item')).toHaveCount(3)
    await expect(page.locator('.assignment-history')).toContainText(REASON)
    await page.screenshot({ path: `test-results/requests-detail-${suffixOf(testInfo)}.png`, fullPage: true })
  })

  test('the follower reads the review without editing and prepares the evidence bundle', async ({ page }, testInfo) => {
    await signIn(page, testInfo, STAFF.follower)
    await page.goto('/admin/requests/9108/review')
    await expect(page.locator('.review-header')).toBeVisible()
    await expect(page.locator('.review-notices')).toContainText(isEn(testInfo) ? 'Read-only view' : 'عرض للقراءة فقط')
    await expect(page.locator('.raw-score input').first()).toBeDisabled()
    await expect(page.locator('.review-actions .btn')).toHaveCount(0)
    await page.locator('.review-page__title-row .btn--secondary').click()
    await expect(page).toHaveURL(/\/admin\/requests\/9108\/evidence-bundles\/[0-9a-f-]{36}$/)
    await expect(page.locator('.bundle-card__note')).toBeVisible()
    await expect(page.locator('.bundle-card .btn--primary')).toBeVisible({ timeout: 10000 })
    await page.screenshot({ path: `test-results/requests-bundle-${suffixOf(testInfo)}.png`, fullPage: true })
    const download = page.waitForEvent('download')
    await page.locator('.bundle-card .btn--primary').click()
    expect((await download).suggestedFilename()).toBe('ACC-2026-000008-evidence.zip')
  })
})

test.describe('Assessor', () => {
  test('the assessor sees the assigned tabs, starts a review and requests completion with an expiry outcome', async ({ page }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, STAFF.assessor)
    await page.goto('/admin/requests/assigned')
    const rows = page.locator('.table:not(.table--loading) tbody .table__row')
    await expect(rows).toHaveCount(1)
    await expect(rows).toContainText('ACC-2026-000007')
    await page.locator('.tabs__tab').nth(1).click()
    await expect(rows).toHaveCount(1)
    await expect(rows.filter({ hasText: 'ACC-2026-000008' })).toHaveCount(1)

    await page.goto('/admin/requests/9107/review')
    await page.locator('.review-actions .btn--primary').click()
    await expect(page.locator('.review-header')).toContainText(isEn(testInfo) ? 'Under review' : 'قيد المراجعة')
    await page.locator('.review-actions .btn--secondary').nth(1).click()
    const dialog = page.locator('.modal')
    await dialog.locator('textarea').first().fill('يرجى إرفاق القوائم المالية المدققة للعام الأخير وتحديث وصف الخطة الاستراتيجية.')
    await dialog.locator('.completion-dialog__option .checkbox input').nth(1).check()
    await dialog.locator('input[type="date"]').fill(new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10))
    await dialog.locator('.radio-group input').nth(1).check()
    await page.screenshot({ path: `test-results/requests-completion-assessor-${suffixOf(testInfo)}.png`, fullPage: true })
    await dialog.locator('button[type="submit"]').click()
    await expect(page.locator('.review-notices')).toContainText(isEn(testInfo) ? 'Waiting for the entity' : 'بانتظار الجهة')
    await expect(page.locator('.review-notices .completion-card')).toContainText(isEn(testInfo) ? 'closed as incomplete' : 'لعدم الاستكمال')
    expect(errors).toEqual([])
  })

  test('the assessor scores every item with optional feedback and completes the assessment', async ({ page }, testInfo) => {
    test.setTimeout(60000)
    await signIn(page, testInfo, STAFF.assessor)
    await page.goto('/admin/requests/9108/review')
    await expect(page.locator('.review-header')).toBeVisible()
    await page.screenshot({ path: `test-results/requests-review-${suffixOf(testInfo)}.png`, fullPage: true })
    await fillEmptyScores(page, '8')
    await page.locator('#review-sub-901 textarea').first().fill('التزام واضح بمؤشرات الأداء المالية.')
    await page.locator('.review-actions .btn--secondary').first().click()
    await expect(page.locator('.review-header__progress-text')).toContainText(/26\D+26/)
    await expect(page.locator('.review-actions__status')).toContainText(isEn(testInfo) ? 'Everything is saved' : 'كل شيء محفوظ')
    await page.locator('.review-actions .btn--primary').click()
    await page.locator('.modal .btn--primary').click()
    await expect(page.locator('.review-header')).toContainText(isEn(testInfo) ? 'Completed' : 'مكتمل')
  })
})

test.describe('Super Admin and settings', () => {
  test('the Super Admin reopens a completed request with a reason and overrides published scores', async ({ page }, testInfo) => {
    await signIn(page, testInfo, STAFF.superAdmin)
    await page.goto('/admin/requests/9104/review')
    await page.locator('.review-notices .btn--danger').click()
    const dialog = page.locator('.modal')
    await dialog.locator('.override-dialog__grid input').first().fill('9.5')
    await dialog.locator('textarea').fill(OVERRIDE_REASON)
    await dialog.locator('button[type="submit"]').click()
    await expect(dialog).toHaveCount(0)

    await page.goto('/admin/requests/9104')
    await expect(page.locator('.request-actions .btn--danger')).toHaveCount(2)
    await page.locator('.request-actions .btn--danger').first().click()
    await dialog.locator('textarea').fill('short')
    await dialog.locator('textarea').blur()
    await expect(dialog.locator('.field__error')).toBeVisible()
    await dialog.locator('textarea').fill(OVERRIDE_REASON)
    await expect(dialog.locator('.field__error')).toHaveCount(0)
    await dialog.locator('button[type="submit"]').click()
    await expect(dialog).toHaveCount(0)
    await expect(page.locator('.request-facts')).toContainText(isEn(testInfo) ? 'Under review' : 'قيد المراجعة')
    await expect(page.locator('.assessment-timeline')).toContainText(isEn(testInfo) ? 'Request reopened' : 'أُعيد فتح الطلب')
  })

  test('the System Administrator updates the review deadline and the upload limits', async ({ page }, testInfo) => {
    await signIn(page, testInfo, STAFF.systemAdmin)
    await page.goto('/admin/settings/assessment')
    const sla = page.locator('input[name="reviewSlaDays"]')
    await expect(sla).toHaveValue('14')
    await sla.fill('500')
    await page.locator('.path-settings .btn--primary').click()
    await expect(page.locator('.field__error').first()).toBeVisible()
    await sla.fill('21')
    await page.locator('.path-settings .btn--primary').click()
    await expect(page.locator('.toast--success').first()).toBeVisible()
    await page.screenshot({ path: `test-results/settings-assessment-${suffixOf(testInfo)}.png`, fullPage: true })
  })
})
