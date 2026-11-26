// The request page (admin review 2026-11-20): actions sit at the header's end, the rest in a «⋯» menu,
// and the history moved to its own tab.
export async function chooseRequestAction(page, name) {
  await page.locator('.request-actions [aria-haspopup="menu"]').click()
  await page.getByRole('menuitem', { name }).click()
}

export function requestMenuItems(page) {
  return page.getByRole('menu').getByRole('menuitem')
}

export async function openRequestTab(page, name) {
  await page.locator('.request-detail__tabs [role="tab"]', { hasText: name }).click()
}
