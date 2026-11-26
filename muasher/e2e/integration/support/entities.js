import { expect } from '@playwright/test'
import { mailMark, waitForCode } from './mailbox'
import { typeCode } from './accounts'
import { chooseFirstNamed } from '../../support/dropdown'
import {
  answerGovernanceGrade,
  attachAuthorizationLetter,
  attachRegistrationDocument,
  goToRegistrationAccountStep,
} from '../../support/registration'

let sequence = 0

export function nextUnifiedNumber() {
  sequence += 1
  return `7${String((Date.now() + sequence) % 1e9).padStart(9, '0')}`
}

export async function registerEntity(page, entity) {
  const unifiedNumber = entity.unifiedNumber ?? nextUnifiedNumber()
  await page.goto('/auth/register')
  await chooseFirstNamed(page, 'entityType')
  await page.locator('[name="unifiedNumber"]').fill(unifiedNumber)
  await page.locator('[name="licenseNumber"]').fill(`88${unifiedNumber}`)
  await attachRegistrationDocument(page, 'licenseCertificate')
  await page.locator('[name="name.ar"]').fill(entity.nameAr)
  await page.locator('[name="name.en"]').fill(entity.nameEn)
  await chooseFirstNamed(page, 'regionId')
  await expect(page.locator('[name="cityId"]')).toBeEnabled()
  await chooseFirstNamed(page, 'cityId')
  await chooseFirstNamed(page, 'developmentSector')
  await chooseFirstNamed(page, 'orgSize')
  await page.locator('[name="foundedOn"]').fill('2012-05-14')
  await answerGovernanceGrade(page, '88.5')
  await goToRegistrationAccountStep(page)
  await page.locator('[name="authorizedPersonName"]').fill(entity.person)
  await attachAuthorizationLetter(page)
  await page.locator('[name="officialEmail"]').fill(entity.email)
  await page.locator('[name="password"]').fill(entity.password)
  await page.locator('[name="passwordConfirmation"]').fill(entity.password)
  await page.locator('label.checkbox:has([name="termsAccepted"])').click()
  const mark = mailMark(entity.email)
  await page.locator('form button[type="submit"]').click()
  await expect(page).toHaveURL(/\/auth\/otp/)
  await typeCode(page, await waitForCode(entity.email, mark))
  await expect(page).toHaveURL(/\/entity\/profile/)
  await expect(page.locator('.profile-header__name')).toContainText(entity.nameAr)
  return { unifiedNumber }
}
