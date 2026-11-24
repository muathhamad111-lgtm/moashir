import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'
import { chooseRequestAction } from './support/requestRecord'
import { completionSheet, nextCompletionStep } from './support/reviewWork'

const IGNORED_CONSOLE = /Failed to load resource|40\d|50\d/i
const PDF = Buffer.from('%PDF-1.4\n% Award evidence\n%%EOF\n')

const USERS = {
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
  ruwad: {
    id: 6005,
    roles: ['entity'],
    entityId: 6005,
    name: { ar: 'خالد بن عبدالعزيز السبيعي', en: 'Khalid Abdulaziz Al-Subaie' },
  },
  reviewed: {
    id: 6002,
    roles: ['entity'],
    entityId: 6002,
    name: { ar: 'فهد بن ناصر الدوسري', en: 'Fahad Nasser Al-Dosari' },
  },
  tamkeen: {
    id: 6006,
    roles: ['entity'],
    entityId: 6006,
    name: { ar: 'نورة بنت سعد العنزي', en: 'Noura Saad Al-Anazi' },
  },
  follower: { id: 9, roles: ['request_follower'] },
  assessor: { id: 10, roles: ['assessor'] },
  filteringOfficer: { id: 13, roles: ['filtering_officer'] },
  systemAdmin: { id: 1, roles: ['system_admin'] },
  superAdmin: {
    id: 900,
    roles: [],
    superAdmin: true,
    name: { ar: 'مدير النظام الرئيسي', en: 'Super Admin' },
  },
}

const RETURN_NOTES =
  'يرجى استكمال وصف منهجية العمل التطوعي وإرفاق تقرير الأثر المحدّث قبل نهاية المهلة.'
const WITHDRAW_REASON = 'سحب الطلب بناءً على خطاب رسمي من الجهة لظروف تنظيمية طارئة.'
const OVERRIDE_REASON =
  'أظهرت الشواهد أثرًا مجتمعيًا واضحًا يبرر تأهيل الجهة استثناءً لتقييم اللجنة.'
