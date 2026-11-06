import { expect } from '@playwright/test'
import { mailMark, waitForCode } from './mailbox'
import { typeCode } from './accounts'
import { chooseFirstNamed } from '../../support/dropdown'

let sequence = 0

export function nextUnifiedNumber() {
  sequence += 1
  return `7${String((Date.now() + sequence) % 1e9).padStart(9, '0')}`
}

export async function registerEntity(page, entity) {
  const unifiedNumber = entity.unifiedNumber ?? nextUnifiedNumber()
  await page.goto('/auth/register')
  await page.locator('[name="officialEmail"]').fill(entity.email)
  await page.locator('[name="unifiedNumber"]').fill(unifiedNumber)
  await page.locator('[name="password"]').fill(entity.password)
  await page.locator('[name="passwordConfirmation"]').fill(entity.password)
  await page.locator('[name="name.ar"]').fill(entity.nameAr)
  await page.locator('[name="name.en"]').fill(entity.nameEn)
  await page.locator('[name="authorizedPersonName"]').fill(entity.person)
  await chooseFirstNamed(page, 'entityType')
  await chooseFirstNamed(page, 'developmentSector')
  await chooseFirstNamed(page, 'orgSize')
  await page.locator('[name="foundingYear"]').fill('2012')
  await chooseFirstNamed(page, 'regionId')
  await expect(page.locator('[name="cityId"]')).toBeEnabled()
  await chooseFirstNamed(page, 'cityId')
  await page.locator('[name="governanceGrade"]').fill('88.5')
  await page.locator('label.checkbox:has([name="termsAccepted"])').click()
  const mark = mailMark(entity.email)
  await page.locator('form button[type="submit"]').click()
  await expect(page).toHaveURL(/\/auth\/otp/)
  await typeCode(page, await waitForCode(entity.email, mark))
  await expect(page).toHaveURL(/\/entity\/profile/)
  await expect(page.locator('.entity-profile-hub .portal-area__title')).toContainText(entity.nameAr)
  return { unifiedNumber }
}
