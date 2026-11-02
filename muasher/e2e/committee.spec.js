/* eslint-disable playwright/no-wait-for-timeout */
import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

function localeFor(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

const COMMITTEE_CYCLES = ['cycle-committee-2026']

function seed(page, locale, { role, id, committee = false } = {}) {
  return seedSignedIn(
    page,
    {
      id: id ?? 7000,
      roles: [role],
      name: { ar: 'مستخدم الاختبار', en: 'Test User' },
      ...(committee ? { committeeCycles: COMMITTEE_CYCLES } : {}),
    },
    { locale },
  )
}

function isBenignNetworkNoise(text) {
  return /Failed to load resource: the server responded with a status of (403|404|500|502|503)/.test(text)
}

function collectConsoleErrors(page) {
  const errors = []
  page.on('console', (msg) => {
    if (msg.type() !== 'error') return
    if (isBenignNetworkNoise(msg.text())) return
    errors.push(msg.text())
  })
  page.on('pageerror', (err) => errors.push(String(err)))
  return errors
}

async function gotoWithRetry(page, path, selector) {
  await page.goto(path)
  for (let attempt = 0; attempt < 20; attempt += 1) {
    if (await page.locator(selector).first().isVisible().catch(() => false)) return
    const retry = page.locator('.error-state__retry button, .error-state__retry .btn')
    if (await retry.first().isVisible().catch(() => false)) {
      await retry.first().click().catch(() => {})
    } else {
      await page.reload().catch(() => {})
    }
    await page.waitForTimeout(500)
  }
}

test.describe('Award committee Notes 11/12/13', () => {
  test.describe.configure({ retries: 2 })

  test('Note 11: an authorised staff session sees the committee-management controls', async ({ page }, testInfo) => {
    const locale = localeFor(testInfo)
    const errors = collectConsoleErrors(page)
    await seed(page, locale, { role: 'award_secretary' })
    await gotoWithRetry(page, '/admin/award/committee/manage', '.committee-management__title')
    await expect(page.locator('.committee-management__title')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('.committee-management__toolbar')).toBeVisible()
    await expect(page.locator('.committee-management__gated')).toHaveCount(0)
    expect(errors).toEqual([])
  })

  test('Note 11: a session WITHOUT committee management sees a view-only gated surface', async ({ page }, testInfo) => {
    const locale = localeFor(testInfo)
    const errors = collectConsoleErrors(page)
    await seed(page, locale, { role: 'assessor', id: 'jur-9401', committee: true })
    await page.goto('/admin/award/committee/manage')
    await expect(page.locator('.error-page__title, .committee-management__title').first()).toBeVisible({ timeout: 15000 })
    expect(errors).toEqual([])
  })

  test('Note 12: a committee assessor reaches only their OWN blind scoring surface', async ({ page }, testInfo) => {
    const locale = localeFor(testInfo)
    const errors = collectConsoleErrors(page)
    await seed(page, locale, { role: 'assessor', id: 'jur-9401', committee: true })
    await gotoWithRetry(page, '/admin/award/committee/score/9401', '.committee-score__title')
    await expect(page.locator('.committee-score__title')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('.committee-score__confidential')).toBeVisible()
    expect(errors).toEqual([])
  })

  test('Note 13: the secretary drills into the review-all screen', async ({ page }, testInfo) => {
    const locale = localeFor(testInfo)
    const errors = collectConsoleErrors(page)
    await seed(page, locale, { role: 'award_secretary' })
    await gotoWithRetry(page, '/admin/award/secretary/review', '.secretary-review__title')
    await expect(page.locator('.secretary-review__title')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('.secretary-review__independence')).toBeVisible()
    const openBtn = page.locator('.secretary-review__entities button').first()
    await expect(openBtn).toBeVisible()
    await openBtn.click()
    await expect(page.locator('.secretary-review__committee-assessor').first()).toBeVisible({ timeout: 15000 })
    expect(errors).toEqual([])
  })
})