const SUPER_REASON = 'إجراء تشغيلي معتمد من إدارة الجائزة لإغلاق فترة التقديم.'
const REOPEN_REASON = 'إعادة فتح الدورة لمراجعة ملاحظات اللجنة على النتائج المعلنة سابقًا.'

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function signIn(page, testInfo, user) {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

function text(testInfo, en, ar) {
  return isEn(testInfo) ? en : ar
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

async function shot(page, name, testInfo) {
  await page.screenshot({ path: `test-results/${name}-${suffixOf(testInfo)}.png`, fullPage: true })
}

async function fillEmptyScores(page, value) {
  const mains = page.locator('.station-row__button, .review-strip__step')
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

test.describe('Award public intro and entity path', () => {
  test('a guest sees the current cycle with dates, regions and conditions', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await seedPreferences(page, { locale: isEn(testInfo) ? 'en' : 'ar' })
    await page.goto('/assessment/advanced')
    const card = page.locator('.public-cycle')
    await expect(card.locator('.public-cycle__name')).toBeVisible()
    await expect(card.locator('.cycle-dates__row')).toHaveCount(3)
    await expect(card.locator('.cycle-regions__item')).toHaveCount(4)
    await expect(card).toContainText(text(testInfo, 'Accepting applications', 'يستقبل الطلبات'))
    await expect(page.locator('.advanced-intro__guest a')).toHaveAttribute('href', /redirect=/)
    await shot(page, 'award-public-intro', testInfo)
    expect(errors).toEqual([])
  })

  // AJ-04: a signed-in entity follows the award from its path page, never from the public intro.
  test('a signed-in entity opening the public intro lands on its award path page', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.albirr)
    await page.goto('/assessment/advanced')
    await expect(page).toHaveURL(/\/paths\/award$/)
    await expect(page.locator('[data-testid="path-status-card"]')).toBeVisible()
  })

  test('an eligible entity applies, sees the server completeness on submit and deletes its draft', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.albirr)
    await page.goto('/paths/award')
    const status = page.locator('[data-testid="path-status-card"]')
    await expect(status).toHaveAttribute('data-state', 'eligible')
    await shot(page, 'award-entity-eligibility', testInfo)
    await page.locator('[data-testid="path-primary-action"]').click()
    await expect(page).toHaveURL(/\/assessment\/advanced\/form\/\d+$/)
    await expect(page.locator('.station-row__button, .review-strip__step').first()).toBeVisible()
    await expect(page.locator('.accredited-completeness')).toHaveCount(0)
    await shot(page, 'award-entity-draft', testInfo)
    await page.locator('.station-row__button, .review-strip__step').last().click()
    await page.locator('[data-testid="accredited-submit"]').click()
    await expect(page.locator('.accredited-completeness__item').first()).toBeVisible()
    await expect(page.locator('.modal')).toHaveCount(0)
    await page.locator('.accredited-actions .btn--danger').click()
    await page.locator('.modal .btn--danger').click()
    await expect(page).toHaveURL(/\/paths\/award$/)
    await expect(page.locator('[data-testid="path-primary-action"]')).toContainText(
      text(testInfo, 'Apply for the award', 'قدّم على الجائزة'),
    )
    expect(errors).toEqual([])
  })

  test('an entity outside the cycle regions sees why it cannot apply', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.tamkeen)
    await page.goto('/paths/award')
    await expect(page.locator('[data-testid="path-status-card"]')).toHaveAttribute(
      'data-state',
      'notEligible',
    )
    await expect(page.locator('[data-testid="path-primary-action"]')).toHaveCount(0)
  })

  test('the entity sees "under review" while its request waits for filtering', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.ruwad)
    await page.goto('/paths/award')
    const status = page.locator('[data-testid="path-status-card"]')
    await expect(status).toContainText(text(testInfo, 'Under review', 'قيد المراجعة'))
    await expect(status).not.toContainText(text(testInfo, 'filtering', 'التصفية'))
  })

  test('the award path shows "under review" while the initial assessor works', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.reviewed)
    await page.goto('/paths/award')
    const underReview = text(testInfo, 'Under review', 'قيد المراجعة')
    await expect(page.getByTestId('path-status-card')).toContainText(underReview)
    await expect(page.getByTestId('path-requests')).toContainText(underReview)
    await expect(page.locator('main')).not.toContainText(
      text(testInfo, 'Under initial assessment', 'قيد التقييم الأولي'),
    )
  })

  test('a returned request lets the entity edit only the listed items and resubmit', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.namaa)
    await page.goto('/paths/award')
    await expect(page.locator('[data-testid="path-status-card"]')).toHaveAttribute(
      'data-state',
      'needsCompletion',
    )
    await page.locator('[data-testid="path-primary-action"]').click()
    await expect(page.locator('.completion-card')).toBeVisible()
    await expect(page.locator('.completion-card__item')).toHaveCount(2)
    await page.locator('.station-row__button, .review-strip__step').nth(1).click()
    const cv = page.locator('#accredited-sub-201 textarea')
    await expect(cv).toBeEditable()
    await cv.fill(
      'أجرت الجهة دراسة احتياجات شملت ألف مستفيد عبر الاستبانات والمقابلات المنظمة، وبُنيت البرامج على نتائجها.',
    )
    await expect(page.locator('#accredited-sub-202 textarea')).toBeDisabled()
    await page.locator('.station-row__button, .review-strip__step').nth(7).click()
    await page
      .locator('#accredited-sub-802 .evidence-slots__slot')
      .first()
      .locator('.evidence-upload__input')
      .setInputFiles({ name: 'impact-report-2026.pdf', mimeType: 'application/pdf', buffer: PDF })
    await expect(page.locator('#accredited-sub-802 .evidence-file')).toHaveCount(1)
    await shot(page, 'award-entity-completion', testInfo)
    await page.locator('.station-row__button, .review-strip__step').last().click()
    await page.locator('[data-testid="accredited-submit"]').click()
    await page.locator('.modal .btn--primary').click()
    await expect(page).toHaveURL(/\/paths\/award$/)
    // A resubmitted request returns to the assignment queue, which the entity reads as under review.
    await expect(page.locator('[data-testid="path-status-card"]')).toContainText(
      text(testInfo, 'Under review', 'قيد المراجعة'),
    )
    expect(errors).toEqual([])
  })
})

