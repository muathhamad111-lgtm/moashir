import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

const ENTITY = { id: 5001, roles: ['entity'], entityId: 5001, name: 'أحمد بن سالم القحطاني' }
const SYSTEM_ADMIN = {
  id: 2,
  roles: ['system_admin'],
  name: { ar: 'منيرة بنت خالد العتيبي', en: 'Munira Al-Otaibi' },
}
const FINANCE = { id: 31, roles: ['finance_officer'], name: 'Finance Officer' }

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function seed(page, testInfo, user) {
  return seedSignedIn(page, user, { locale: isEn(testInfo) ? 'en' : 'ar' })
}

const historyRows = (page) => page.locator('.profile-history tbody tr')

test('the entity sees its own change history without staff names', async ({ page }, testInfo) => {
  await seed(page, testInfo, ENTITY)
  await page.goto('/entity/profile')
  await page.getByTestId('profile-history-card').locator('button').click()
  const drawer = page.getByTestId('profile-history-drawer')
  await expect(drawer.getByTestId('profile-history-entry')).toHaveCount(4)
  await expect(drawer.getByTestId('profile-history-entry').nth(1)).toContainText(
    /Platform staff|موظف المنصة/,
  )
  await expect(drawer).not.toContainText('منيرة')
  await page.screenshot({
    path: `test-results/entity-history-own-${isEn(testInfo) ? 'en-ltr' : 'ar-rtl'}.png`,
    fullPage: true,
  })
})

test('staff readers see the staff name with the first role, or the staff label when unknown', async ({
  page,
}, testInfo) => {
  await seed(page, testInfo, SYSTEM_ADMIN)
  await page.goto('/admin/entities/5001')
  await expect(historyRows(page)).toHaveCount(4)
  await expect(historyRows(page).nth(2)).toContainText(
    /منيرة بنت خالد العتيبي — (System Administrator|مدير النظام)/,
  )
  await expect(historyRows(page).nth(1)).toContainText(/Staff member|موظف/)
  await expect(historyRows(page).nth(0)).toContainText(/System|النظام/)
  await page.locator('.profile-history').scrollIntoViewIfNeeded()
  await page.screenshot({
    path: `test-results/entity-history-staff-${isEn(testInfo) ? 'en-ltr' : 'ar-rtl'}.png`,
    fullPage: true,
  })
})

test('the contact-only finance view has no change history', async ({ page }, testInfo) => {
  await seed(page, testInfo, FINANCE)
  await page.goto('/admin/entities/5001')
  await expect(page.locator('.entity-record__title')).toBeVisible()
  await expect(page.locator('.profile-history')).toHaveCount(0)
})
