import { test, expect } from '@playwright/test'
import { seedScenario, seedSignedIn } from './support/session'
import { chooseNamed, chooseValue } from './support/dropdown'

const IGNORED_CONSOLE = /Failed to load resource|40\d|50\d/i

const USERS = {
  secretary: { id: 18, roles: ['system_admin', 'award_secretary'] },
  systemAdmin: { id: 1, roles: ['system_admin'] },
  david: { id: 27, roles: ['assessor'] },
  ruwad: {
    id: 6005,
    roles: ['entity'],
    entityId: 6005,
    name: { ar: 'خالد بن عبدالعزيز السبيعي', en: 'Khalid Abdulaziz Al-Subaie' },
  },
}

const RETURN_NOTE =
  'يرجى مراجعة درجات معيار الحوكمة في ضوء الشواهد المرفقة وتوحيد أسس التقدير بين الأعضاء.'
const OPINION = 'أتفق مع المتوسط المحسوب، وأقترح التنويه بممارسات الحوكمة والشفافية لدى الجهة.'
const SUSPEND_REASON = 'إيقاف الحساب مؤقتًا بقرار إداري لحين استكمال إجراءات التعاقد.'

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function text(testInfo, en, ar) {
  return isEn(testInfo) ? en : ar
}

async function signIn(page, testInfo, user, scenario) {
  if (scenario) await seedScenario(page, scenario)
  await seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
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
  await page.screenshot({
    path: `test-results/${name}-${isEn(testInfo) ? 'en-ltr' : 'ar-rtl'}.png`,
    fullPage: true,
  })
}

function rows(page) {
  return page.locator('.table:not(.table--loading) tbody .table__row')
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

test.describe('Award committee roster and start', () => {
  test('a cycle secretary appoints committee assessors and starts the committee assessment', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.secretary, 'qualified')
    await page.goto('/admin/award/committee/manage')
    await page.locator('a[href$="/admin/award/cycles/31/committee"]').click()
    await expect(page).toHaveURL(/\/admin\/award\/cycles\/31\/committee$/)
    await expect(page.locator('.empty-state')).toBeVisible()
    for (const name of [
      'David Nguyen',
      text(testInfo, 'د. منال بنت سعيد الحارثي', 'د. منال بنت سعيد الحارثي'),
    ]) {
      await page.locator('.award-page-header__actions .btn--primary').click()
      const dialog = page.locator('.modal__panel')
      await dialog.locator('.dropdown-combobox__input').click()
      await page.getByRole('option', { name }).click()
      await dialog.locator('button[type="submit"]').click()
      await expect(dialog).toBeHidden()
    }
    await expect(rows(page)).toHaveCount(2)
    await shot(page, 'committee-roster', testInfo)
    await page.locator('.sidebar a[href="/admin/award/cycles"]').click()
    await page.locator('a[href="/admin/award/cycles/31"]').first().click()
    await page
      .locator('.cycle-actions button', {
        hasText: text(testInfo, 'Start the committee assessment', 'بدء تقييم اللجنة'),
      })
      .click()
    await page.locator('.modal .btn--primary').click()
    await expect(page.locator('.cycle-editor__title-row')).toContainText(
      text(testInfo, 'Committee assessment', 'تقييم اللجنة'),
    )
    await page.locator('.cycle-actions a[href="/admin/award/cycles/31/committee"]').click()
    await expect(rows(page).first()).toContainText(
      text(testInfo, '0 of 2 submitted', 'أُرسل 0 من 2'),
    )
    expect(errors).toEqual([])
  })
})

