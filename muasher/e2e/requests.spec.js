import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { applyOpenListFilters, openListFilters } from './support/listFilters'

const IGNORED_CONSOLE = /Failed to load resource|40\d|50\d/i

const STAFF = {
  follower: { id: 8, roles: ['request_follower', 'filtering_officer'] },
  assessor: { id: 10, roles: ['assessor'] },
  systemAdmin: { id: 1, roles: ['system_admin'] },
  superAdmin: {
    id: 900,
    roles: [],
    superAdmin: true,
    name: { ar: 'مدير النظام الرئيسي', en: 'Super Admin' },
  },
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
    if (['error', 'warning'].includes(message.type()) && !IGNORED_CONSOLE.test(message.text()))
      errors.push(message.text())
  })
  page.on('pageerror', (error) => errors.push(error.message))
  return errors
}

async function fillEmptyScores(page, value) {
  const mains = page.locator('.station-row__button')
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
  // Admin review 2026-11-20 (R1): the assessor stays in every state; a reassigned request is marked.
  test('the list keeps the assessor of a completed request and marks a reassigned one', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, STAFF.follower)
    await page.goto('/admin/requests?stage=closed')
    const completed = page.locator('tbody tr', { hasText: 'ACC-2026-000003' })
    await expect(completed.locator('.request-queue__assessor')).toBeVisible()
    await expect(completed).not.toContainText(isEn(testInfo) ? 'Not assigned' : 'غير مُسند')
    await page
      .locator('.stats-card', { hasText: isEn(testInfo) ? 'In progress' : 'قيد العمل' })
      .click()
    await expect(page).toHaveURL(/stage=in_progress/)
    const moved = page.locator('tbody tr', { hasText: 'ACC-2026-000008' })
    await expect(moved.locator('.request-queue__assessor')).toContainText(
      isEn(testInfo) ? 'Reassigned' : 'أُعيد الإسناد',
    )
    await expect(page.locator('.requests-page__subtitle')).toHaveCount(0)
    await page.screenshot({
      path: `test-results/requests-queue-stages-${suffixOf(testInfo)}.png`,
      fullPage: true,
    })
    expect(errors).toEqual([])
  })

  test('the queue shows stats, overdue with icon and text, and filters by status', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, STAFF.follower)
    await page.goto('/admin/requests')
    const rows = page.locator('.table:not(.table--loading) tbody .table__row')
    await expect(rows.first()).toBeVisible()
    await expect(page.locator('.stats-card')).toHaveCount(4)
    const overdue = page.locator('.overdue-badge.badge--danger').first()
    await expect(overdue).toBeVisible()
    await expect(overdue.locator('svg.app-icon')).toHaveCount(1)
    await page.screenshot({
      path: `test-results/requests-queue-${suffixOf(testInfo)}.png`,
      fullPage: true,
    })
    const filters = await openListFilters(page)
    await filters.locator('.dropdown-field').first().click()
    await page
      .getByRole('option', { name: isEn(testInfo) ? 'Submitted' : 'مُرسل', exact: true })
      .click()
    await applyOpenListFilters(page)
    await expect(rows).toHaveCount(1)
    await expect(rows.first()).toContainText('ACC-2026-000005')
    await expect(page.locator('.queue-stages__chip[aria-pressed="true"]')).toContainText('(1)')
    expect(errors).toEqual([])
  })

  test('the follower assigns straight from the queue row without leaving the list', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, STAFF.follower)
    await page.goto('/admin/requests')
    const row = page.locator('.table__row', { hasText: 'ACC-2026-000005' })
    const assign = row.locator('[data-testid="queue-row-assign"]')
    await expect(assign).toHaveText(isEn(testInfo) ? 'Assign' : 'إسناد')
    await expect(
      page
        .locator('.table__row', { hasText: 'ACC-2026-000007' })
        .locator('[data-testid="queue-row-assign"]'),
    ).toHaveText(isEn(testInfo) ? 'Reassign' : 'إعادة إسناد')
    await assign.click()
    const dialog = page.locator('.modal')
    await dialog.locator('.assign-dialog__option').nth(1).click()
    await dialog.locator('button[type="submit"]').click()
    await expect(row.locator('[data-testid="queue-row-assign"]')).toHaveText(
      isEn(testInfo) ? 'Reassign' : 'إعادة إسناد',
    )
    await expect(page).toHaveURL(/\/admin\/requests$/)
    await page.screenshot({
      path: `test-results/requests-queue-row-assign-${suffixOf(testInfo)}.png`,
      fullPage: true,
    })
    expect(errors).toEqual([])
  })

  test('the follower assigns a submitted request and reassigns another with a mandatory reason', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, STAFF.follower)
    await page.goto('/admin/requests/9105')
    await expect(
      page.locator('.request-detail__assign [data-testid="request-assign"]'),
    ).toBeVisible()
    await expect(page.locator('.request-actions [data-testid="request-assign"]')).toHaveCount(0)
    await expect(page.locator('.request-payment-card__facts')).toHaveCount(0)
    await expect(page.locator('.request-facts')).toContainText(isEn(testInfo) ? 'Paid' : 'مدفوعة')
    await expect(page.locator('.request-facts')).not.toContainText('ENT-')
    await expect(page.locator('.request-certificate')).toHaveCount(0)
    await page.locator('.request-detail__heading [data-testid="request-assign"]').click()
    const dialog = page.locator('.modal')
    await expect(dialog.locator('.assign-dialog__option').first()).toBeVisible()
    await page.screenshot({
      path: `test-results/requests-assign-${suffixOf(testInfo)}.png`,
      fullPage: true,
    })
    await dialog.locator('.assign-dialog__option').nth(1).click()
    await dialog.locator('button[type="submit"]').click()
    await expect(page.locator('.request-facts')).toContainText(
      isEn(testInfo) ? 'Assigned' : 'مُسند',
    )
    await expect(page.locator('.assignment-history__item')).toHaveCount(1)

    await page.goto('/admin/requests/9108')
    await page.locator('.request-detail__heading [data-testid="request-assign"]').click()
    await dialog.locator('.assign-dialog__option').first().click()
    await dialog.locator('button[type="submit"]').click()
    await expect(dialog.locator('.field__error')).toBeVisible()
    await dialog.locator('textarea').fill(REASON)
    await dialog.locator('button[type="submit"]').click()
    await expect(page.locator('.assignment-history__item')).toHaveCount(3)
    await expect(page.locator('.assignment-history')).toContainText(REASON)
    await page.screenshot({
      path: `test-results/requests-detail-${suffixOf(testInfo)}.png`,
      fullPage: true,
    })
  })

  test('the follower reads the review without editing and prepares the evidence bundle', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, STAFF.follower)
    await page.goto('/admin/requests/9108/review')
    await expect(page.locator('.review-header')).toBeVisible()
    await expect(page.locator('.review-notices')).toContainText(
      isEn(testInfo) ? 'Read-only view' : 'عرض للقراءة فقط',
    )
    await expect(page.locator('.raw-score input').first()).toBeDisabled()
    await expect(page.locator('.review-actions .btn--secondary')).toHaveCount(0)
    await expect(page.locator('[data-testid="review-complete"]')).toHaveCount(0)
    await page.locator('.review-page__title-row .btn--secondary').click()
    await expect(page).toHaveURL(/\/admin\/requests\/9108\/evidence-bundles\/[0-9a-f-]{36}$/)
    await expect(page.locator('.bundle-card__note')).toBeVisible()
    await expect(page.locator('.bundle-card .btn--primary')).toBeVisible({ timeout: 10000 })
    await page.screenshot({
      path: `test-results/requests-bundle-${suffixOf(testInfo)}.png`,
      fullPage: true,
    })
    const download = page.waitForEvent('download')
    await page.locator('.bundle-card .btn--primary').click()
    expect((await download).suggestedFilename()).toBe('ACC-2026-000008-evidence.zip')
  })
})

