import { expect } from '@playwright/test'
import { chooseNamed } from './dropdown'

const ENTITY_STEP = {
  nameAr: 'جمعية الوفاء الخيرية',
  nameEn: 'Al-Wafa Charity',
  unifiedNumber: '7000000911',
  foundedOn: '2016-03-21',
}

export function registrationNextButton(page) {
  return page.locator('.entity-register__actions button[type="submit"]')
}

export async function fillRegistrationEntityStep(page, overrides = {}) {
  const entity = { ...ENTITY_STEP, ...overrides }
  await chooseNamed(page, 'entityType', 'civil_association')
  await page.locator('[name="unifiedNumber"]').fill(entity.unifiedNumber)
  await page.locator('[name="name.ar"]').fill(entity.nameAr)
  await page.locator('[name="name.en"]').fill(entity.nameEn)
  await chooseNamed(page, 'regionId', '2')
  await chooseNamed(page, 'cityId', '12')
  await chooseNamed(page, 'developmentSector', 'social')
  await chooseNamed(page, 'orgSize', 'medium')
  await page.locator('[name="foundedOn"]').fill(entity.foundedOn)
}

export async function goToRegistrationAccountStep(page) {
  await registrationNextButton(page).click()
  await expect(page.locator('[name="officialEmail"]')).toBeVisible()
}
