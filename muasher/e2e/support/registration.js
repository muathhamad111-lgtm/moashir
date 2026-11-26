import { Buffer } from 'node:buffer'
import { expect } from '@playwright/test'
import { chooseNamed } from './dropdown'

const ENTITY_STEP = {
  licenseNumber: '880000911',
  nameAr: 'جمعية الوفاء الخيرية',
  nameEn: 'Al-Wafa Charity',
  unifiedNumber: '7000000911',
  foundedOn: '2016-03-21',
  governanceGrade: null,
}

const SAMPLE_PDF = Buffer.from(
  '%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n',
)

export async function attachRegistrationDocument(page, name, fileName = `${name}.pdf`) {
  await page
    .locator(`input[type="file"][name="${name}"]`)
    .setInputFiles({ name: fileName, mimeType: 'application/pdf', buffer: SAMPLE_PDF })
}

export function attachAuthorizationLetter(page) {
  return attachRegistrationDocument(page, 'authorizationLetter', 'authorization-letter.pdf')
}

export function registrationNextButton(page) {
  return page.locator('.entity-register__actions button[type="submit"]')
}

// The grade is required unless «no governance grade» is ticked beneath it.
export async function answerGovernanceGrade(page, grade) {
  const noGrade = page.locator('input[name="noGovernanceGrade"]')
  await noGrade.setChecked(!grade)
  if (grade) await page.locator('[name="governanceGrade"]').fill(grade)
}

export async function fillRegistrationEntityStep(page, overrides = {}) {
  const entity = { ...ENTITY_STEP, ...overrides }
  await chooseNamed(page, 'entityType', 'civil_association')
  await page.locator('[name="unifiedNumber"]').fill(entity.unifiedNumber)
  await page.locator('[name="licenseNumber"]').fill(entity.licenseNumber)
  await attachRegistrationDocument(page, 'licenseCertificate', 'license-certificate.pdf')
  await page.locator('[name="name.ar"]').fill(entity.nameAr)
  await page.locator('[name="name.en"]').fill(entity.nameEn)
  await chooseNamed(page, 'regionId', '2')
  await chooseNamed(page, 'cityId', '12')
  await chooseNamed(page, 'developmentSector', 'social')
  await chooseNamed(page, 'orgSize', 'medium')
  await page.locator('[name="foundedOn"]').fill(entity.foundedOn)
  await answerGovernanceGrade(page, entity.governanceGrade)
}

export async function goToRegistrationAccountStep(page) {
  await registrationNextButton(page).click()
  await expect(page.locator('[name="officialEmail"]')).toBeVisible()
}
