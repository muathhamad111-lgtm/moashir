import { mkdirSync } from 'node:fs'
import { test, expect } from '@playwright/test'
import { mockSessionCookie, seedSignedIn } from './support/session'
import { settleFonts } from './support/screenshots'

const SHOTS = '../agents/tasks/reports/Task-70'
const NAMAA = { id: 7601, roles: ['entity'], entityId: 6001, name: 'نورة بنت فهد العتيبي' }
const ADMIN = { id: 9101, roles: ['system_admin'], name: 'مدير النظام' }
const NAMAA_SILVER_CERTIFICATE = '2c9e7a41-0b5d-4f63-8e21-7d4a00009104'
const DIALOG = '.modal:has(.level-achieved__body) [role="dialog"]'
const LEVEL_BADGE = '.account-menu__level .level-badge'

mkdirSync(SHOTS, { recursive: true })

function localeOf(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

function suffixOf(testInfo) {
  return localeOf(testInfo) === 'en' ? 'en-ltr' : 'ar-rtl'
}

function navigateInApp(page, path) {
  return page.evaluate(
    (target) =>
      document.querySelector('#app').__vue_app__.config.globalProperties.$router.push(target),
    path,
  )
}

function waitForSignedInUser(page, id) {
  return page.waitForFunction(
    (expected) =>
      document.querySelector('#app')?.__vue_app__?.config.globalProperties.$pinia._s.get('auth')
        ?.user?.id === expected,
    id,
  )
}

async function reissueAsAdministrator(page, certificateId) {
  await page.context().addCookies([mockSessionCookie(ADMIN)])
  const status = await page.evaluate(async (id) => {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')
    const response = await fetch(`/api/v1/certificates/${id}/reissue`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-XSRF-TOKEN': token,
      },
      body: JSON.stringify({ reason: 'Reissued after the release for the level dialog check' }),
    })
    return response.status
  }, certificateId)
  await page.context().addCookies([mockSessionCookie(NAMAA)])
  return status
}

test.describe('level-achieved dialog — release cut-off', () => {
  test.beforeEach(async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.setViewportSize({ width: 1440, height: 900 })
  })

  test('a certificate issued before the release shows no dialog', async ({ page }, testInfo) => {
    await seedSignedIn(page, NAMAA, { locale: localeOf(testInfo), theme: 'light' })
    await page.goto('/dashboard')
    await expect(page.locator(LEVEL_BADGE)).toHaveAttribute('data-tier', 'silver')
    await expect(page.locator('.level-hero')).toBeVisible()
    await expect(page.locator(DIALOG)).toHaveCount(0)
    const acknowledged = await page.evaluate(() =>
      Object.keys(window.localStorage).filter((key) =>
        key.startsWith('muasher.level.acknowledged'),
      ),
    )
    expect(acknowledged).toEqual([])
  })

  test('a certificate issued after the release shows the dialog once', async ({
    page,
  }, testInfo) => {
    await seedSignedIn(page, NAMAA, { locale: localeOf(testInfo), theme: 'light' })
    await page.goto('/verify')
    await waitForSignedInUser(page, NAMAA.id)
    expect(await reissueAsAdministrator(page, NAMAA_SILVER_CERTIFICATE)).toBe(201)

    await navigateInApp(page, '/dashboard')
    const dialog = page.locator(DIALOG)
    await expect(dialog).toBeVisible()
    await expect(page.locator('.modal:has(.level-achieved__body) [data-autofocus]')).toBeFocused()
    await settleFonts(page)
    await page.screenshot({
      path: `${SHOTS}/level-achieved-new-certificate-${suffixOf(testInfo)}.png`,
    })
    await page.keyboard.press('Escape')
    await expect(dialog).toHaveCount(0)

    await navigateInApp(page, '/verify')
    await expect(page.locator('.level-hero')).toHaveCount(0)
    await navigateInApp(page, '/dashboard')
    await expect(page.locator('.level-hero')).toBeVisible()
    await expect(page.locator(LEVEL_BADGE)).toHaveAttribute('data-tier', 'silver')
    await expect(page.locator(DIALOG)).toHaveCount(0)
  })
})
