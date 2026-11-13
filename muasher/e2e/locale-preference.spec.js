import { test, expect } from '@playwright/test'
import { seedLocale, seedSignedIn } from './support/session'

const OFFICER = {
  id: 7101,
  roles: ['criteria_officer'],
  name: { ar: 'خالد العمري', en: 'Khalid Al-Omari' },
}

const localeSwitch = (page) => page.getByRole('button', { name: /^(English|العربية)$/ }).first()

async function cookieValue(page, name) {
  const cookies = await page.context().cookies()
  return cookies.find((cookie) => cookie.name === name)?.value ?? null
}

test('a signed-in language switch is saved to the profile and wins over the guest cookie', async ({
  page,
}) => {
  await seedSignedIn(page, OFFICER, { locale: 'ar' })
  await page.goto('/admin/settings/criteria')
  await expect(page.locator('.criteria-page__title')).toBeVisible()
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar')

  const sent = page.waitForRequest(
    (request) => request.url().includes('/api/v1/me/preferences') && request.method() === 'PATCH',
  )
  const saved = page.waitForResponse((response) =>
    response.url().includes('/api/v1/me/preferences'),
  )
  await localeSwitch(page).click()
  expect((await sent).postDataJSON()).toEqual({ locale: 'en' })
  expect((await saved).status()).toBe(200)
  await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')

  await seedLocale(page, 'ar')
  await page.reload()
  await expect(page.locator('.criteria-page__title')).toBeVisible()
  await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  expect(await cookieValue(page, 'locale')).toBe('en')
})

test('guests keep their language in the locale cookie without any profile call', async ({
  page,
}) => {
  const profileCalls = []
  page.on('request', (request) => {
    if (request.url().includes('/api/v1/me/preferences')) profileCalls.push(request.url())
  })
  await page.goto('/auth/login')
  await expect(page.locator('html')).toHaveAttribute('lang', 'ar')
  await localeSwitch(page).click()
  await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  expect(await cookieValue(page, 'locale')).toBe('en')
  await page.reload()
  await expect(page.locator('html')).toHaveAttribute('lang', 'en')
  expect(profileCalls).toEqual([])
})
