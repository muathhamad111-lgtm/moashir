import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

// Internal review notes (operator 2026-11-22): staff write a note on a criterion, a sub-criterion or
// an indicator during the review; the entity never sees them.
const ASSESSOR = { id: 10, roles: ['assessor'] }
const FOLLOWER = { id: 8, roles: ['request_follower', 'filtering_officer'] }
const REQUEST = 9108

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function trackErrors(page) {
  const errors = []
  page.on('pageerror', (error) => errors.push(error.message))
  return errors
}

async function openReview(page, testInfo, user) {
  await seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto(`/admin/requests/${REQUEST}/review`)
  await expect(page.locator('.review-workspace .review-criterion')).toBeVisible()
}

test('the assessor writes internal notes on a sub-criterion and an indicator and saves them with the scores', async ({
  page,
}, testInfo) => {
  const errors = trackErrors(page)
  await openReview(page, testInfo, ASSESSOR)
  const firstSub = page.locator('.review-sub').first()
  await firstSub.locator('.review-card__body > .review-note .review-note__toggle').click()
  await firstSub
    .locator('.review-card__body > .review-note textarea')
    .fill('مراجعة محضر مجلس الإدارة لعام 2024 قبل اعتماد الدرجة.')
  const firstIndicator = firstSub.locator('.review-item').first()
  await firstIndicator.locator('.review-note__toggle').click()
  await firstIndicator.locator('.review-note textarea').fill('الرابط المرفق لا يفتح.')
  await page.locator('[data-testid="review-save"]').click()
  await expect(page.locator('.toast--success').first()).toBeVisible()

  // Back on the request page and into the review again: the notes are there.
  await page
    .getByRole('link', { name: isEn(testInfo) ? 'Back to the request' : 'العودة إلى الطلب' })
    .click()
  await page.locator('[data-testid="request-open-review"]').click()
  await expect(firstSub.locator('.review-card__body > .review-note textarea')).toHaveValue(
    'مراجعة محضر مجلس الإدارة لعام 2024 قبل اعتماد الدرجة.',
  )
  await expect(firstIndicator.locator('.review-note textarea')).toHaveValue(
    'الرابط المرفق لا يفتح.',
  )
  await page.screenshot({
    path: `test-results/review-notes-assessor-${isEn(testInfo) ? 'en-ltr' : 'ar-rtl'}.png`,
  })
  expect(errors).toEqual([])
})

test('the follower reads the review and saves their own note on the criterion from the header', async ({
  page,
}, testInfo) => {
  const errors = trackErrors(page)
  await openReview(page, testInfo, FOLLOWER)
  const save = page.locator('[data-testid="review-notes-save"]')
  await expect(save).toBeDisabled()
  await page.locator('.review-criterion > .review-note .review-note__toggle').click()
  await page
    .locator('.review-criterion > .review-note textarea')
    .fill('متابعة: تأخر المقيّم يومين.')
  await save.click()
  await expect(save).toBeDisabled()
  await expect(page.locator('.review-criterion > .review-note textarea')).toHaveValue(
    'متابعة: تأخر المقيّم يومين.',
  )
  expect(errors).toEqual([])
})
