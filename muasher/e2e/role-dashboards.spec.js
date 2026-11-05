import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

function locale(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function seedRoles(page, testInfo, roles, id = 5001) {
  return seedSignedIn(
    page,
    {
      id,
      roles,
      entityId: roles.includes('entity') ? 5001 : null,
      name: { ar: 'سارة بنت عبدالله', en: 'Sarah Abdullah' },
    },
    { locale: locale(testInfo) },
  )
}

test('a multi-role account lands on the highest board and keeps the back office', async ({
  page,
}, testInfo) => {
  await seedRoles(page, testInfo, ['entity', 'assessor', 'system_admin'])
  await page.goto('/dashboard')
  await expect(page.locator('[data-board="system_admin"]')).toBeVisible()
  await expect(page.locator('.dashboard-board-nav__link')).toHaveCount(4)
  await expect(page.locator('.journey-home')).toHaveCount(0)
  await expect(page.locator('.sidebar')).toBeVisible()
})

test('an entity-only account gets the journey home instead of a staff board', async ({
  page,
}, testInfo) => {
  await seedRoles(page, testInfo, ['entity'])
  await page.goto('/dashboard')
  await expect(page.locator('.journey-home')).toBeVisible()
  await expect(page.locator('.staff-dashboard')).toHaveCount(0)
})

test('an unknown board in the link falls back to the landing board', async ({ page }, testInfo) => {
  await seedRoles(page, testInfo, ['finance_officer'], 41)
  await page.goto('/dashboard?board=system_admin')
  await expect(page.locator('[data-board="finance_officer"]')).toBeVisible()
  await expect(page).not.toHaveURL(/board=system_admin/)
})