test.describe('Assessor', () => {
  test('the assessor sees one sortable list of assigned requests, starts a review and requests completion with an expiry outcome', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, STAFF.assessor)
    await page.goto('/admin/requests/assigned')
    const rows = page.locator('.table:not(.table--loading) tbody .table__row')
    // One list of every assigned request (no tabs), soonest due first.
    await expect(page.locator('.tabs__tab')).toHaveCount(0)
    await expect(rows.filter({ hasText: 'ACC-2026-000007' })).toHaveCount(1)
    await expect(rows.filter({ hasText: 'ACC-2026-000008' })).toHaveCount(1)
    const due = page.getByRole('columnheader', { name: isEn(testInfo) ? 'Due' : 'الاستحقاق' })
    await expect(due).toHaveAttribute('aria-sort', 'ascending')
    await due.getByRole('button').click()
    await expect(due).toHaveAttribute('aria-sort', 'descending')

    await page.goto('/admin/requests/9107/review')
    await page.locator('[data-testid="review-start"]').click()
    await expect(page.locator('.review-header')).toContainText(
      isEn(testInfo) ? 'Under review' : 'قيد المراجعة',
    )
    await page.locator('[data-testid="review-request-completion"]').click()
    const dialog = page.locator('.modal')
    await dialog
      .locator('textarea')
      .first()
      .fill('يرجى إرفاق القوائم المالية المدققة للعام الأخير وتحديث وصف الخطة الاستراتيجية.')
    await dialog.locator('.completion-dialog__option input').nth(1).check()
    await dialog
      .locator('input[type="date"]')
      .fill(new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10))
    await dialog.locator('.radio-group input').nth(1).check()
    await page.screenshot({
      path: `test-results/requests-completion-assessor-${suffixOf(testInfo)}.png`,
      fullPage: true,
    })
    await dialog.locator('button[type="submit"]').click()
    await expect(page.locator('.review-notices')).toContainText(
      isEn(testInfo) ? 'Waiting for the entity' : 'بانتظار الجهة',
    )
    await expect(page.locator('.review-notices .completion-card')).toContainText(
      isEn(testInfo) ? 'closed as incomplete' : 'لعدم الاستكمال',
    )
    expect(errors).toEqual([])
  })

  test('an attachment downloads under its own name, not the API fallback «download»', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, STAFF.assessor)
    await page.goto('/admin/requests/9108/review')
    const card = page.locator('.review-evidence__card').first()
    await expect(card).toBeVisible()
    const fileName = (await card.locator('.review-evidence__name').textContent()).trim()
    const download = page.waitForEvent('download')
    await card.getByRole('button').click()
    expect((await download).suggestedFilename()).toBe(fileName)
  })

  test('the assessor scores every item with optional feedback and completes the assessment', async ({
    page,
  }, testInfo) => {
    test.setTimeout(60000)
    await signIn(page, testInfo, STAFF.assessor)
    await page.goto('/admin/requests/9108/review')
    await expect(page.locator('.review-header')).toBeVisible()
    await page.screenshot({
      path: `test-results/requests-review-${suffixOf(testInfo)}.png`,
      fullPage: true,
    })
    await fillEmptyScores(page, '8')
    await page
      .locator('#review-sub-901 textarea')
      .first()
      .fill('التزام واضح بمؤشرات الأداء المالية.')
    await page.locator('.review-actions .btn--secondary').first().click()
    await expect(page.locator('.review-header__progress-text')).toContainText(/26\D+26/)
    await expect(page.locator('.review-actions__status')).toContainText(
      isEn(testInfo) ? 'Everything is saved' : 'كل شيء محفوظ',
    )
    await page.locator('.review-actions .btn--primary').click()
    await page.locator('.modal .btn--primary').click()
    await expect(page.locator('.review-header')).toContainText(
      isEn(testInfo) ? 'Completed' : 'مكتمل',
    )
  })
})

test.describe('Super Admin and settings', () => {
  test('the Super Admin reopens a completed request with a reason and overrides published scores', async ({
    page,
  }, testInfo) => {
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
    await expect(page.locator('.request-facts')).toContainText(
      isEn(testInfo) ? 'Under review' : 'قيد المراجعة',
    )
    await expect(page.locator('.assessment-timeline')).toContainText(
      isEn(testInfo) ? 'Request reopened' : 'أُعيد فتح الطلب',
    )
  })

  test('the System Administrator updates the review deadline and the upload limits', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, STAFF.systemAdmin)
    await page.goto('/admin/settings/assessment?tab=accredited')
    const sla = page.locator('input[name="reviewSlaDays"]')
    const save = page.locator('#path-settings-accredited button[type="submit"]')
    await expect(sla).toHaveValue('14')
    await sla.fill('500')
    await save.click()
    await expect(page.locator('.field__error').first()).toBeVisible()
    await sla.fill('21')
    await save.click()
    await expect(page.locator('.toast--success').first()).toBeVisible()
    await page.screenshot({
      path: `test-results/settings-assessment-${suffixOf(testInfo)}.png`,
      fullPage: true,
    })
  })
})
