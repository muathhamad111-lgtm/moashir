import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

// AJ-23: the entity no longer holds award.results.view. It still reads its own announced award result (through
// assessment.history.view), cannot read another entity's, and is turned away from the staff results boards.

const ENTITY = {
  id: 7000,
  roles: ['entity'],
  entityId: 5001,
  name: { ar: 'مستخدم الاختبار', en: 'Test User' },
}

test.beforeEach(async ({ page }, testInfo) => {
  const locale = testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
  await seedSignedIn(page, ENTITY, { locale })
})

test('the entity opens its own announced award result', async ({ page }) => {
  const answer = page.waitForResponse((response) =>
    response.url().endsWith('/api/v1/assessments/9207/result'),
  )
  await page.goto('/assessment/advanced/result/9207')
  expect((await answer).status()).toBe(200)
  await expect(page).toHaveURL(/\/assessment\/advanced\/result\/9207$/)
  await expect(page.locator('.accredited-result__title')).toBeVisible()
})

test("the entity cannot read another entity's award result", async ({ page }) => {
  const answer = page.waitForResponse((response) =>
    response.url().endsWith('/api/v1/assessments/9204/result'),
  )
  await page.goto('/assessment/advanced/result/9204')
  expect((await answer).status()).toBe(404)
  await expect(page.locator('.empty-state')).toBeVisible()
})

test('the entity is turned away from the staff results boards', async ({ page }) => {
  for (const path of [
    '/admin/award/results',
    '/admin/award/cycles/31/results',
    '/admin/award/cycles/31/results/national',
  ]) {
    await page.goto(path)
    await expect(page).toHaveURL(/\/403$/)
  }
})
