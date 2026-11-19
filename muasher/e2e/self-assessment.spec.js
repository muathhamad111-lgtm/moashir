import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import { pinMockClock } from './support/clock'
import { clearMockFault, forceMockFault } from './support/mockFaults'
import { closeSuccess } from './support/dialogs'

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

async function fillEmptyScoresOnStep(page, value) {
  const inputs = page.locator('.self-main .raw-score input')
  const fields = await inputs.count()
  for (let field = 0; field < fields; field += 1) {
    const input = inputs.nth(field)
    if ((await input.inputValue()) === '') await input.fill(value)
  }
}

async function fillEveryEmptyScore(page, value) {
  const next = page.locator('[data-testid="step-next"]')
  await fillEmptyScoresOnStep(page, value)
  while (await next.isVisible()) {
    await next.click()
    await fillEmptyScoresOnStep(page, value)
  }
}

async function goToLastStep(page) {
  const next = page.locator('[data-testid="step-next"]')
  while (await next.isVisible()) await next.click()
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

  await expect(page.locator('[data-testid="step-previous"]')).toHaveCount(0)
  await page.locator('[data-testid="step-next"]').click()
  await expect(page.locator('.step-counter')).toContainText(
    isEn(testInfo) ? 'Criterion 2' : 'المعيار 2',
  )
  await expect(page.locator('.self-main__title')).toBeFocused()
  await expect(page.locator('[data-testid="step-previous"]')).toBeVisible()
  const empty = page.locator('.self-main .raw-score input').first()
  await expect(empty).toHaveValue('')
  const [decrease, increase] = await page
    .locator('.self-main .raw-score')
    .first()
    .locator('.score-stepper__button')
    .all()
  await expect(decrease).toBeDisabled()
  await increase.click()
  await expect(empty).toHaveValue('1')
  await empty.press('ArrowUp')
  await expect(empty).toHaveValue('2')
  await empty.fill('')
  await empty.pressSequentially('٧٫٥تا')
  await expect(empty).toHaveValue('7.5')
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
  await goToLastStep(page)
  await page.locator('[data-testid="self-submit"]').click()
  await expect(page.locator('.toast--error').last()).toBeVisible()
  await expect(page.locator('.self-main .field__error').first()).toBeVisible()
  await expect(page.getByRole('alertdialog')).toHaveCount(0)
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
  await page.locator('[data-testid="self-submit"]').click()
  const dialog = page.getByRole('alertdialog')
  await expect(dialog).toBeVisible()
  await expect(dialog.locator('.modal__message')).toHaveText('SLF-2026-000002')
  await page.screenshot({ path: `test-results/self-submit-confirm-${suffixOf(testInfo)}.png` })

  await dialog.locator('.btn--primary').click()
  await expect(page).toHaveURL(/\/assessment\/self\/result\/9002$/)
  await closeSuccess(page)
  await expect(page.locator('.self-result__gauge-value')).toBeVisible()
  await expect(page.locator('.self-result__main')).not.toHaveCount(0)
  const firstMain = page.locator('.self-result__main-head').first()
  await expect(firstMain).toHaveAttribute('aria-expanded', 'false')
  await expect(page.locator('.self-result__point').first()).toBeHidden()
  await firstMain.click()
  await expect(firstMain).toHaveAttribute('aria-expanded', 'true')
  await expect(page.locator('.self-result__point').first()).toBeVisible()
  await page.screenshot({
    path: `test-results/self-result-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })

  await page.locator('.self-result__actions .btn--secondary').click()
  await expect(page).toHaveURL(/\/paths\/self$/)
  await expect(page.locator('[data-testid="path-requests"]')).toBeVisible()
  await expect(page.locator('[data-testid^="path-row-request-"]').first()).toBeVisible()
  await page.screenshot({
    path: `test-results/self-history-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  expect(errors).toEqual([])
})

test('an entity without an open draft starts a new numbered self assessment in one click from its journey', async ({
  page,
}, testInfo) => {
  await seedEntity(page, testInfo, 6002)
  await page.goto('/dashboard')
  const tile = page.locator('[data-testid="path-tile-self"]')
  await expect(tile.locator('[data-testid="path-tile-history-self"]')).toHaveAttribute(
    'href',
    '/paths/self',
  )
  await tile.locator('[data-testid="path-tile-action-self"]').click()
  await expect(page).toHaveURL(/\/assessment\/self$/)
  await expect(page.locator('.self-summary__number')).toContainText('SLF-2026-000006')
  await expect(page.locator('.self-summary__progress-value')).toContainText(/0/)
  await expect(page.locator('.step-counter')).toContainText(
    isEn(testInfo) ? 'Criterion 1' : 'المعيار 1',
  )
  await expect(page.locator('.self-main__code')).toHaveCount(0)
  await page.screenshot({
    path: `test-results/self-start-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
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
  await goToLastStep(page)
  await expect(page.locator('[data-testid="self-submit"]')).toBeDisabled()
  await expect(page.locator('[data-testid="self-save"]')).toBeEnabled()
  await page.screenshot({
    path: `test-results/self-criteria-update-${suffixOf(testInfo)}.png`,
    fullPage: true,
  })
  await page.locator('[data-testid="self-save"]').click()
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
  await page.locator('[data-testid="self-save"]').click()
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
  await page.locator('[data-testid="self-save"]').click()
  await expect(page.locator('.assessment-notice--frozen')).toBeVisible()
  await expect(page.locator('.self-summary .frozen-badge')).toBeVisible()
  await expect(page.locator('.self-main .raw-score input').first()).toBeDisabled()
  await expect(page.locator('[data-testid="self-save"]')).toBeDisabled()
  await goToLastStep(page)
  await expect(page.locator('[data-testid="self-submit"]')).toBeDisabled()
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

test('the assessment asks to extend the session and saves then signs out when nobody answers', async ({
  page,
}, testInfo) => {
  await page.clock.install()
  await seedEntity(page, testInfo)
  await page.goto('/assessment/self')
  await expect(page.locator('.self-summary')).toBeVisible()
  await page.clock.fastForward('02:00:00')
  const dialog = page.getByRole('alertdialog')
  await expect(dialog).toBeVisible()
  await expect(dialog.locator('.session-extend__time')).toHaveText(/1[45]:\d\d/)
  await page.screenshot({ path: `test-results/self-session-extend-${suffixOf(testInfo)}.png` })
  await dialog.locator('.btn--primary').click()
  await expect(dialog).toHaveCount(0)

  await page.locator('[data-testid="step-next"]').click()
  await page.locator('.self-main .raw-score input').first().fill('6')
  await page.clock.fastForward('02:00:00')
  await expect(page.getByRole('alertdialog')).toBeVisible()
  await page.clock.fastForward('00:15:01')
  await expect(page).toHaveURL(/\/auth\/login/)
  await expect(page.locator('.toast--info').last()).toBeVisible()
})
