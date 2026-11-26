import { expect } from '@playwright/test'

// The assessor's review (design §B.3.1–§B.3.3): every item is scored and saved before a completion
// request can be sent, and the request itself is a side sheet in three steps.

async function scoreOpenCriterion(page, value) {
  const fields = page.locator('.review-main .raw-score input')
  const total = await fields.count()
  for (let field = 0; field < total; field += 1) {
    if (!(await fields.nth(field).inputValue())) await fields.nth(field).fill(value)
  }
  const groups = page.locator('.review-main .review-achievement__options')
  const choices = await groups.count()
  for (let group = 0; group < choices; group += 1) {
    if (!(await groups.nth(group).locator('[aria-checked="true"]').count()))
      await groups.nth(group).locator('[role="radio"]').first().click()
  }
}

export async function scoreAndSaveEveryItem(page, value = '8') {
  const steps = page.locator('.review-strip__step')
  const count = await steps.count()
  for (let index = 0; index < count; index += 1) {
    await steps.nth(index).click()
    await scoreOpenCriterion(page, value)
  }
  await page.locator('[data-testid="review-save"]').click()
  await expect(page.locator('[data-testid="review-request-completion"]')).toBeEnabled()
}

export function completionSheet(page) {
  return page.locator('.modal--drawer')
}

export async function nextCompletionStep(page) {
  const sheet = completionSheet(page)
  const current = await sheet.locator('[aria-current="step"]').textContent()
  await sheet.locator('button[type="submit"]').click()
  await expect(sheet.locator('[aria-current="step"]')).not.toHaveText(current)
}

export async function fillCompletionMessage(page, notes, { outcome = 0 } = {}) {
  const sheet = completionSheet(page)
  await sheet.locator('textarea[name="notes"]').fill(notes)
  await sheet
    .locator('input[name="deadline"]')
    .fill(new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10))
  await sheet.locator('.radio-group input').nth(outcome).check()
}
