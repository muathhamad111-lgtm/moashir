/* eslint-disable playwright/no-wait-for-timeout */
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function suffixFor(testInfo) {
  return isEn(testInfo) ? 'en-ltr' : 'ar-rtl'
}

function seedRole(page, roles, locale, { committee = false } = {}) {
  return seedSignedIn(
    page,
    {
      id: 5001,
      roles,
      entityId: roles.includes('entity') ? 5001 : null,
      name: { ar: 'سارة بنت عبدالله', en: 'Sarah Abdullah' },
      ...(committee ? { committeeCycles: ['cycle-committee-2026'] } : {}),
    },
    { locale },
  )
}

async function gotoDashboard(page, marker) {
  await page.goto('/dashboard')
  for (let attempt = 0; attempt < 16; attempt += 1) {
    if (
      await page
        .locator(marker)
        .first()
        .isVisible()
        .catch(() => false)
    )
      return
    const retry = page.locator('.error-state__retry')
    if (
      await retry
        .first()
        .isVisible()
        .catch(() => false)
    )
      await retry.first().click()
    await page.waitForTimeout(500)
  }
}

const ROLES = [
  { key: 'admin', roles: ['system_admin'], marker: '#admin-summary' },
  { key: 'follower', roles: ['request_follower'], marker: '#follower-requests' },
  { key: 'assessor', roles: ['assessor'], marker: '#assessor-tasks' },
  {
    key: 'committee-assessor',
    roles: ['assessor'],
    committee: true,
    marker: '#committee-assessor-stats',
  },
  { key: 'secretary', roles: ['award_secretary'], marker: '#secretary-summary' },
  { key: 'entity', roles: ['entity'], marker: '#entity-status' },
  { key: 'finance', roles: ['finance_officer'], marker: '#finance-summary' },
  { key: 'content', roles: ['content_officer'], marker: '#content-summary' },
]

for (const role of ROLES) {
  test(`${role.key} dashboard renders its Action Center + cards`, async ({ page }, testInfo) => {
    const errors = []
    page.on('console', (msg) => {
      if (msg.type() !== 'error') return
      const text = msg.text()
      if (/Failed to load resource|50\d|Service Unavailable/i.test(text)) return
      errors.push(text)
    })
    await seedRole(page, role.roles, isEn(testInfo) ? 'en' : 'ar', { committee: role.committee })
    await gotoDashboard(page, role.marker)

    await expect(page.locator('.action-center__title')).toBeVisible()
    await expect(page.locator('.action-center__item').first()).toBeVisible()
    await expect(page.locator(role.marker)).toBeVisible()

    await page.screenshot({
      path: `e2e/__screenshots__/dashboard-${role.key}-${suffixFor(testInfo)}.png`,
      fullPage: true,
    })

    await page.waitForTimeout(800)
    expect(errors).toEqual([])
  })
}

test('multi-role user gets the highest-privilege (admin) dashboard', async ({ page }, testInfo) => {
  await seedRole(page, ['entity', 'assessor', 'system_admin'], isEn(testInfo) ? 'en' : 'ar')
  await gotoDashboard(page, '#admin-summary')
  await expect(page.locator('#admin-summary')).toBeVisible()
  await expect(page.locator('#entity-status')).toHaveCount(0)
})
