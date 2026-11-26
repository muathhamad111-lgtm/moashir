import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'
import {
  completionSheet,
  fillCompletionMessage,
  nextCompletionStep,
  scoreAndSaveEveryItem,
} from './support/reviewWork'

// Criteria batch S7: a main criterion's own attachments in the review and the completion request.
const IGNORED_CONSOLE = /Failed to load resource|40\d|50\d/i
const ASSESSOR = { id: 10, roles: ['assessor'] }

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
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

test('the assessor asks the entity for the attachments of a main criterion', async ({
  page,
}, testInfo) => {
  const errors = trackConsole(page)
  await seedSignedIn(page, ASSESSOR, { locale: isEn(testInfo) ? 'en' : 'ar' })
  await page.goto('/admin/requests/9107/review')
  await page.locator('[data-testid="review-start"]').click()
  await scoreAndSaveEveryItem(page)
  // Back on the first criterion, so the picker opens its group first.
  await page.locator('.review-strip__step').first().click()
  await page.locator('[data-testid="review-request-completion"]').click()
  const dialog = completionSheet(page)
  const mainRow = dialog.locator('.completion-picker__row').first()
  await expect(mainRow.locator('th')).toHaveText(
    isEn(testInfo) ? 'Main criterion evidence' : 'شواهد المعيار الرئيسي',
  )
  await expect(mainRow.locator('input')).toHaveCount(1)
  await mainRow.locator('input').check()
  await expect(dialog.locator('.completion-summary')).toContainText(
    isEn(testInfo)
      ? 'Main criterion evidence: Administrative Leadership'
      : 'شواهد المعيار الرئيسي: القيادة الإدارية',
  )
  await nextCompletionStep(page)
  await fillCompletionMessage(page, 'يرجى إرفاق الخطة الاستراتيجية المعتمدة للجهة.')
  await nextCompletionStep(page)
  await dialog.locator('button[type="submit"]').click()
  await expect(page.locator('.review-notices .completion-card')).toContainText(
    isEn(testInfo) ? 'Administrative Leadership' : 'القيادة الإدارية',
  )
  expect(errors).toEqual([])
})
