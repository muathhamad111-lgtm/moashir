import { expect } from '@playwright/test'
import { mailMark, waitForCode } from './mailbox'
import { typeCode } from './accounts'

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
  await page.locator('[name="entityType"]').selectOption({ index: 1 })
  await page.locator('[name="developmentSector"]').selectOption({ index: 1 })
  await page.locator('[name="orgSize"]').selectOption({ index: 1 })
  await page.locator('[name="foundingYear"]').fill('2012')
  await page.locator('[name="regionId"]').selectOption({ index: 1 })
  await expect(page.locator('[name="cityId"]')).toBeEnabled()
  await page.locator('[name="cityId"]').selectOption({ index: 1 })
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
