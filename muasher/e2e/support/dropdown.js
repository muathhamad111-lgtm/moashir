export function namedSelect(scope, name) {
  return scope.locator(`button[role="combobox"][name="${name}"]`)
}

export function openOption(page, value) {
  return page.locator(`[role="listbox"] [role="option"][data-value="${value}"]`)
}

export async function chooseValue(trigger, value) {
  await trigger.click()
  await openOption(trigger.page(), value).click()
}

export async function chooseNamed(scope, name, value) {
  await chooseValue(namedSelect(scope, name), value)
}

export async function chooseFirstNamed(scope, name) {
  const trigger = namedSelect(scope, name)
  await trigger.click()
  await trigger.page().locator('[role="listbox"] [role="option"]').first().click()
}
