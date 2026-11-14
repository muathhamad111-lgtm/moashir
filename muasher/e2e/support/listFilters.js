import { expect } from '@playwright/test'
import { chooseValue } from './dropdown'

export async function searchList(page, text) {
  const toggle = page.locator('.expandable-search__toggle')
  if (await toggle.isVisible()) await toggle.click()
  await page.locator('.expandable-search__input').fill(text)
}

export async function applyListFilters(page, choices) {
  await page.locator('.filter-dialog__toggle').click()
  const dialog = page.getByRole('dialog')
  await expect(dialog).toBeVisible()
  for (const [testId, value] of Object.entries(choices))
    await chooseValue(dialog.locator(`[data-testid="${testId}"] [role="combobox"]`), value)
  await dialog.getByRole('button', { name: /^(Apply|تطبيق)$/ }).click()
  await expect(dialog).toHaveCount(0)
}
