// The assign / reassign dialog picks the assessor from one searchable select (admin review 2026-11-20).
export function assessorOptions(page) {
  return page.locator('[role="listbox"] [role="option"]')
}

export async function openAssessorList(page, dialog) {
  await dialog.locator('.assign-dialog__assessor input[role="combobox"]').click()
  await assessorOptions(page).first().waitFor()
}

export async function chooseAssessor(page, dialog, index = 0) {
  await openAssessorList(page, dialog)
  await assessorOptions(page).nth(index).click()
}
