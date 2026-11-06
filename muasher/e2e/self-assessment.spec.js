import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { pinMockClock } from './support/clock'
import { clearMockFault, forceMockFault } from './support/mockFaults'

const IGNORED_CONSOLE = /Failed to load resource|40\d|50\d/i

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixOf(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function localeOf(testInfo) {
  return isEn(testInfo) ? 'en' : 'ar'
}

const AL_BIRR_HISTORY = [
  'SLF-2026-000002',
  'SLF-2026-000001',
  'ACC-2026-000001',
  'ACC-2025-000012',
  'ADV-2025-000001',
]

function seedEntity(page, testInfo, entityId = 5001) {
  return seedSignedIn(
    page,
    {
      id: entityId,
      roles: ['entity'],
      entityId,
      name: { ar: 'أحمد بن سالم القحطاني', en: 'Ahmed Salem Al-Qahtani' },
    },
    { locale: localeOf(testInfo) },
  )
}

function seedStaff(page, testInfo, roles = ['system_admin']) {
  return seedSignedIn(
    page,
    { id: 2, roles, name: { ar: 'منيرة بنت خالد العتيبي', en: 'Munira Khalid Al-Otaibi' } },
    { locale: localeOf(testInfo) },
  )
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

async function fillEveryEmptyScore(page, value) {
  const mains = page.locator('.criteria-tree__open')
  const count = await mains.count()
  for (let index = 0; index < count; index += 1) {
    await mains.nth(index).click()
    const inputs = page.locator('.self-main .raw-score input')
    const fields = await inputs.count()
    for (let field = 0; field < fields; field += 1) {
      const input = inputs.nth(field)
      if ((await input.inputValue()) === '') await input.fill(value)
    }
  }
}

test('the entity resumes its open draft, saves raw scores and sees the percent returned by the server', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedEntity(page, testInfo)
  await page.goto('/assessment/self')
  await expect(page.locator('.self-summary__number')).toContainText('SLF-2026-000002')
  await expect(page.locator('.self-summary__progress-value')).toContainText(/6/)
  await page.screenshot({
    path: `test-results/self-draft-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await page.locator('.criteria-tree__open').nth(1).click()
  const empty = page.locator('.self-main .raw-score input').first()
  await expect(empty).toHaveValue('')
  await empty.fill('7.5')
  await expect(page.locator('.self-actions__hint')).toBeVisible()
  await page.locator('.self-actions .btn--secondary').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await expect(page.locator('.self-summary__progress-value')).toContainText(/7/)
  await expect(page.locator('.self-main .raw-score').first().locator('.field__hint')).toContainText(
    '75.00%',
  )
  await expect(empty).toHaveValue('7.50')
  expect(errors).toEqual([])
})

test('an incomplete submission points to the first item without a score', async ({
  page,
}, testInfo) => {
  await seedEntity(page, testInfo)
  await page.goto('/assessment/self')
  await expect(page.locator('.self-summary')).toBeVisible()
  await page.locator('.self-actions .btn--primary').click()
  await expect(page.locator('.toast--error').last()).toBeVisible()
  await expect(page.locator('.self-main .field__error').first()).toBeVisible()
  await expect(page.getByRole('dialog')).toHaveCount(0)
})

test('the entity completes the draft, confirms and sees the result immediately, then its history', async ({
  page,
}, testInfo) => {
  test.setTimeout(60000)
  const errors = trackConsole(page)
  await pinMockClock(page)
  await seedEntity(page, testInfo)
  await page.goto('/assessment/self')
  await expect(page.locator('.self-summary')).toBeVisible()
  await fillEveryEmptyScore(page, '8')
  await page.locator('.self-actions .btn--primary').click()
  const dialog = page.getByRole('dialog')
  await expect(dialog).toBeVisible()
  await expect(dialog.locator('.self-submit__lead')).toContainText('SLF-2026-000002')
  await page.screenshot({ path: `test-results/self-submit-confirm-${suffixOf(testInfo)}.png` })

  await dialog.locator('.btn--primary').click()
  await expect(page).toHaveURL(/\/assessment\/self\/result\/9002$/)
  await expect(page.locator('.self-result__gauge-value')).toBeVisible()
  await expect(page.locator('.self-result__main')).not.toHaveCount(0)
  await expect(page.locator('.self-result__point').first()).toBeVisible()
  await page.screenshot({
    path: `test-results/self-result-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await page.locator('.self-result__actions .btn--secondary').click()
  await expect(page).toHaveURL(/\/assessment\/history$/)
  await page.locator('[role="tab"]').nth(1).click()
  await expect(page.locator('.history-item')).toHaveCount(AL_BIRR_HISTORY.length)
  expect((await page.locator('.history-item__number').allInnerTexts()).toSorted()).toEqual(
    AL_BIRR_HISTORY.toSorted(),
  )
  await page.locator('.history-item').first().locator('.status-history__toggle').click()
  await expect(
    page.locator('.history-item').first().locator('.assessment-timeline__step'),
  ).toHaveCount(3)
  await expect(
    page.locator('.history-item').first().locator('.assessment-timeline__actor').first(),
  ).toHaveText(isEn(testInfo) ? 'The entity' : 'الجهة')
  await page.screenshot({
    path: `test-results/self-history-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  expect(errors).toEqual([])
})

test('an entity without an open draft starts a new numbered self assessment', async ({
  page,
}, testInfo) => {
  await seedEntity(page, testInfo, 6002)
  await page.goto('/assessment/self')
  const start = page.locator('.self-start')
  await expect(start).toBeVisible()
  await expect(start.locator('.self-start__facts li')).toHaveCount(3)
  await page.screenshot({
    path: `test-results/self-start-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await start.locator('.btn--primary').click()
  await expect(page.locator('.self-summary__number')).toContainText('SLF-2026-000006')
  await expect(page.locator('.self-summary__progress-value')).toContainText(/0/)
})

test('criteria update in progress blocks submission with the translated message but keeps saving', async ({
  page,
}, testInfo) => {
  await seedEntity(page, testInfo, 7005)
  await page.goto('/assessment/self')
  const notice = page.locator('.assessment-notice--warning')
  await expect(notice).toBeVisible()
  await expect(notice).toContainText(
    isEn(testInfo) ? 'Criteria update in progress' : 'جارٍ تحديث المعايير',
  )
  await expect(page.locator('.self-actions .btn--primary')).toBeDisabled()
  await expect(page.locator('.self-actions .btn--secondary')).toBeEnabled()
  await page.screenshot({
    path: `test-results/self-criteria-update-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await page.locator('.self-actions .btn--secondary').click()
  await expect(page.locator('.toast--success').last()).toBeVisible()
  await notice.locator('.btn').click()
  await expect(notice).toBeVisible()
})

test('a state conflict on save offers a refresh path', async ({ page }, testInfo) => {
  await seedEntity(page, testInfo)
  await page.goto('/assessment/self')
  await expect(page.locator('.self-summary')).toBeVisible()
  await forceMockFault(page, {
    method: 'PUT',
    path: '/api/v1/assessments/9002',
    status: 409,
    code: 'state.transition_illegal',
  })
  await page.locator('.self-actions .btn--secondary').click()
  const conflict = page.locator('.assessment-notice--danger')
  await expect(conflict).toBeVisible()
  await clearMockFault(page)
  await conflict.locator('.btn').click()
  await expect(conflict).toHaveCount(0)
  await expect(page.locator('.self-summary')).toBeVisible()
})

test('a frozen answer while saving turns the draft read-only', async ({ page }, testInfo) => {
  await seedEntity(page, testInfo)
  await page.goto('/assessment/self')
  await expect(page.locator('.self-summary')).toBeVisible()
  await forceMockFault(page, {
    method: 'PUT',
    path: '/api/v1/assessments/9002',
    status: 409,
    code: 'entity.deleted',
  })
  await page.locator('.self-actions .btn--secondary').click()
  await expect(page.locator('.assessment-notice--frozen')).toBeVisible()
  await expect(page.locator('.self-summary .frozen-badge')).toBeVisible()
  await expect(page.locator('.self-main .raw-score input').first()).toBeDisabled()
  await expect(page.locator('.self-actions .btn--primary')).toBeDisabled()
})

test('the result of an open draft shows the not-issued placeholder with its status', async ({
  page,
}, testInfo) => {
  await seedEntity(page, testInfo)
  await page.goto('/assessment/self/result/9002')
  await expect(page.locator('.result-pending')).toBeVisible()
  await expect(page.locator('.result-pending .badge')).toBeVisible()
  await page.locator('.result-pending .btn--primary').click()
  await expect(page).toHaveURL(/\/assessment\/self$/)
})

test('staff read a frozen request read-only from the entity record', async ({ page }, testInfo) => {
  const errors = trackConsole(page)
  await seedStaff(page, testInfo)
  await page.goto('/admin/entities/7002')
  const card = page.locator('.entity-assessments')
  await expect(card.locator('.entity-assessments__item')).toHaveCount(1)
  await expect(card.locator('.frozen-badge')).toBeVisible()
  await card.locator('.entity-assessments__link').click()
  await expect(page).toHaveURL(/\/admin\/assessments\/9004$/)
  await expect(page.locator('.assessment-notice--frozen')).toBeVisible()
  await expect(page.locator('.assessment-record__badges .frozen-badge')).toBeVisible()
  await expect(page.locator('.result-pending')).toBeVisible()
  await page.screenshot({
    path: `test-results/self-frozen-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  expect(errors).toEqual([])
})

test('staff read a submitted result without any write action', async ({ page }, testInfo) => {
  await seedStaff(page, testInfo, ['request_follower'])
  await page.goto('/admin/assessments/9003')
  await expect(page.locator('.self-result__gauge-value')).toBeVisible()
  await expect(page.locator('.assessment-notice--info')).toBeVisible()
  await expect(page.locator('.self-actions')).toHaveCount(0)
  await expect(page.locator('.assessment-record input')).toHaveCount(0)
})