test.describe('Committee assessor', () => {
  test('scores blindly, writes notes and submits the evaluation, which then locks', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.david, 'committee')
    await page.goto('/admin/committee/assignments')
    await expect(rows(page)).toHaveCount(3)
    await shot(page, 'committee-assignments', testInfo)
    await rows(page)
      .filter({ hasText: text(testInfo, 'Ruwad Education Foundation', 'مؤسسة رواد التعليم') })
      .locator('.cycle-table__link')
      .click()
    await expect(page.locator('.committee-workspace .assessment-notice').first()).toContainText(
      text(testInfo, 'Independent evaluation', 'تقييم مستقل'),
    )
    await expect(page.locator('.committee-entity')).toContainText(
      text(testInfo, 'Ruwad Education Foundation', 'مؤسسة رواد التعليم'),
    )
    await fillEmptyScores(page, '8')
    await page
      .locator('textarea[name="committee-general-note"]')
      .fill('أداء مؤسسي متوازن مع فرص واضحة للتحسين في قياس الأثر.')
    await page.locator('.committee-actions .btn--secondary').click()
    await expect(page.locator('.review-actions__status')).toContainText(
      text(testInfo, 'Everything is saved', 'كل شيء محفوظ'),
    )
    await shot(page, 'committee-evaluation', testInfo)
    await page.locator('.committee-actions .btn--primary').click()
    await page.locator('.modal .btn--primary').click()
    await expect(page.locator('.committee-workspace')).toContainText(
      text(testInfo, 'Submitted and locked', 'أُرسل وأُقفل'),
    )
    await expect(page.locator('.committee-actions .btn--primary')).toHaveCount(0)
    expect(errors).toEqual([])
  })

  test('answers a discussion session without seeing the other members names', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.david, 'committee')
    await page.goto('/admin/committee/discussions')
    const card = page.locator('.discussion-card')
    await expect(card).toHaveCount(1)
    await expect(card.locator('.discussion-entries__author').first()).toHaveText(
      text(testInfo, 'Committee member', 'عضو اللجنة'),
    )
    await card.locator('textarea[name="body"]').fill(OPINION)
    await card.locator('button[type="submit"]').click()
    await expect(
      card.locator('.discussion-entries__author', {
        hasText: text(testInfo, 'Your answer', 'ردك'),
      }),
    ).toBeVisible()
    await shot(page, 'committee-discussions', testInfo)
    expect(errors).toEqual([])
  })
})

test.describe('Award Secretary desk', () => {
  test('approves an average, closes a discussion and returns a request to the committee', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.secretary, 'committee')
    await page.goto('/admin/award/secretary')
    await page.locator('.cycle-table__link').first().click()
    await expect(rows(page)).toHaveCount(3)
    await shot(page, 'secretary-desk', testInfo)
    await rows(page)
      .filter({ hasText: 'ADV-' })
      .filter({ hasText: text(testInfo, 'Athar', 'أثر') })
      .locator('.cycle-table__link')
      .click()
    await expect(page.locator('.secretary-facts__percent')).toBeVisible()
    await page.locator('.secretary-evaluation__table, .filtering-actions').first().waitFor()
    await page.locator('.request-detail__main .btn--ghost').first().click()
    await expect(page.locator('.secretary-evaluation__table')).toBeVisible()
    await page.keyboard.press('Escape')
    await shot(page, 'secretary-request', testInfo)
    await page
      .locator('.filtering-actions button', {
        hasText: text(testInfo, 'Approve the average', 'اعتماد المتوسط'),
      })
      .click()
    await page.locator('.modal__panel button[type="submit"]').click()
    await expect(page.locator('.secretary-facts')).toContainText(
      text(testInfo, 'Approved', 'معتمد'),
    )
    await page.goBack()
    await rows(page)
      .filter({ hasText: text(testInfo, 'Ehsan', 'إحسان') })
      .locator('.cycle-table__link')
      .click()
    await page.locator('.secretary-discussions .btn--secondary').click()
    await page.locator('.modal__panel button[type="submit"]').click()
    await expect(page.locator('.secretary-discussions')).toContainText(
      text(testInfo, 'Closed', 'مغلق'),
    )
    await page
      .locator('.filtering-actions button', {
        hasText: text(testInfo, 'Return to the committee', 'إعادة إلى اللجنة'),
      })
      .click()
    await page.locator('.modal__panel textarea[name="generalNote"]').fill(RETURN_NOTE)
    await page.locator('.modal__panel button[type="submit"]').click()
    await expect(page.locator('.secretary-facts')).toContainText(
      text(testInfo, 'Returned to the committee', 'أُعيد إلى اللجنة'),
    )
    expect(errors).toEqual([])
  })
})