test.describe('Award staff screens', () => {
  test('the follower filters the queue by path and returns a request for completion', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.follower)
    await page.goto('/admin/requests')
    const rows = page.locator('.table:not(.table--loading) tbody .table__row')
    await expect(rows.first()).toBeVisible()
    await page.locator('.sidebar__item--advanced').click()
    await expect(page).toHaveURL(/\/admin\/requests\?path=advanced$/)
    await expect(rows.first()).toContainText('ADV-')
    await page.goto('/admin/requests/9203')
    await chooseRequestAction(page, text(testInfo, 'Return for completion', 'إعادة للاستكمال'))
    // The request is a side sheet in three steps (design §B.3.3): items, message and deadline, review.
    const dialog = completionSheet(page)
    await expect(dialog.locator('.completion-dialog')).toBeVisible()
    await dialog.locator('.completion-dialog__option input[type="checkbox"]').first().check()
    await nextCompletionStep(page)
    await dialog.locator('textarea[name="notes"]').fill(RETURN_NOTES)
    await dialog.locator('.datepicker__calendar').click()
    await expect(dialog.locator('.date-calendar [aria-current="date"]')).toBeFocused()
    await page.keyboard.press('Enter')
    await expect(dialog.locator('.date-calendar')).toHaveCount(0)
    await nextCompletionStep(page)
    await shot(page, 'award-follower-return', testInfo)
    await dialog.locator('button[type="submit"]').click()
    await expect(page.locator('.request-facts')).toContainText(
      text(testInfo, 'Needs completion', 'بحاجة إلى استكمال'),
    )
    await expect(page.locator('.completion-card')).toBeVisible()
    expect(errors).toEqual([])
  })

  test('the follower withdraws a request with a mandatory reason', async ({ page }, testInfo) => {
    await signIn(page, testInfo, USERS.follower)
    await page.goto('/admin/requests/9205')
    await chooseRequestAction(page, text(testInfo, 'Withdraw the request', 'سحب الطلب'))
    const dialog = page.locator('.modal')
    await dialog.locator('button[type="submit"]').click()
    await expect(dialog.locator('.field__error')).toBeVisible()
    await dialog.locator('textarea').fill(WITHDRAW_REASON)
    await dialog.locator('button[type="submit"]').click()
    await expect(page.locator('.request-facts')).toContainText(text(testInfo, 'Withdrawn', 'مسحوب'))
  })

  test('the initial assessor completes the assessment and the request waits for filtering', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.assessor)
    await page.goto('/admin/requests/assigned?path=advanced')
    await expect(page.locator('.table__row', { hasText: 'ADV-' }).first()).toBeVisible()
    await page.goto('/admin/requests/9202/review')
    await expect(page.locator('.review-actions')).toBeVisible()
    // The initial assessor may ask for completion too, once every item is scored (UX-1).
    await expect(page.locator('[data-testid="review-request-completion"]')).toBeDisabled()
    await fillEmptyScores(page, '7')
    await page.locator('.review-actions .btn--primary').click()
    await page.locator('.modal .btn--primary').click()
    await expect(page.locator('.review-header, .review-workspace').first()).toContainText(
      text(testInfo, 'Awaiting filtering', 'بانتظار التصفية'),
    )
    expect(errors).toEqual([])
  })

  test('the System Administrator creates a draft cycle while one runs and sees why it cannot open', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.systemAdmin)
    await page.goto('/admin/award/cycles')
    await expect(page.locator('.table:not(.table--loading) tbody .table__row')).toHaveCount(3)
    await page.locator('.cycles-page__header .btn--primary').click()
    await expect(page.locator('form[data-step="identity"]')).toBeVisible()
    await page.locator('input[name="name.ar"]').fill('دورة جائزة مؤشر للمناطق الشمالية')
    await page.locator('input[name="name.en"]').fill('Moashir Award for the Northern Regions')
    await shot(page, 'award-cycle-editor-new', testInfo)
    await page.locator('.cycle-setup__next').click()
    const year = new Date().getFullYear() + 2
    await page.locator('input[name="submissionStart"]').fill(`${year}-02-01`)
    await page.locator('input[name="submissionEnd"]').fill(`${year}-03-15`)
    await page.locator('input[name="committeeStart"]').fill(`${year}-04-01`)
    await page.locator('input[name="committeeEnd"]').fill(`${year}-05-15`)
    await page.locator('.cycle-setup__next').click()
    await expect(page).toHaveURL(/\/admin\/award\/cycles\/\d+\/setup$/)
    await expect(page.locator('form[data-step="conditions"]')).toBeVisible()
    await expect(page.locator('input[name="disqualificationScore"]')).toHaveValue('60.00')
    for (const step of ['conditions', 'deadlines', 'fee']) {
      await expect(page.locator(`form[data-step="${step}"]`)).toBeVisible()
      await page.locator('.cycle-setup__next').click()
    }
    await expect(page.locator('.cycle-staff')).toBeVisible()
    await page.locator('.cycle-setup__next').click()
    await page.locator('[data-testid="cycle-save-draft"]').click()
    await expect(page).toHaveURL(/\/admin\/award\/cycles\/\d+$/)
    await expect(page.locator('.cycle-actions')).toContainText(
      text(testInfo, 'Submissions cannot open yet', 'لا يمكن فتح التقديم بعد'),
    )
    await expect(page.locator('.cycle-actions .btn--primary')).toBeDisabled()
    await shot(page, 'award-cycle-editor', testInfo)
    expect(errors).toEqual([])
  })

  test('the running cycle locks its stage fields and refuses to close while requests are open', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await page.goto('/admin/award/cycles/31')
    await expect(page).toHaveURL(/\/admin\/award\/cycles\/31$/)
    await page.locator('[data-testid="cycle-edit"]').click()
    await expect(page).toHaveURL(/\/admin\/award\/cycles\/31\/setup$/)
    await page.locator('.cycle-summary__section[data-step="dates"] .btn').click()
    await expect(page.locator('input[name="submissionStart"]')).toBeDisabled()
    await expect(page.locator('input[name="submissionEnd"]')).toBeEnabled()
    await page.locator('.wizard__button').last().click()
    await page.locator('[data-testid="cycle-finish"]').click()
    await expect(page).toHaveURL(/\/admin\/award\/cycles\/31$/)
    await page
      .locator('.cycle-actions button', {
        hasText: text(testInfo, 'Close submissions', 'إغلاق التقديم'),
      })
      .click()
    await page.locator('.modal .btn--primary').click()
    await expect(page.locator('.cycle-setup__title-row')).toContainText(
      text(testInfo, 'Submissions closed', 'التقديم مغلق'),
    )
    await page
      .locator('.cycle-actions button', {
        hasText: text(testInfo, 'Close the cycle', 'إغلاق الدورة'),
      })
      .click()
    await page.locator('.modal .btn--primary').click()
    await expect(page.locator('.cycle-blocked__item').first()).toBeVisible()
    await expect(page.locator('.cycle-blocked')).toContainText('ADV-')
  })

  test('the Super Admin is told why a closed cycle cannot be reopened while another runs', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.superAdmin)
    await page.goto('/admin/award/cycles/30')
    await page
      .locator('.cycle-actions button', {
        hasText: text(testInfo, 'Reopen the cycle', 'إعادة فتح الدورة'),
      })
      .click()
    const dialog = page.locator('.modal')
    await dialog.locator('textarea').fill(REOPEN_REASON)
    await dialog.locator('button[type="submit"]').click()
    await expect(page.locator('.cycle-actions')).toContainText(
      text(testInfo, 'Another cycle is running', 'توجد دورة أخرى قائمة'),
    )
  })

  test('the filtering officer reviews the queue and the read-only initial assessment', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.filteringOfficer)
    await page.goto('/admin/award/filtering')
    // One cycle in progress: the picker opens it directly (phase 1).
    await expect(page).toHaveURL(/\/admin\/award\/cycles\/31\/filtering/)
    await expect(page).toHaveURL(/\/admin\/award\/cycles\/31\/filtering$/)
    await expect(page.locator('.cycle-tabs__tab.is-active')).toHaveAttribute(
      'data-tab',
      'filtering',
    )
    const rows = page.locator('.table:not(.table--loading) tbody .table__row')
    await expect(rows).toHaveCount(2)
    await expect(page.locator('.filtering-threshold')).toContainText('60.00')
    // Decisions are open while submissions are open (operator, 2026-11-22): no window notice.
    await expect(page.locator('.cycles-page .assessment-notice')).toHaveCount(0)
    await shot(page, 'award-filtering-queue', testInfo)
    await rows.last().locator('.cycle-table__link').click()
    await expect(page.locator('.request-facts')).toBeVisible()
    await expect(
      page.locator('.accredited-feedback, .feedback-list, .accredited-result').first(),
    ).toBeVisible()
    await expect(page.locator('.filtering-actions .btn--primary')).toBeEnabled()
    await shot(page, 'award-filtering-detail', testInfo)
    await page.goto('/admin/award/cycles/30/filtering')
    await expect(page.locator('.cycles-page .assessment-notice').first()).toContainText(
      text(testInfo, 'Filtering has ended', 'انتهت مرحلة التصفية'),
    )
    expect(errors).toEqual([])
  })

  test('the Super Admin closes submissions and qualifies a below-threshold request with a reason', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.superAdmin)
    await page.goto('/admin/award/cycles/31')
    await page
      .locator('.cycle-actions button', {
        hasText: text(testInfo, 'Close submissions', 'إغلاق التقديم'),
      })
      .click()
    await page.locator('.modal .btn--primary').click()
    const reason = page.locator('.modal').filter({ has: page.locator('textarea[name="reason"]') })
    await reason.locator('textarea').fill(SUPER_REASON)
    await reason.locator('button[type="submit"]').click()
    await expect(page.locator('.cycle-setup__title-row')).toContainText(
      text(testInfo, 'Submissions closed', 'التقديم مغلق'),
    )
    await page.locator('.cycle-tabs__tab[data-tab="filtering"]').click()
    await expect(page).toHaveURL(/\/admin\/award\/cycles\/31\/filtering$/)
    await expect(page.locator('.cycle-setup__title-row')).toContainText(
      text(testInfo, 'Submissions closed', 'التقديم مغلق'),
    )
    await page
      .locator('.table__row', { hasText: 'ADV-' })
      .filter({ has: page.locator('.badge--danger') })
      .first()
      .locator('.cycle-table__link')
      .click()
    await page.locator('.filtering-actions .btn--primary').click()
    const dialog = page.locator('.modal')
    await expect(dialog.locator('.filtering-decision__warning')).toBeVisible()
    await dialog.locator('button[type="submit"]').click()
    await expect(dialog.locator('.field__error').first()).toBeVisible()
    await dialog.locator('textarea[name="overrideReason"]').fill(OVERRIDE_REASON)
    await dialog.locator('button[type="submit"]').click()
    await expect(page.locator('.filtering-decisions__item')).toHaveCount(1)
    await expect(page.locator('.filtering-decisions')).toContainText(
      text(testInfo, 'Exception', 'استثناء'),
    )
    await shot(page, 'award-filtering-decision', testInfo)
  })
})
