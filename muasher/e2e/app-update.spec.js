import { test, expect } from '@playwright/test'
import { seedSignedIn } from './support/session'

// A tab left on an older bundle offers a reload once a newer build is deployed. Only a built bundle
// has hashed assets to compare, so this runs against the built preview (CI), not the dev server.
const ADMIN = { id: 7001, roles: ['system_admin'], name: { ar: 'نورة', en: 'Noura' } }

function locale(testInfo) {
  return testInfo.project.name === 'chromium-en-ltr' ? 'en' : 'ar'
}

test('offers a reload when a newer build is deployed, and can be put off', async ({
  page,
}, testInfo) => {
  await seedSignedIn(page, ADMIN, { locale: locale(testInfo) })
  // A newer build is now deployed: the live index.html names another entry script. (Rewritten in the
  // page, because the mock layer's service worker answers fetches that page routes never see.)
  await page.addInitScript(() => {
    const original = window.fetch.bind(window)
    window.fetch = async (input, init) => {
      const response = await original(input, init)
      if (!String(input).endsWith('/index.html')) return response
      const html = (await response.text()).replace(
        /\/assets\/index-[\w-]+\.js/,
        '/assets/index-next.js',
      )
      return new Response(html, { status: response.status, headers: response.headers })
    }
  })
  await page.goto('/dashboard')
  await expect(page.locator('main[aria-busy="false"]')).toBeVisible()
  await page.evaluate(() => window.dispatchEvent(new Event('focus')))

  const banner = page.getByTestId('app-update')
  await expect(banner).toBeVisible()
  const isEn = locale(testInfo) === 'en'
  await expect(banner).toContainText(isEn ? 'A new version' : 'يتوفر تحديث جديد')
  const reload = banner.getByRole('button', { name: isEn ? 'Reload the page' : 'أعد تحميل الصفحة' })
  await reload.focus()
  await expect(reload).toBeFocused()
  await banner.getByRole('button', { name: isEn ? 'Later' : 'لاحقًا' }).click()
  await expect(banner).toBeHidden()
})