test.describe('Results and announcement', () => {
  test('ranks and approves the last region, approves the national result and announces it', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.secretary, 'results')
    await page.goto('/admin/award/results')
    await page.locator('.cycle-table__link').first().click()
    const riyadh = page
      .locator('.region-results')
      .filter({ hasText: text(testInfo, 'Riyadh', 'الرياض') })
    await chooseValue(riyadh.locator('button[role="combobox"]'), '1')
    await expect(riyadh.locator('.region-results__actions .btn--primary')).toBeEnabled()
    await expect(riyadh).toContainText(text(testInfo, '1 of 1 ranked', 'رُتّب 1 من 1'))
    await shot(page, 'results-regional', testInfo)
    await riyadh.locator('.region-results__actions .btn--primary').click()
    await page.locator('.modal .btn--primary').click()
    await expect(riyadh).toContainText(text(testInfo, 'Approved', 'معتمدة'))
    await page.locator('.results-tabs__link').nth(1).click()
    await expect(rows(page)).toHaveCount(3)
    await chooseValue(rows(page).first().locator('button[role="combobox"]'), '1')
    await expect(page.locator('.region-results__actions .btn--primary')).toBeEnabled()
    await chooseValue(rows(page).nth(1).locator('button[role="combobox"]'), '2')
    await expect(page.locator('.region-results__actions .btn--primary')).toBeEnabled()
    await page.locator('.region-results__actions .btn--primary').click()
    await page.locator('.modal .btn--primary').click()
    await expect(page.locator('.announcement-card .btn--primary')).toBeEnabled()
    await page.locator('.announcement-card .btn--primary').click()
    await page.locator('.modal .btn--primary').click()
    await expect(page.locator('.announcement-card')).toContainText(
      text(testInfo, 'The results were announced on', 'أُعلنت النتائج بتاريخ'),
    )
    await shot(page, 'results-national-announced', testInfo)
    expect(errors).toEqual([])
  })

  test('the entity sees its result after the announcement without the committee average', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.ruwad, 'announced')
    await page.goto('/assessment/advanced')
    await page
      .locator('.advanced-request__link', {
        hasText: text(testInfo, 'View the award result', 'عرض نتيجة الجائزة'),
      })
      .click()
    await expect(page).toHaveURL(/\/assessment\/advanced\/result\/9204$/)
    await expect(page.locator('.award-outcome__title')).toHaveText(
      text(testInfo, 'National award winner', 'فائز بالجائزة على مستوى المملكة'),
    )
    await expect(page.locator('.award-outcome')).toContainText(text(testInfo, 'First', 'الأول'))
    await expect(page.locator('.award-outcome__note')).toBeVisible()
    await shot(page, 'award-entity-result', testInfo)
    expect(errors).toEqual([])
  })
})

test.describe('Users, settings and criteria alignment', () => {
  test('the System Administrator suspends a staff account with a reason', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.systemAdmin)
    await page.goto('/admin/settings/users')
    await page.locator('.toolbar__search input').fill('munira')
    await expect(rows(page)).toHaveCount(1)
    const row = rows(page).filter({ hasText: 'munira.alotaibi' })
    await row.locator('.dropdown-menu > button').click()
    await page.getByRole('menuitem', { name: text(testInfo, 'Suspend', 'إيقاف') }).click()
    await page.locator('.modal__panel textarea').fill(SUSPEND_REASON)
    await page.locator('.modal__panel button[type="submit"]').click()
    await expect(row).toContainText(text(testInfo, 'Suspended', 'موقوف'))
    await shot(page, 'users-suspended', testInfo)
    expect(errors).toEqual([])
  })

  test('the assessment settings carry the award cycle defaults', async ({ page }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, USERS.systemAdmin)
    await page.goto('/admin/settings/assessment?tab=general')
    await expect(page.locator('input[name="advancedDisqualificationScore"]')).toHaveValue('60.00')
    await page.locator('input[name="advancedDisqualificationScore"]').fill('65.5')
    await chooseNamed(page, 'committeeEntityDataVisibility', 'code_only')
    await page.locator('#assessment-settings-form button[type="submit"]').click()
    await expect(page.locator('input[name="advancedDisqualificationScore"]')).toHaveValue('65.50')
    await shot(page, 'settings-award-defaults', testInfo)
    expect(errors).toEqual([])
  })

  test('the criteria editor explains that the running cycle keeps its version and offers keep or move', async ({
    page,
  }, testInfo) => {
    const errors = trackConsole(page)
    await signIn(page, testInfo, { id: 3, roles: ['criteria_officer'] })
    await page.goto('/admin/settings/criteria')
    await page.locator('.criteria-version-panel .btn--primary').first().click()
    await expect(page.locator('.cycle-bindings')).toContainText(
      text(testInfo, 'stays on its criteria version', 'تبقى دورة الجائزة الجارية'),
    )
    await page
      .locator('.criteria-version-panel button', { hasText: text(testInfo, 'Publish', 'نشر') })
      .first()
      .click()
    const dialog = page.locator('.modal__panel')
    await expect(dialog.locator('.cycle-bindings__option')).toHaveCount(2)
    await expect(dialog.locator('.cycle-bindings__option.is-selected')).toContainText(
      text(testInfo, 'Keep them on their current version', 'إبقاؤها على إصدارها الحالي'),
    )
    await shot(page, 'criteria-publish-cycles', testInfo)
    expect(errors).toEqual([])
  })
})
