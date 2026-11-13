import { expect } from '@playwright/test'

export const successDialog = (page) => page.locator('.modal__panel:has(.modal__icon--success)')

export const decisionAction = (page) =>
  page.getByRole('alertdialog').locator('.modal__footer .btn').first()

export async function closeSuccess(page) {
  const dialog = successDialog(page)
  await expect(dialog).toBeVisible()
  await dialog.locator('.modal__footer .btn').click()
  await expect(dialog).toHaveCount(0)
}
