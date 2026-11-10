import { expect } from '@playwright/test'
import { chooseNamed } from '../../support/dropdown'

export const PRICES_SCREEN = '/admin/settings/prices'
export const AVAILABLE = 'available'

export function priceRow(page, key) {
  return page.locator(`[data-testid="price-row-${key}"]`)
}

function isPriceWrite(response, key) {
  return (
    response.request().method() === 'PUT' &&
    new URL(response.url()).pathname === `/api/v1/settings/prices/${key}`
  )
}

export async function savePathPrice(page, key, { price, availability = AVAILABLE }) {
  const row = priceRow(page, key)
  const input = row.locator('input[name="priceBeforeVat"]')
  await expect(input).toBeEditable()
  await input.fill(price)
  await chooseNamed(row, 'availability', availability)
  const written = page.waitForResponse((response) => isPriceWrite(response, key))
  await row.locator('.path-price-row__actions .btn--primary').click()
  const answer = await written
  expect(answer.status()).toBe(200)
  await expect(page.locator('.toast--success').last()).toBeVisible()
  return (await answer.json()).data
}
