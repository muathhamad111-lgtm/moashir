import { Buffer } from 'node:buffer'
import { test, expect } from '@playwright/test'
import { seedPreferences, seedSignedIn } from './support/session'

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

  test('an eligible entity applies, sees the server completeness on submit and deletes its draft', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.albirr)
    await page.goto('/assessment/advanced')
    const eligibility = page.locator('.advanced-entity .eligibility-list__item')
    await expect(eligibility).toHaveCount(3)
    await expect(page.locator('.advanced-entity .eligibility-list__item--failed')).toHaveCount(0)
    await expect(page.locator('.advanced-entity__previous')).toBeVisible()
    await shot(page, 'award-entity-eligibility', testInfo)
    await page.locator('.advanced-request__actions .btn--primary').click()
    await expect(page).toHaveURL(/\/assessment\/advanced\/form\/\d+$/)
    await expect(page.locator('.accredited-completeness')).toBeVisible()
    await shot(page, 'award-entity-draft', testInfo)
    await page.locator('.accredited-actions .btn--primary').click()
    await expect(page.locator('.accredited-completeness__item').first()).toBeVisible()
    await expect(page.locator('.modal')).toHaveCount(0)
    await page.locator('.accredited-actions .btn--danger').click()
    await page.locator('.modal .btn--danger').click()
    await expect(page).toHaveURL(/\/assessment\/advanced$/)
    await expect(page.locator('.advanced-request__actions .btn--primary')).toContainText(
      text(testInfo, 'Apply for the award', 'التقديم على الجائزة'),
    )
    expect(errors).toEqual([])
  })

  test('an entity outside the cycle regions sees the recorded conditions rejection', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.tamkeen)
    await page.goto('/assessment/advanced')
    const request = page.locator('.advanced-request')
    await expect(request.locator('.status-badge, .badge').first()).toBeVisible()
    await expect(request).toContainText(
      text(testInfo, 'did not meet the entry conditions', 'لم يستوفِ طلبك شروط الدخول'),
    )
    await expect(request.locator('.eligibility-list__item--failed')).toHaveCount(1)
    await expect(request.locator('.advanced-request__actions .btn--primary')).toHaveCount(0)
  })

  test('the entity sees "under review" while its request waits for filtering', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.ruwad)
    await page.goto('/assessment/advanced')
    const request = page.locator('.advanced-request')
    await expect(request).toContainText(text(testInfo, 'Under review', 'قيد المراجعة'))
    await expect(request).not.toContainText(text(testInfo, 'filtering', 'التصفية'))
  })

  test('a returned request lets the entity edit only the listed items and resubmit', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.namaa)
    await page.goto('/assessment/advanced')
    await expect(page.locator('.advanced-request')).toContainText(
      text(testInfo, 'returned for completion', 'أُعيد طلبك للاستكمال'),
    )
    await page.locator('.advanced-request__link').click()
    await expect(page.locator('.completion-card')).toBeVisible()
    await expect(page.locator('.completion-card__item')).toHaveCount(2)
    await page.locator('.criteria-tree__open').nth(1).click()
    const cv = page.locator('#accredited-sub-201 textarea')
    await expect(cv).toBeEditable()
    await cv.fill(
      'أجرت الجهة دراسة احتياجات شملت ألف مستفيد عبر الاستبانات والمقابلات المنظمة، وبُنيت البرامج على نتائجها.',
    )
    await expect(page.locator('#accredited-sub-202 textarea')).toBeDisabled()
    await page.locator('.criteria-tree__open').nth(7).click()
    await page
      .locator('#accredited-sub-802 .evidence-slots__slot')
      .first()
      .locator('.evidence-upload__input')
      .setInputFiles({ name: 'impact-report-2026.pdf', mimeType: 'application/pdf', buffer: PDF })
    await expect(page.locator('#accredited-sub-802 .evidence-file')).toHaveCount(1)
    await shot(page, 'award-entity-completion', testInfo)
    await page.locator('.accredited-actions .btn--primary').click()
    await page.locator('.modal .btn--primary').click()
    await expect(page).toHaveURL(/\/assessment\/advanced$/)
    await expect(page.locator('.advanced-request')).toContainText(
      text(testInfo, 'under review', 'قيد المراجعة'),
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
    await page.locator('.path-switch__link').nth(1).click()
    await expect(page).toHaveURL(/\/admin\/requests\?path=advanced$/)
    await expect(rows.first()).toContainText('ADV-')
    await expect(rows.first()).toContainText(
      text(testInfo, 'Moashir Institutional Excellence Award', 'دورة جائزة مؤشر للتميز المؤسسي'),
    )
    await page.goto('/admin/requests/9203')
    await page
      .locator('.request-actions button', {
        hasText: text(testInfo, 'Return for completion', 'إعادة للاستكمال'),
      })
      .click()
    const dialog = page.locator('.modal')
    await expect(dialog.locator('.completion-dialog')).toBeVisible()
    await dialog.locator('textarea[name="notes"]').fill(RETURN_NOTES)
    await dialog.locator('.completion-dialog__option input[type="checkbox"]').first().check()
    const deadline = dialog.locator('input[type="date"]')
    await deadline.fill(await deadline.getAttribute('min'))
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
    await page
      .locator('.request-actions button', {
        hasText: text(testInfo, 'Withdraw the request', 'سحب الطلب'),
      })
      .click()
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
    await page.getByRole('tab').nth(1).click()
    await expect(page.locator('.table__row', { hasText: 'ADV-' }).first()).toBeVisible()
    await page.goto('/admin/requests/9202/review')
    await expect(page.locator('.review-actions')).toBeVisible()
    await expect(
      page.locator('.review-actions button', {
        hasText: text(testInfo, 'Request completion', 'طلب استكمال'),
      }),
    ).toHaveCount(0)
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
    await expect(page.locator('.cycle-form')).toBeVisible()
    await expect(page.locator('input[name="disqualificationScore"]')).toHaveValue('60.00')
    await page.locator('input[name="name.ar"]').fill('دورة جائزة مؤشر التجريبية للمناطق الشمالية')
    await page.locator('input[name="name.en"]').fill('Moashir Award for the Northern Regions')
    const year = new Date().getFullYear() + 2
    await page.locator('input[name="submissionStart"]').fill(`${year}-02-01`)
    await page.locator('input[name="submissionEnd"]').fill(`${year}-03-15`)
    await page.locator('input[name="committeeStart"]').fill(`${year}-04-01`)
    await page.locator('input[name="committeeEnd"]').fill(`${year}-05-15`)
    await page.locator('.checkbox-group input[type="checkbox"]').nth(5).check()
    await page.locator('.checkbox-group input[type="checkbox"]').nth(6).check()
    await shot(page, 'award-cycle-editor-new', testInfo)
    await page.locator('.cycle-form__actions .btn--primary').click()
    await expect(page).toHaveURL(/\/admin\/award\/cycles\/\d+$/)
    await expect(page.locator('.cycle-actions')).toContainText(
      text(testInfo, 'Submissions cannot open yet', 'لا يمكن فتح التقديم بعد'),
    )
    await expect(page.locator('.cycle-actions .btn--primary')).toBeDisabled()
    await expect(page.locator('.cycle-staff')).toBeVisible()
    await shot(page, 'award-cycle-editor', testInfo)
    expect(errors).toEqual([])
  })

  test('the running cycle locks its stage fields and refuses to close while requests are open', async ({
    page,
  }, testInfo) => {
    await signIn(page, testInfo, USERS.systemAdmin)
    await page.goto('/admin/award/cycles/31')
    await expect(page.locator('input[name="submissionStart"]')).toBeDisabled()
    await expect(page.locator('input[name="submissionEnd"]')).toBeEnabled()
    await page
      .locator('.cycle-actions button', {
        hasText: text(testInfo, 'Close submissions', 'إغلاق التقديم'),
      })
      .click()
    await page.locator('.modal .btn--danger').click()
    await expect(page.locator('.cycle-editor__title-row')).toContainText(
      text(testInfo, 'Submissions closed', 'التقديم مغلق'),
    )
    await page
      .locator('.cycle-actions button', {
        hasText: text(testInfo, 'Close the cycle', 'إغلاق الدورة'),
      })
      .click()
    await page.locator('.modal .btn--danger').click()
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
    await page.locator('.cycle-table__link').first().click()
    await expect(page).toHaveURL(/\/admin\/award\/cycles\/31\/filtering$/)
    const rows = page.locator('.table:not(.table--loading) tbody .table__row')
    await expect(rows).toHaveCount(2)
    await expect(page.locator('.filtering-threshold')).toContainText('60.00')
    await expect(page.locator('.assessment-notice').first()).toContainText(
      text(
        testInfo,
        'Decisions open after the submission period',
        'تُتاح القرارات بعد فترة التقديم',
      ),
    )
    await shot(page, 'award-filtering-queue', testInfo)
    await rows.last().locator('.cycle-table__link').click()
    await expect(page.locator('.request-facts')).toBeVisible()
    await expect(
      page.locator('.accredited-feedback, .feedback-list, .accredited-result').first(),
    ).toBeVisible()
    await expect(page.locator('.filtering-actions .btn--primary')).toBeDisabled()
    await shot(page, 'award-filtering-detail', testInfo)
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
    await page.locator('.modal .btn--danger').click()
    const reason = page.locator('.modal').filter({ has: page.locator('textarea[name="reason"]') })
    await reason.locator('textarea').fill(SUPER_REASON)
    await reason.locator('button[type="submit"]').click()
    await expect(page.locator('.cycle-editor__title-row')).toContainText(
      text(testInfo, 'Submissions closed', 'التقديم مغلق'),
    )
    await page
      .locator('.cycle-actions a', { hasText: text(testInfo, 'Open the filtering', 'فتح التصفية') })
      .click()
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
