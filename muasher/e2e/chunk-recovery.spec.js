import { test, expect } from '@playwright/test'
import { seedPreferences } from './support/session'
import { DEV_TOTP_CODE, openSignIn, submitSignIn, typeCode } from './support/signIn'

const STAFF_ENROLLED = 'yasser.alghamdi@moashir.example.sa'

function isEn(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr'
}

function localeOf(testInfo) {
  return isEn(testInfo) ? 'en' : 'ar'
}

function chunkOf(view) {
  const source = new RegExp(`/src/views/auth/${view}\\.vue$`)
  const built = new RegExp(`/assets/${view}-[\\w-]+\\.js$`)
  return (url) => source.test(url.pathname) || built.test(url.pathname)
}

async function missFirstRequest(page, view) {
  const missed = []
  await page.context().route(chunkOf(view), (route) => {
    if (missed.length) return route.continue()
    missed.push(route.request().url())
    return route.fulfill({ status: 404, contentType: 'text/html', body: 'Not Found' })
  })
  return missed
}

async function markPageLife(page) {
  await page.evaluate(() => {
    window.moashirPageBeforeDeploy = true
  })
}

function samePageLife(page) {
  return page.evaluate(() => window.moashirPageBeforeDeploy === true)
}

test('a sign-in tab opened before a deploy reloads into a fresh sign-in when the MFA step is missing', async ({
  page,
}, testInfo) => {
  await seedPreferences(page, { locale: localeOf(testInfo) })
  await openSignIn(page)
  const missed = await missFirstRequest(page, 'Mfa')
  await markPageLife(page)
  const reloaded = page.waitForEvent('load')

  await submitSignIn(page, STAFF_ENROLLED)

  await reloaded
  expect(missed).toHaveLength(1)
  expect(await samePageLife(page)).toBe(false)
  await expect(page).toHaveURL(/\/auth\/login$/)
  await expect(page.locator('[name="identifier"]')).toBeVisible()
  await expect(page.locator('.auth-failure')).toHaveCount(0)

  await submitSignIn(page, STAFF_ENROLLED)
  await expect(page).toHaveURL(/\/auth\/mfa/)
  await expect(page.locator('.auth-mfa')).toBeVisible()
  await typeCode(page, DEV_TOTP_CODE)
  await expect(page.locator('.navbar--app')).toBeVisible()
})

test('a page opened before a deploy loads the target route in full when its chunk is missing', async ({
  page,
}, testInfo) => {
  await seedPreferences(page, { locale: localeOf(testInfo) })
  await openSignIn(page)
  const missed = await missFirstRequest(page, 'Register')
  await markPageLife(page)
  const reloaded = page.waitForEvent('load')

  await page.locator('.auth-form__switch a').click()

  await reloaded
  expect(missed).toHaveLength(1)
  expect(await samePageLife(page)).toBe(false)
  await expect(page).toHaveURL(/\/auth\/register$/)
  await expect(page.locator('.entity-register')).toBeVisible()
})
